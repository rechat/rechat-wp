import React from 'react'

const ListingFilters = () => {
    return (
        <div className="rch-filters">
        <div className="box-filter-listing-text">
            <input 
                type="search" 
                className="rch-text-filter" 
                id="content" 
                placeholder="Search by City ..." 
            />
        </div>

        <div className="box-filter-listing">
            <span className="toggleMain" id="rch-property-type-text">
                Property Type
            </span>
            <div className="rch-inside-filters rch-for-lease" style={{ display: "none" }}></div>
        </div>

        <div className="box-filter-listing rch-price-filter-listing">
            <span className="toggleMain" id="rch-price-text-filter">
                Price
            </span>
            <div className="rch-inside-filters rch-main-price" style={{ display: "none" }}></div>
        </div>

        <div className="box-filter-listing rch-price-filter-listing rch-beds-filter-listing">
            <span id="rch-beds-text-filter" className="toggleMain">
                Beds
            </span>
            <div className="rch-inside-filters rch-main-price" style={{ display: "none" }}></div>
        </div>

        <div className="box-filter-listing rch-price-filter-listing rch-bath-filter-listing">
            <span id="rch-baths-text-filter" className="toggleMain">
                Bath
            </span>
            <div className="rch-inside-filters rch-main-price rch-main-beds" style={{ display: "none" }}></div>
        </div>

        <div className="box-filter-listing">
            <span className="toggleMain more-filter-text">
                More Filters
                <span id="filter-badge" className="rch-filter-badge" style={{ display: "none" }}>
                    0
                </span>
            </span>
            <div className="rch-inside-filters rch-other-filter-listing" style={{ display: "none" }}></div>
        </div>

        <button type="button" className="reset-btn-all" >
            Reset
        </button>
    </div>
    )
}

export default ListingFilters