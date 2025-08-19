import React, { useEffect, useState, useRef } from 'react';
import apiFetch from '@wordpress/api-fetch';
import ListingFilters from './listings-filters';

const ListingMain = (props) => {
    // Destructure and convert props to appropriate types
    const {
        listing_per_page,
        maximum_bedrooms,
        minimum_bedrooms,
        maximum_bathrooms,
        minimum_bathrooms,
        maximum_year_built,
        minimum_year_built,
        maximum_square_meters,
        minimum_square_meters,
        maximum_price,
        minimum_price,
        property_types,
        own_listing,
        show_filter_bar,
        maximum_lot_square_meters,
        minimum_lot_square_meters,
        selectedStatuses,
        brandId: propsBrandId // Accept brandId from props
    } = props;
    // Convert string values to appropriate types
    const parsedListingPerPage = parseInt(listing_per_page) || 10;
    const parsedOwnListing = own_listing === 'true' || own_listing === true;
    const parsedShowFilterBar = show_filter_bar === 'true' || show_filter_bar === true;
    
    // Safely get site URL
    const siteUrl = typeof wp !== 'undefined' && wp.data && wp.data.select && wp.data.select("core") ? 
        wp.data.select("core").getSite()?.url : window.location.origin;

    // Use brandId from props if available, otherwise start with null and fetch it
    const [brandId, setBrandId] = useState(propsBrandId || null);
    const [listings, setListings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [mapBounds, setMapBounds] = useState(null);
    const [isFirstMapLoad, setIsFirstMapLoad] = useState(true);
    const mapRef = useRef(null);

    // Log the brand ID from props for debugging
    useEffect(() => {
        console.log('Brand ID from shortcode:', propsBrandId);
        console.log('Current brand ID state:', brandId);
        setBrandId(propsBrandId);
    }, [propsBrandId, brandId]);
console.log('Brand ID from shortcode:', brandId);
    // const fetchBrandId = async () => {
    //     // Only fetch if we don't already have a brandId from props
    //     if (propsBrandId) {
    //         console.log('Using brand ID from props:', propsBrandId);
    //         return;
    //     }

    //     try {
    //         const brandResponse = await apiFetch({ path: '/wp/v2/options' });
    //         if (brandResponse.rch_rechat_brand_id) {
    //             setBrandId(brandResponse.rch_rechat_brand_id);
    //             console.log('Fetched brand ID from API:', brandResponse.rch_rechat_brand_id);
    //         } else {
    //             console.error('Brand ID not found in WordPress options.');
    //         }
    //     } catch (error) {
    //         console.error('Error fetching brand ID:', error);
    //     }
    // };

    const fetchListings = (bounds = null) => {
        if (!brandId) return;

        const headers = {
            'Content-Type': 'application/json',
            'X-RECHAT-BRAND': brandId,
        };

        const bodyObject = {
            limit: Number(listing_per_page),
            maximum_bedrooms: (maximum_bedrooms ? Number(maximum_bedrooms) : ''),
            minimum_bedrooms: (minimum_bedrooms ? Number(minimum_bedrooms) : ''),
            maximum_bathrooms: (maximum_bathrooms ? Number(maximum_bathrooms) : ''),
            minimum_bathrooms: (minimum_bathrooms ? Number(minimum_bathrooms) : ''),
            maximum_year_built: (maximum_year_built ? Number(maximum_year_built) : ''),
            minimum_year_built: (minimum_year_built ? Number(minimum_year_built) : ''),
            maximum_square_meters: (maximum_square_meters ? Number(maximum_square_meters) : ''),
            minimum_square_meters: (minimum_square_meters ? Number(minimum_square_meters) : ''),
            minimum_price: (minimum_price ? Number(minimum_price) : ''),
            maximum_price: (maximum_price ? Number(maximum_price) : ''),
            minimum_lot_square_meters: (minimum_lot_square_meters ? Number(minimum_lot_square_meters) : ''),
            maximum_lot_square_meters: (maximum_lot_square_meters ? Number(maximum_lot_square_meters) : ''),
            property_types: property_types ? property_types.split(",").map(type => type.trim()) : [],
            listing_statuses: selectedStatuses?.length ? selectedStatuses : "",
            ...(own_listing && { brand: brandId })
        };

        if (bounds) {
            bodyObject.points = [
                { latitude: bounds.getNorthEast().lat(), longitude: bounds.getNorthEast().lng() },
                { latitude: bounds.getNorthEast().lat(), longitude: bounds.getSouthWest().lng() },
                { latitude: bounds.getSouthWest().lat(), longitude: bounds.getSouthWest().lng() },
                { latitude: bounds.getSouthWest().lat(), longitude: bounds.getNorthEast().lng() }
            ];
        }

        const filteredBody = Object.fromEntries(
            Object.entries(bodyObject).filter(([_, value]) => value !== undefined && value !== null && value !== "")
        );

        let body = null;
        if (Object.keys(filteredBody).length > 0) {
            body = JSON.stringify(filteredBody);
        }

        setLoading(true);
        fetch('https://api.rechat.com/valerts?order_by[]=-price', {
            method: 'POST',
            headers: headers,
            body: body,
        })
            .then(res => res.json())
            .then(data => {
                setListings(data.data);
                renderServerTemplates(data.data);

                if (isFirstMapLoad) {
                    initGoogleMap(data.data);
                    setIsFirstMapLoad(false);
                } else {
                    updateMapMarkers(data.data);
                }

                setLoading(false);
            })
            .catch(error => {
                console.error('Error:', error);
                setLoading(false);
            });
    };

    // useEffect(() => {
    //     // Only fetch brand ID if it's not provided in props
    //     if (!propsBrandId) {
    //         fetchBrandId();
    //     }
    // }, [propsBrandId]);

    useEffect(() => {
        setMapBounds(null);
        fetchListings();
    }, [
        brandId,
        listing_per_page,
        maximum_bedrooms,
        minimum_bedrooms,
        maximum_bathrooms,
        minimum_bathrooms,
        maximum_year_built,
        minimum_year_built,
        maximum_square_meters,
        minimum_square_meters,
        maximum_price,
        minimum_price,
        property_types,
        own_listing,
        maximum_lot_square_meters,
        minimum_lot_square_meters,
        property_types,
        selectedStatuses
    ]);

    const renderServerTemplates = (listings) => {
        if (!listings || listings.length === 0) return;

        apiFetch({
            path: '/rechat/v1/render-listing-template/',
            method: 'POST',
            data: { listings }
        }).then(response => {
            const listingContainer = document.getElementById('listing-list');
            if (listingContainer) {
                listingContainer.innerHTML = response.html;
            }
        }).catch(error => {
            console.error('Error rendering templates:', error);
        });
    };

    const initGoogleMap = (listings) => {
        if (!listings || listings.length === 0 || !window.google) return;

        const mapContainer = document.getElementById('rch-google-map');
        if (!mapContainer) return;

        const map = new google.maps.Map(mapContainer, {
            zoom: 10,
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true,
        });

        mapRef.current = map;

        const bounds = new google.maps.LatLngBounds();
        const markers = addMarkersToMap(map, listings, bounds);

        if (markers.length > 0) {
            map.fitBounds(bounds);
        }

        let boundsChangeTimeout;
        map.addListener('bounds_changed', () => {
            clearTimeout(boundsChangeTimeout);
            boundsChangeTimeout = setTimeout(() => {
                const newBounds = map.getBounds();
                if (newBounds && !isFirstMapLoad) {
                    setMapBounds(newBounds);
                    fetchListings(newBounds);
                }
            }, 800);
        });

        return map;
    };

    const addMarkersToMap = (map, listings, bounds = null) => {
        return listings.map(listing => {
            if (listing.property && listing.property.latitude && listing.property.longitude) {
                const position = {
                    lat: parseFloat(listing.property.latitude),
                    lng: parseFloat(listing.property.longitude)
                };

                if (bounds) bounds.extend(position);

                const marker = new google.maps.Marker({
                    position: position,
                    map: map,
                    title: listing.property.address.full_address
                });

                const price = listing.price ? `$${new Intl.NumberFormat().format(listing.price)}` : 'Price not available';
                const infoContent = `
                    <div class="rch-map-info-window">
                        <h4>${price}</h4>
                        <p>${listing.property.address.full_address}</p>
                        <p>${listing.property.bedrooms || 0} beds, ${listing.property.bathrooms || 0} baths</p>
                    </div>
                `;

                const infoWindow = new google.maps.InfoWindow({
                    content: infoContent
                });

                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });

                return marker;
            }
            return null;
        }).filter(Boolean);
    };

    const updateMapMarkers = (listings) => {
        if (!mapRef.current || !listings) return;

        addMarkersToMap(mapRef.current, listings);
    };

    return (
        <div>
            {show_filter_bar && (<ListingFilters />)}

            {loading ? (
                <div id="rch-loading-listing" className="rch-listing-skeleton-loader">
                    {[...Array(6)].map((_, i) => (
                        <div className="rch-listing-item-skeleton" key={i}>
                            <div className="rch-skeleton-image"></div>
                            <h3 className="rch-skeleton-text rch-skeleton-price"></h3>
                            <p className="rch-skeleton-text rch-skeleton-address"></p>
                            <ul>
                                <li className="rch-skeleton-text rch-skeleton-list-item"></li>
                                <li className="rch-skeleton-text rch-skeleton-list-item"></li>
                                <li className="rch-skeleton-text rch-skeleton-list-item"></li>
                            </ul>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="rch-container-listing-list">
                    <div id="rch-google-map" className="rch-map-listing-list"></div>
                    <div className="rch-under-main-listing">
                        <div id="listing-list" className="rch-listing-list">
                            {Array.isArray(listings) && listings.length === 0 && (
                                <div>No Listing Found</div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default ListingMain;