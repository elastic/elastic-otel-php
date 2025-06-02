/*
 * Copyright Elasticsearch B.V. and/or licensed to Elasticsearch B.V. under one
 * or more contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

/*
 * Copyright The OpenTelemetry Authors
 * SPDX-License-Identifier: Apache-2.0
 */

/*
 * DO NOT EDIT, this is an Auto-generated file from:
 * buildscripts/semantic-convention/templates/registry/semantic_attributes-h.j2
 */












#pragma once


namespace opentelemetry {
namespace semconv
{
namespace geo
{

/**
 * Two-letter code representing continent’s name.
 */
static constexpr const char *kGeoContinentCode
 = "geo.continent.code";

/**
 * Two-letter ISO Country Code (<a href="https://wikipedia.org/wiki/ISO_3166-1#Codes">ISO 3166-1 alpha2</a>).
 */
static constexpr const char *kGeoCountryIsoCode
 = "geo.country.iso_code";

/**
 * Locality name. Represents the name of a city, town, village, or similar populated place.
 */
static constexpr const char *kGeoLocalityName
 = "geo.locality.name";

/**
 * Latitude of the geo location in <a href="https://wikipedia.org/wiki/World_Geodetic_System#WGS84">WGS84</a>.
 */
static constexpr const char *kGeoLocationLat
 = "geo.location.lat";

/**
 * Longitude of the geo location in <a href="https://wikipedia.org/wiki/World_Geodetic_System#WGS84">WGS84</a>.
 */
static constexpr const char *kGeoLocationLon
 = "geo.location.lon";

/**
 * Postal code associated with the location. Values appropriate for this field may also be known as a postcode or ZIP code and will vary widely from country to country.
 */
static constexpr const char *kGeoPostalCode
 = "geo.postal_code";

/**
 * Region ISO code (<a href="https://wikipedia.org/wiki/ISO_3166-2">ISO 3166-2</a>).
 */
static constexpr const char *kGeoRegionIsoCode
 = "geo.region.iso_code";


namespace GeoContinentCodeValues
{
/**
 * Africa
 */
static constexpr const char *
 kAf
 = "AF";

/**
 * Antarctica
 */
static constexpr const char *
 kAn
 = "AN";

/**
 * Asia
 */
static constexpr const char *
 kAs
 = "AS";

/**
 * Europe
 */
static constexpr const char *
 kEu
 = "EU";

/**
 * North America
 */
static constexpr const char *
 kNa
 = "NA";

/**
 * Oceania
 */
static constexpr const char *
 kOc
 = "OC";

/**
 * South America
 */
static constexpr const char *
 kSa
 = "SA";

}


}
}
}
