<?php

class Location extends LoggedUserMethodHandler
{
    public function listCountriesHandler() {
        $countries = $this->database->fetchAll("SELECT
                id, country_code, name
            FROM location_countries"
        );

        $this->response["data"] = $countries;
        $this->output();
    }

    public function listRegionsHandler() {
        $this->requireParam("country_code");

        $regions = $this->database->fetchAll("SELECT
                id, region_code, name
            FROM location_regions WHERE country_code = :country_code",
            array("country_code" => $this->params["country_code"])
        );

        $this->response["data"] = $regions;
        $this->output();
    }

    public function listCitiesHandler() {
        $this->requireParam("country_code");
        $this->requireParam("region_code");

        $cities = $this->database->fetchAll("SELECT
                id, country_code, region_code, name
            FROM location_cities WHERE country_code = :country_code AND region_code = :region_code",
            array(
                "country_code" => $this->params["country_code"],
                "region_code" => $this->params["region_code"]
            )
        );

        $this->response["data"] = $cities;
        $this->output();
    }
}