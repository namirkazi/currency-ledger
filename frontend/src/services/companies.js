import { request } from "./api";

export const getCompanies = () => {
  return request("/companies/list.php");
};

export const createCompany = (data) => {
  return request("/companies/create.php", {
    method: "POST",
    body: JSON.stringify(data),
  });
};

export const updateCompany = (data) => {
  return request("/companies/update.php", {
    method: "POST",
    body: JSON.stringify(data),
  });
};
