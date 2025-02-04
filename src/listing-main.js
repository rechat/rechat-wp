import React, { useEffect, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import ListingFilters from './listings-filters';
const ListingMain = (
    { listing_per_page,
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
        selectedStatuses
    }
) => {
    const siteUrl = wp.data.select("core").getSite()?.url;

    const [brandId, setBrandId] = useState(null);
    const [listings, setListings] = useState([]); // State to store fetched listings
    const [loading, setLoading] = useState(true); // State to track loading status
    const fetchBrandId = async () => {
        try {
            const brandResponse = await apiFetch({ path: '/wp/v2/options' });
            if (brandResponse.rch_rechat_brand_id) {
                setBrandId(brandResponse.rch_rechat_brand_id);
            } else {
                console.error('Brand ID not found in WordPress options.');
            }
        } catch (error) {
            console.error('Error fetching brand ID:', error);
        }
    };

    useEffect(() => {
        fetchBrandId();
    }, []);
    useEffect(() => {
        if (brandId) {

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
                    setLoading(false);
                })
                .catch(error => {
                    console.error('Error:', error);
                    setLoading(false);
                });
        }
    }, [brandId,
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
    // Add brandId, listing_per_page, and maximum_bedrooms as dependencies
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
                <div id="listing-list" className="rch-listing-list">
                    {Array.isArray(listings) && listings.length > 0 ? (
                        listings.map((listing) => (
                            <div className="house-item" key={listing.id}>
                                <a href="#">
                                    <picture>
                                        <img src={listing.cover_image_url || `${siteUrl}/wp-content/plugins/rechat-plugin/assets/images/placeholder.webp`} alt="House Image" />
                                    </picture>
                                    {listing.price && <h3>$ {new Intl.NumberFormat().format(listing.price)}</h3>}
                                    {(listing.address.street_number || listing.address.street_name || listing.address.city || listing.address.state) && (
                                        <p>
                                            {`${listing.address.street_number} ${listing.address.street_name}, ${listing.address.city}, ${listing.address.state}`}
                                        </p>
                                    )}
                                    <ul>
                                        {listing.compact_property.bedroom_count > 0 && (
                                            <li>
                                                <img src={`${siteUrl}/wp-content/plugins/rechat-plugin/assets/images/bed.svg`} alt="Beds" />
                                                <b>{listing.compact_property.bedroom_count}</b> Beds
                                            </li>
                                        )}
                                        {listing.compact_property.full_bathroom_count > 0 && (
                                            <li>
                                                <img src={`${siteUrl}/wp-content/plugins/rechat-plugin/assets/images/shower-full.svg`} alt="Full shower" />
                                                <b>{listing.compact_property.full_bathroom_count}</b> Full Baths
                                            </li>
                                        )}
                                        {listing.compact_property.half_bathroom_count > 0 && (
                                            <li>
                                                <img src={`${siteUrl}/wp-content/plugins/rechat-plugin/assets/images/shower-half.svg`} alt="Half shower" />
                                                <b>{listing.compact_property.half_bathroom_count}</b> Half Baths
                                            </li>
                                        )}
                                        {listing.compact_property.square_meters > 0 && (
                                            <li>
                                                <img src={`${siteUrl}/wp-content/plugins/rechat-plugin/assets/images/sq.svg`} alt="sq ft" />
                                                <b>{new Intl.NumberFormat().format(listing.compact_property.square_meters)}</b> SQ.FT
                                            </li>
                                        )}
                                    </ul>
                                </a>
                            </div>
                        ))
                    ) : (
                        <div>No Listing Found</div>
                    )}

                </div>
            )}
        </div>
    );
};

export default ListingMain;