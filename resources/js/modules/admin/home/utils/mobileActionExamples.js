/**
 * Known mobile deep-link paths and action-value examples.
 * Keep in sync with the mobile CatalogActionHelper / HomeContentActionHelper.
 */
export const MOBILE_SCREEN_PATHS = {
    flights: '/flight-search',
    hotels: '/hotel-search',
    esim: '/esim-countries',
    travelInsurance: '/travel-insurance',
    orangeInsurance: '/orange-insurance',
    vehicleInsurance: '/vehicle-insurance',
};

const CATALOG_KEY_PATHS = {
    esim: MOBILE_SCREEN_PATHS.esim,
    travel_insurance: MOBILE_SCREEN_PATHS.travelInsurance,
    orange_insurance: MOBILE_SCREEN_PATHS.orangeInsurance,
    mandatory_insurance: MOBILE_SCREEN_PATHS.vehicleInsurance,
};

const CATEGORY_PATHS = {
    flights: MOBILE_SCREEN_PATHS.flights,
    hotels: MOBILE_SCREEN_PATHS.hotels,
    insurance: MOBILE_SCREEN_PATHS.travelInsurance,
    esim: MOBILE_SCREEN_PATHS.esim,
};

/**
 * @param {{ actionType: string, category?: string|null, catalogKey?: string|null }} opts
 */
export function actionValueExample({ actionType, category = null, catalogKey = null }) {
    if (actionType === 'url') {
        return 'https://example.com/offer';
    }

    if (actionType === 'none') {
        return '';
    }

    if (actionType === 'search_flights') {
        return MOBILE_SCREEN_PATHS.flights;
    }

    if (actionType === 'search_hotels') {
        return MOBILE_SCREEN_PATHS.hotels;
    }

    if (actionType === 'search_insurance') {
        return MOBILE_SCREEN_PATHS.travelInsurance;
    }

    if (actionType === 'search_esim') {
        return MOBILE_SCREEN_PATHS.esim;
    }

    if (actionType === 'route') {
        if (catalogKey && CATALOG_KEY_PATHS[catalogKey]) {
            return CATALOG_KEY_PATHS[catalogKey];
        }

        if (category && CATEGORY_PATHS[category]) {
            return CATEGORY_PATHS[category];
        }

        return MOBILE_SCREEN_PATHS.flights;
    }

    return MOBILE_SCREEN_PATHS.flights;
}

export const KNOWN_ROUTE_EXAMPLES = [
    MOBILE_SCREEN_PATHS.flights,
    MOBILE_SCREEN_PATHS.hotels,
    MOBILE_SCREEN_PATHS.esim,
    MOBILE_SCREEN_PATHS.travelInsurance,
    MOBILE_SCREEN_PATHS.orangeInsurance,
    MOBILE_SCREEN_PATHS.vehicleInsurance,
];
