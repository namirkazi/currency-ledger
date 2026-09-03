<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

$user = requireAuth();

$data = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($data)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid JSON request.'
    ], 400);
}


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$type = strtoupper(
    trim($data['type'] ?? 'BUY')
);

$fromCurrencyId = filter_var(
    $data['from_currency_id'] ?? null,
    FILTER_VALIDATE_INT
);

$toCurrencyId = filter_var(
    $data['to_currency_id'] ?? null,
    FILTER_VALIDATE_INT
);

$fromAmount = decimalValue(
    $data['from_amount'] ?? ''
);

$toAmount = decimalValue(
    $data['to_amount'] ?? ''
);

$exchangeRate = decimalValue(
    $data['exchange_rate'] ?? ''
);

$requestId =
    $_SERVER['HTTP_X_IDEMPOTENCY_KEY']
    ?? trim($data['request_id'] ?? '');


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if (!in_array($type, ['BUY', 'SELL'], true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Transaction type must be BUY or SELL.'
    ], 422);
}

if (!$fromCurrencyId || $fromCurrencyId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid source currency is required.'
    ], 422);
}

if (!$toCurrencyId || $toCurrencyId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid destination currency is required.'
    ], 422);
}

if ($fromCurrencyId === $toCurrencyId) {
    jsonResponse([
        'success' => false,
        'message' => 'Source and destination currencies must be different.'
    ], 422);
}

if (bccomp($fromAmount, '0', 6) <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'From amount must be greater than zero.'
    ], 422);
}

if (bccomp($toAmount, '0', 6) <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'To amount must be greater than zero.'
    ], 422);
}

if (bccomp($exchangeRate, '0', 6) <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Exchange rate must be greater than zero.'
    ], 422);
}

if (!preg_match('/^[a-f0-9-]{16,64}$/i', $requestId)) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid request ID is required.'
    ], 422);
}


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | IDEMPOTENCY
    |--------------------------------------------------------------------------
    */

    $existingStmt = $pdo->prepare("
        SELECT id
        FROM transactions
        WHERE request_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $existingStmt->execute([
        $requestId
    ]);

    $existing = $existingStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($existing) {

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'duplicate' => true,
            'transaction_id' => (int) $existing['id']
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | GET AED BASE CURRENCY
    |--------------------------------------------------------------------------
    */

    $aedStmt = $pdo->prepare("
        SELECT id, code
        FROM currencies
        WHERE code = 'AED'
        LIMIT 1
    ");

    $aedStmt->execute();

    $aedCurrency = $aedStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$aedCurrency) {
        throw new RuntimeException(
            'AED currency is not configured.'
        );
    }

    $aedCurrencyId = (int) $aedCurrency['id'];


    /*
    |--------------------------------------------------------------------------
    | DETERMINE DIRECTION
    |--------------------------------------------------------------------------
    |
    | BUY:
    |
    | AED -> Foreign Currency
    |
    | SELL:
    |
    | Foreign Currency -> AED
    |
    */

    $isBuyingForeignCurrency =
        (int) $fromCurrencyId === $aedCurrencyId
        && (int) $toCurrencyId !== $aedCurrencyId;

    $isSellingForeignCurrency =
        (int) $fromCurrencyId !== $aedCurrencyId
        && (int) $toCurrencyId === $aedCurrencyId;


    /*
    |--------------------------------------------------------------------------
    | ONLY AED PAIRS ALLOWED
    |--------------------------------------------------------------------------
    */

    if (
        !$isBuyingForeignCurrency
        && !$isSellingForeignCurrency
    ) {
        throw new RuntimeException(
            'All currency exchanges must be between AED and a foreign currency.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOCK SOURCE BALANCE
    |--------------------------------------------------------------------------
    */

    $sourceBalanceStmt = $pdo->prepare("
        SELECT
            ab.currency_id,
            ab.balance,
            c.code,
            c.name
        FROM account_balances ab
        INNER JOIN currencies c
            ON c.id = ab.currency_id
        WHERE ab.currency_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $sourceBalanceStmt->execute([
        $fromCurrencyId
    ]);

    $sourceBalance = $sourceBalanceStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$sourceBalance) {
        throw new RuntimeException(
            'Source currency balance does not exist.'
        );
    }

    if (
        bccomp(
            $sourceBalance['balance'],
            $fromAmount,
            6
        ) < 0
    ) {
        throw new RuntimeException(
            "Insufficient {$sourceBalance['code']} balance."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE DESTINATION CURRENCY
    |--------------------------------------------------------------------------
    */

    $destinationCurrencyStmt = $pdo->prepare("
        SELECT
            id,
            code,
            name
        FROM currencies
        WHERE id = ?
          AND active = 1
        LIMIT 1
    ");

    $destinationCurrencyStmt->execute([
        $toCurrencyId
    ]);

    $destinationCurrency =
        $destinationCurrencyStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$destinationCurrency) {
        throw new RuntimeException(
            'Destination currency does not exist or is inactive.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FIFO INVENTORY CALCULATION
    |--------------------------------------------------------------------------
    */

    $realizedProfit = '0.000000';

    $totalCostBasis = '0.000000';

    $saleAllocations = [];


    /*
    |--------------------------------------------------------------------------
    | SELLING FOREIGN CURRENCY
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | Sell 30,000 USD
    |
    | FIFO Lots:
    |
    | Lot 1:
    | 20,000 USD @ 3.67
    |
    | Lot 2:
    | 20,000 USD @ 3.66
    |
    | Consume:
    |
    | 20,000 from Lot 1
    | 10,000 from Lot 2
    |
    */

    if ($isSellingForeignCurrency) {

        $lotsStmt = $pdo->prepare("
            SELECT
                id,
                remaining_amount,
                acquisition_rate
            FROM inventory_lots
            WHERE currency_id = ?
              AND remaining_amount > 0
            ORDER BY created_at ASC, id ASC
            FOR UPDATE
        ");

        $lotsStmt->execute([
            $fromCurrencyId
        ]);

        $lots = $lotsStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        $remainingToSell = $fromAmount;


        /*
        |--------------------------------------------------------------------------
        | FIFO LOOP
        |--------------------------------------------------------------------------
        */

        foreach ($lots as $lot) {

            if (
                bccomp(
                    $remainingToSell,
                    '0',
                    6
                ) <= 0
            ) {
                break;
            }


            /*
            |--------------------------------------------------------------------------
            | DETERMINE HOW MUCH TO TAKE
            |--------------------------------------------------------------------------
            */

            if (
                bccomp(
                    $lot['remaining_amount'],
                    $remainingToSell,
                    6
                ) <= 0
            ) {

                /*
                 * Consume entire lot.
                 */

                $take =
                    $lot['remaining_amount'];
            } else {

                /*
                 * Partial lot consumption.
                 */

                $take =
                    $remainingToSell;
            }


            /*
            |--------------------------------------------------------------------------
            | CALCULATE COST OF THIS PORTION
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | 10,000 USD
            | x
            | 3.67 AED
            |
            | =
            |
            | 36,700 AED
            |
            */

            $lotCost = bcmul(
                $take,
                $lot['acquisition_rate'],
                6
            );


            /*
            |--------------------------------------------------------------------------
            | ADD TO TOTAL COST BASIS
            |--------------------------------------------------------------------------
            */

            $totalCostBasis = bcadd(
                $totalCostBasis,
                $lotCost,
                6
            );


            /*
            |--------------------------------------------------------------------------
            | SAVE ALLOCATION TEMPORARILY
            |--------------------------------------------------------------------------
            */

            $saleAllocations[] = [

                'lot_id' =>
                $lot['id'],

                'amount' =>
                $take,

                'acquisition_rate' =>
                $lot['acquisition_rate'],

                'cost' =>
                $lotCost
            ];


            /*
            |--------------------------------------------------------------------------
            | REDUCE REMAINING SALE AMOUNT
            |--------------------------------------------------------------------------
            */

            $remainingToSell = bcsub(
                $remainingToSell,
                $take,
                6
            );
        }


        /*
        |--------------------------------------------------------------------------
        | INVENTORY CHECK
        |--------------------------------------------------------------------------
        */

        if (
            bccomp(
                $remainingToSell,
                '0',
                6
            ) > 0
        ) {
            throw new RuntimeException(
                "Insufficient inventory for {$sourceBalance['code']}."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CALCULATE REALIZED PROFIT
        |--------------------------------------------------------------------------
        |
        | AED RECEIVED
        |
        | -
        |
        | ORIGINAL AED COST
        |
        */

        $realizedProfit = bcsub(
            $toAmount,
            $totalCostBasis,
            6
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE TRANSACTION
    |--------------------------------------------------------------------------
    */

    $transactionStmt = $pdo->prepare("
        INSERT INTO transactions
        (
            request_id,
            type,
            from_currency_id,
            from_amount,
            to_currency_id,
            to_amount,
            exchange_rate,
            realized_profit,
            status,
            created_by
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'COMPLETED',
            ?
        )
    ");

    $transactionStmt->execute([
        $requestId,
        $type,
        $fromCurrencyId,
        $fromAmount,
        $toCurrencyId,
        $toAmount,
        $exchangeRate,
        $realizedProfit,
        $user['id']
    ]);

    $transactionId =
        $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | CREATE INVENTORY LOT ON PURCHASE
    |--------------------------------------------------------------------------
    |
    | AED -> FOREIGN CURRENCY
    |
    | Example:
    |
    | AED 36,700
    | ->
    | USD 10,000
    |
    | Cost per USD:
    |
    | 36,700 / 10,000
    |
    | =
    |
    | 3.67 AED
    |
    */

    if ($isBuyingForeignCurrency) {

        $acquisitionRate = bcdiv(
            $fromAmount,
            $toAmount,
            6
        );


        $inventoryStmt = $pdo->prepare("
            INSERT INTO inventory_lots
            (
                source_transaction_id,
                original_amount,
                remaining_amount,
                acquisition_rate,
                currency_id,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
        ");

        $inventoryStmt->execute([
            $transactionId,
            $toAmount,
            $toAmount,
            $acquisitionRate,
            $toCurrencyId
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | CONSUME INVENTORY LOTS ON SALE
    |--------------------------------------------------------------------------
    */

    if (
        $isSellingForeignCurrency
        && !empty($saleAllocations)
    ) {

        /*
         * Record allocation.
         */

        $allocationStmt = $pdo->prepare("
            INSERT INTO sale_allocations
            (
                transaction_id,
                inventory_lot_id,
                currency_amount,
                acquisition_rate,
                cost_amount
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");


        /*
         * Reduce inventory lot.
         */

        $updateLotStmt = $pdo->prepare("
            UPDATE inventory_lots
            SET remaining_amount =
                remaining_amount - ?
            WHERE id = ?
        ");


        foreach ($saleAllocations as $allocation) {

            /*
             * Save audit record.
             */

            $allocationStmt->execute([
                $transactionId,
                $allocation['lot_id'],
                $allocation['amount'],
                $allocation['acquisition_rate'],
                $allocation['cost']
            ]);


            /*
             * Reduce available inventory.
             */

            $updateLotStmt->execute([
                $allocation['amount'],
                $allocation['lot_id']
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE ACCOUNT BALANCES
    |--------------------------------------------------------------------------
    */


    /*
     * Deduct source currency.
     */

    $deductStmt = $pdo->prepare("
        UPDATE account_balances
        SET balance = balance - ?
        WHERE currency_id = ?
    ");

    $deductStmt->execute([
        $fromAmount,
        $fromCurrencyId
    ]);


    /*
     * Add destination currency.
     */

    $addStmt = $pdo->prepare("
        INSERT INTO account_balances
        (
            currency_id,
            balance
        )
        VALUES
        (
            ?,
            ?
        )

        ON DUPLICATE KEY UPDATE
            balance = balance + VALUES(balance)
    ");

    $addStmt->execute([
        $toCurrencyId,
        $toAmount
    ]);


    /*
    |--------------------------------------------------------------------------
    | CREATE LEDGER ENTRIES
    |--------------------------------------------------------------------------
    */

    $ledgerStmt = $pdo->prepare("
        INSERT INTO ledger_entries
        (
            currency_id,
            amount,
            entry_type,
            transaction_id
        )
        VALUES
        (
            ?,
            ?,
            'TRADE',
            ?
        )
    ");


    /*
     * Source currency leaves.
     */

    $negativeAmount = bcmul(
        $fromAmount,
        '-1',
        6
    );

    $ledgerStmt->execute([
        $fromCurrencyId,
        $negativeAmount,
        $transactionId
    ]);


    /*
     * Destination currency enters.
     */

    $ledgerStmt->execute([
        $toCurrencyId,
        $toAmount,
        $transactionId
    ]);


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    jsonResponse([
        'success' => true,

        'message' =>
        'Currency exchange completed successfully.',

        'transaction' => [

            'id' =>
            (int) $transactionId,

            'type' =>
            $type,

            'from_currency_id' =>
            $fromCurrencyId,

            'from_amount' =>
            $fromAmount,

            'to_currency_id' =>
            $toCurrencyId,

            'to_amount' =>
            $toAmount,

            'exchange_rate' =>
            $exchangeRate,

            'cost_basis' =>
            $totalCostBasis,

            'realized_profit' =>
            $realizedProfit
        ]
    ], 201);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'exchange.php: ' .
            $e->getMessage()
    );

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
