/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/listing-main.js":
/*!*****************************!*\
  !*** ./src/listing-main.js ***!
  \*****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _listings_filters__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./listings-filters */ "./src/listings-filters.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);




const ListingMain = ({
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
  selectedStatuses
}) => {
  const siteUrl = wp.data.select("core").getSite()?.url;
  const [brandId, setBrandId] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [listings, setListings] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)([]); // State to store fetched listings
  const [loading, setLoading] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(true); // State to track loading status
  const fetchBrandId = async () => {
    try {
      const brandResponse = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
        path: '/wp/v2/options'
      });
      if (brandResponse.rch_rechat_brand_id) {
        setBrandId(brandResponse.rch_rechat_brand_id);
      } else {
        console.error('Brand ID not found in WordPress options.');
      }
    } catch (error) {
      console.error('Error fetching brand ID:', error);
    }
  };
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    fetchBrandId();
  }, []);
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (brandId) {
      const headers = {
        'Content-Type': 'application/json',
        'X-RECHAT-BRAND': brandId
      };
      const bodyObject = {
        limit: Number(listing_per_page),
        maximum_bedrooms: maximum_bedrooms ? Number(maximum_bedrooms) : '',
        minimum_bedrooms: minimum_bedrooms ? Number(minimum_bedrooms) : '',
        maximum_bathrooms: maximum_bathrooms ? Number(maximum_bathrooms) : '',
        minimum_bathrooms: minimum_bathrooms ? Number(minimum_bathrooms) : '',
        maximum_year_built: maximum_year_built ? Number(maximum_year_built) : '',
        minimum_year_built: minimum_year_built ? Number(minimum_year_built) : '',
        maximum_square_meters: maximum_square_meters ? Number(maximum_square_meters) : '',
        minimum_square_meters: minimum_square_meters ? Number(minimum_square_meters) : '',
        minimum_price: minimum_price ? Number(minimum_price) : '',
        maximum_price: maximum_price ? Number(maximum_price) : '',
        minimum_lot_square_meters: minimum_lot_square_meters ? Number(minimum_lot_square_meters) : '',
        maximum_lot_square_meters: maximum_lot_square_meters ? Number(maximum_lot_square_meters) : '',
        property_types: property_types ? property_types.split(",").map(type => type.trim()) : [],
        listing_statuses: selectedStatuses?.length ? selectedStatuses : "",
        ...(own_listing && {
          brand: brandId
        })
      };
      const filteredBody = Object.fromEntries(Object.entries(bodyObject).filter(([_, value]) => value !== undefined && value !== null && value !== ""));
      let body = null;
      if (Object.keys(filteredBody).length > 0) {
        body = JSON.stringify(filteredBody);
      }
      setLoading(true);
      fetch('https://api.rechat.com/valerts?order_by[]=-price', {
        method: 'POST',
        headers: headers,
        body: body
      }).then(res => res.json()).then(data => {
        console.log(data);
        setListings(data.data);
        setLoading(false);
      }).catch(error => {
        console.error('Error:', error);
        setLoading(false);
      });
    }
  }, [brandId, listing_per_page, maximum_bedrooms, minimum_bedrooms, maximum_bathrooms, minimum_bathrooms, maximum_year_built, minimum_year_built, maximum_square_meters, minimum_square_meters, maximum_price, minimum_price, property_types, own_listing, maximum_lot_square_meters, minimum_lot_square_meters, property_types, selectedStatuses]);
  // Add brandId, listing_per_page, and maximum_bedrooms as dependencies
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
    children: [show_filter_bar && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_listings_filters__WEBPACK_IMPORTED_MODULE_2__["default"], {}), loading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
      id: "rch-loading-listing",
      className: "rch-listing-skeleton-loader",
      children: [...Array(6)].map((_, i) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
        className: "rch-listing-item-skeleton",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
          className: "rch-skeleton-image"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("h3", {
          className: "rch-skeleton-text rch-skeleton-price"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
          className: "rch-skeleton-text rch-skeleton-address"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("ul", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("li", {
            className: "rch-skeleton-text rch-skeleton-list-item"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("li", {
            className: "rch-skeleton-text rch-skeleton-list-item"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("li", {
            className: "rch-skeleton-text rch-skeleton-list-item"
          })]
        })]
      }, i))
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
      id: "listing-list",
      className: "rch-listing-list",
      children: Array.isArray(listings) && listings.length > 0 ? listings.map(listing => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
        className: "house-item",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("a", {
          href: "#",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("picture", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("img", {
              src: listing.cover_image_url || `${siteUrl}/wp-content/plugins/rechat-plugin/assets/images/placeholder.webp`,
              alt: "House Image"
            })
          }), listing.price && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("h3", {
            children: ["$ ", new Intl.NumberFormat().format(listing.price)]
          }), (listing.address.street_number || listing.address.street_name || listing.address.city || listing.address.state) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
            children: `${listing.address.street_number} ${listing.address.street_name}, ${listing.address.city}, ${listing.address.state}`
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("ul", {
            children: [listing.compact_property.bedroom_count > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("li", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("img", {
                src: `${siteUrl}/wp-content/plugins/rechat-plugin/assets/images/bed.svg`,
                alt: "Beds"
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("b", {
                children: listing.compact_property.bedroom_count
              }), " Beds"]
            }), listing.compact_property.full_bathroom_count > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("li", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("img", {
                src: `${siteUrl}/wp-content/plugins/rechat-plugin/assets/images/shower-full.svg`,
                alt: "Full shower"
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("b", {
                children: listing.compact_property.full_bathroom_count
              }), " Full Baths"]
            }), listing.compact_property.half_bathroom_count > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("li", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("img", {
                src: `${siteUrl}/wp-content/plugins/rechat-plugin/assets/images/shower-half.svg`,
                alt: "Half shower"
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("b", {
                children: listing.compact_property.half_bathroom_count
              }), " Half Baths"]
            }), listing.compact_property.square_meters > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("li", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("img", {
                src: `${siteUrl}/wp-content/plugins/rechat-plugin/assets/images/sq.svg`,
                alt: "sq ft"
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("b", {
                children: new Intl.NumberFormat().format(listing.compact_property.square_meters)
              }), " SQ.FT"]
            })]
          })]
        })
      }, listing.id)) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
        children: "No Listing Found"
      })
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ListingMain);

/***/ }),

/***/ "./src/listings-filters.js":
/*!*********************************!*\
  !*** ./src/listings-filters.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


const ListingFilters = () => {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
    className: "rch-filters",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
      className: "box-filter-listing-text",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("input", {
        type: "search",
        className: "rch-text-filter",
        id: "content",
        placeholder: "Search by City ..."
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
      className: "box-filter-listing",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("span", {
        className: "toggleMain",
        id: "rch-property-type-text",
        children: "Property Type"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
        className: "rch-inside-filters rch-for-lease",
        style: {
          display: "none"
        }
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
      className: "box-filter-listing rch-price-filter-listing",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("span", {
        className: "toggleMain",
        id: "rch-price-text-filter",
        children: "Price"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
        className: "rch-inside-filters rch-main-price",
        style: {
          display: "none"
        }
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
      className: "box-filter-listing rch-price-filter-listing rch-beds-filter-listing",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("span", {
        id: "rch-beds-text-filter",
        className: "toggleMain",
        children: "Beds"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
        className: "rch-inside-filters rch-main-price",
        style: {
          display: "none"
        }
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
      className: "box-filter-listing rch-price-filter-listing rch-bath-filter-listing",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("span", {
        id: "rch-baths-text-filter",
        className: "toggleMain",
        children: "Bath"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
        className: "rch-inside-filters rch-main-price rch-main-beds",
        style: {
          display: "none"
        }
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
      className: "box-filter-listing",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("span", {
        className: "toggleMain more-filter-text",
        children: ["More Filters", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("span", {
          id: "filter-badge",
          className: "rch-filter-badge",
          style: {
            display: "none"
          },
          children: "0"
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
        className: "rch-inside-filters rch-other-filter-listing",
        style: {
          display: "none"
        }
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("button", {
      type: "button",
      className: "reset-btn-all",
      children: "Reset"
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ListingFilters);

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

/***/ }),

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/server-side-render":
/*!******************************************!*\
  !*** external ["wp","serverSideRender"] ***!
  \******************************************/
/***/ ((module) => {

module.exports = window["wp"]["serverSideRender"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/server-side-render */ "@wordpress/server-side-render");
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _listing_main__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./listing-main */ "./src/listing-main.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
const {
  registerBlockType
} = wp.blocks;
const {
  InspectorControls,
  ColorPalette
} = wp.blockEditor || wp.editor;
const {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  MultiSelectControl,
  CheckboxControl,
  ToggleControl,
  RadioControl
} = wp.components;
 // useState and useEffect hooks



//regions block

registerBlockType('rch-rechat-plugin/regions-block', {
  title: 'Regions Block',
  description: 'Block for showing Regions',
  icon: 'admin-site',
  category: 'widgets',
  attributes: {
    postsPerPage: {
      type: 'number',
      default: 5
    },
    regionBgColor: {
      type: 'string',
      default: '#edf1f5'
    },
    textColor: {
      type: 'string',
      default: '#000'
    }
  },
  edit({
    attributes,
    setAttributes
  }) {
    const {
      postsPerPage,
      regionBgColor,
      textColor
    } = attributes;
    function updatePostPerPage(value) {
      setAttributes({
        postsPerPage: value
      });
    }
    function regionBackgroundSelect(newColor) {
      setAttributes({
        regionBgColor: newColor
      });
    }
    function textColorSelect(newTextColor) {
      setAttributes({
        textColor: newTextColor
      });
    }
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(PanelBody, {
          title: 'Setting',
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(RangeControl, {
            label: "Posts Per Page",
            value: postsPerPage,
            onChange: updatePostPerPage,
            min: 1,
            max: 20
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
              children: "Select your background color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ColorPalette, {
            value: regionBgColor,
            onChange: regionBackgroundSelect
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
              children: "Select your text color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ColorPalette, {
            value: textColor,
            onChange: textColorSelect
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default()), {
        block: "rch-rechat-plugin/regions-block",
        attributes: attributes
      })]
    });
  },
  save() {
    return null;
  }
});
//offices block

registerBlockType('rch-rechat-plugin/offices-block', {
  title: 'Offices Block',
  description: 'Block for showing Offices',
  icon: 'building',
  category: 'widgets',
  attributes: {
    postsPerPage: {
      type: 'number',
      default: 5
    },
    regionBgColor: {
      type: 'string',
      default: '#edf1f5'
    },
    textColor: {
      type: 'string',
      default: '#000'
    },
    filterByRegions: {
      type: 'string',
      default: ''
    }
  },
  edit({
    attributes,
    setAttributes
  }) {
    const {
      postsPerPage,
      regionBgColor,
      textColor,
      filterByRegions
    } = attributes;
    const [regions, setRegions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]); // State to store fetched regions

    // Fetch the custom post type 'regions'
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/wp/v2/regions?per_page=100'
      }).then(data => {
        const options = data.map(region => ({
          label: region.title.rendered,
          value: region.id
        }));
        options.unshift({
          label: 'None',
          value: ''
        });
        setRegions(options);
      }).catch(error => console.error('Error fetching regions:', error));
    }, []);
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(PanelBody, {
          title: "Settings",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(RangeControl, {
            label: "Posts Per Page",
            value: postsPerPage,
            onChange: value => setAttributes({
              postsPerPage: value
            }),
            min: 1,
            max: 20
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(SelectControl, {
            label: "Select a Region",
            value: filterByRegions,
            options: regions.length ? regions : [{
              label: 'Loading regions...',
              value: ''
            }],
            onChange: selectedRegion => setAttributes({
              filterByRegions: selectedRegion
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
              children: "Select your background color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ColorPalette, {
            value: regionBgColor,
            onChange: color => setAttributes({
              regionBgColor: color
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
              children: "Select your text color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ColorPalette, {
            value: textColor,
            onChange: color => setAttributes({
              textColor: color
            })
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default()), {
        block: "rch-rechat-plugin/offices-block",
        attributes: attributes
      })]
    });
  },
  save() {
    return null; // Dynamic block, content will be rendered by the server
  }
});

// Agents block
registerBlockType('rch-rechat-plugin/agents-block', {
  title: 'Agents Block',
  description: 'Block for showing Agents',
  icon: 'businessperson',
  category: 'widgets',
  attributes: {
    postsPerPage: {
      type: 'number',
      default: 5
    },
    regionBgColor: {
      type: 'string',
      default: '#edf1f5'
    },
    textColor: {
      type: 'string',
      default: '#000'
    },
    filterByRegions: {
      type: 'string',
      default: ''
    },
    filterByOffices: {
      type: 'string',
      default: ''
    },
    sortBy: {
      type: 'string',
      default: 'date'
    },
    sortOrder: {
      type: 'string',
      default: 'desc'
    }
  },
  edit({
    attributes,
    setAttributes
  }) {
    const {
      postsPerPage,
      regionBgColor,
      textColor,
      filterByRegions,
      filterByOffices,
      sortBy,
      sortOrder
    } = attributes;
    const [regions, setRegions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
    const [offices, setOffices] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
    const fetchData = async (endpoint, setState) => {
      try {
        const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
          path: endpoint
        });
        const options = data.map(item => ({
          label: item.title.rendered,
          value: item.id
        }));
        options.unshift({
          label: 'None',
          value: ''
        });
        setState(options);
      } catch (error) {
        console.error('Error fetching data:', error);
      }
    };
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      fetchData('/wp/v2/regions?per_page=100', setRegions);
      fetchData('/wp/v2/offices?per_page=100', setOffices);
    }, []);
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(PanelBody, {
          title: "Settings",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(RangeControl, {
            label: "Posts Per Page",
            value: postsPerPage,
            onChange: value => setAttributes({
              postsPerPage: value
            }),
            min: 1,
            max: 20
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(SelectControl, {
            label: "Select a Region",
            value: filterByRegions,
            options: regions.length ? regions : [{
              label: 'Loading regions...',
              value: ''
            }],
            onChange: selectedRegion => setAttributes({
              filterByRegions: selectedRegion
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(SelectControl, {
            label: "Select an Office",
            value: filterByOffices,
            options: offices.length ? offices : [{
              label: 'Loading offices...',
              value: ''
            }],
            onChange: selectedOffice => setAttributes({
              filterByOffices: selectedOffice
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(SelectControl, {
            label: "Sort By",
            value: sortBy,
            options: [{
              label: 'Date',
              value: 'date'
            }, {
              label: 'Name',
              value: 'name'
            }],
            onChange: selectedSort => setAttributes({
              sortBy: selectedSort
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(SelectControl, {
            label: "Sort Order",
            value: sortOrder,
            options: [{
              label: 'Ascending',
              value: 'asc'
            }, {
              label: 'Descending',
              value: 'desc'
            }],
            onChange: selectedOrder => setAttributes({
              sortOrder: selectedOrder
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
              children: "Select your background color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ColorPalette, {
            value: regionBgColor,
            onChange: color => setAttributes({
              regionBgColor: color
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
              children: "Select your text color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ColorPalette, {
            value: textColor,
            onChange: color => setAttributes({
              textColor: color
            })
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default()), {
        block: "rch-rechat-plugin/agents-block",
        attributes: attributes
      })]
    });
  },
  save() {
    return null; // Dynamic block, content will be generated by PHP
  }
});
registerBlockType('rch-rechat-plugin/listing-block', {
  title: 'Listing Block',
  description: 'Block for showing property listings',
  icon: 'building',
  category: 'widgets',
  attributes: {
    minimum_price: {
      type: 'string',
      default: ''
    },
    maximum_price: {
      type: 'string',
      default: ''
    },
    minimum_lot_square_meters: {
      type: 'string',
      default: ''
    },
    maximum_lot_square_meters: {
      type: 'string',
      default: ''
    },
    minimum_bathrooms: {
      type: 'string',
      default: ''
    },
    maximum_bathrooms: {
      type: 'string',
      default: ''
    },
    minimum_square_meters: {
      type: 'string',
      default: ''
    },
    maximum_square_meters: {
      type: 'string',
      default: ''
    },
    minimum_year_built: {
      type: 'string',
      default: ''
    },
    maximum_year_built: {
      type: 'string',
      default: ''
    },
    minimum_bedrooms: {
      type: 'string',
      default: ''
    },
    maximum_bedrooms: {
      type: 'string',
      default: ''
    },
    listing_per_page: {
      type: 'string',
      default: '5'
    },
    filterByRegions: {
      type: 'string',
      default: ''
    },
    filterByOffices: {
      type: 'string',
      default: ''
    },
    selectedStatuses: {
      type: 'array',
      default: []
    },
    listing_statuses: {
      type: 'array',
      default: []
    },
    show_filter_bar: {
      type: 'boolean',
      default: true
    },
    // New attribute for showing the filter bar
    own_listing: {
      type: 'boolean',
      default: true
    },
    // New attribute for showing the filter bar
    property_types: {
      type: 'string',
      default: ''
    }
  },
  edit({
    attributes,
    setAttributes
  }) {
    const {
      minimum_price,
      maximum_price,
      minimum_lot_square_meters,
      maximum_lot_square_meters,
      minimum_bathrooms,
      maximum_bathrooms,
      minimum_square_meters,
      maximum_square_meters,
      minimum_year_built,
      maximum_year_built,
      minimum_bedrooms,
      maximum_bedrooms,
      listing_per_page,
      filterByRegions,
      filterByOffices,
      selectedStatuses,
      show_filter_bar,
      own_listing,
      property_types,
      listing_statuses
    } = attributes;
    const [regions, setRegions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
    const [offices, setOffices] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
    const statusOptions = [{
      label: 'Active',
      value: 'Active'
    }, {
      label: 'Closed',
      value: 'Closed'
    }, {
      label: 'Archived',
      value: 'Archived'
    }];
    const fetchData = async (path, setState) => {
      try {
        const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
          path
        });
        setState([{
          label: 'None',
          value: ''
        }, ...data.map(item => ({
          label: item.title.rendered,
          value: item.meta.region_id || item.meta.office_id
        }))]);
      } catch (error) {
        console.error('Error fetching data:', error);
      }
    };
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      fetchData('/wp/v2/regions?per_page=100', setRegions);
      fetchData('/wp/v2/offices?per_page=100', setOffices);
    }, []);
    const handleAttributeChange = (attr, value) => {
      setAttributes({
        [attr]: value
      });
    };
    const handleStatusChange = status => {
      const updatedStatuses = selectedStatuses.includes(status) ? selectedStatuses.filter(s => s !== status) : [...selectedStatuses, status];
      const listingStatuses = updatedStatuses.flatMap(status => ({
        Active: ['Active', 'Incoming', 'Coming Soon', 'Pending'],
        Closed: ['Sold', 'Leased'],
        Archived: ['Withdrawn', 'Expired']
      })[status] || []);
      setAttributes({
        selectedStatuses: updatedStatuses,
        listing_statuses: listingStatuses
      });
    };
    const handlePropertyTypeChange = value => {
      setAttributes({
        property_types: value
      });
    };
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(PanelBody, {
          title: "Listing Settings",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(CheckboxControl, {
            label: "Show Filter Bar",
            checked: show_filter_bar,
            onChange: () => setAttributes({
              show_filter_bar: !show_filter_bar
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(CheckboxControl, {
            label: "Only our own listings",
            checked: own_listing,
            onChange: () => setAttributes({
              own_listing: !own_listing
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(SelectControl, {
            label: "Select a Region",
            value: filterByRegions,
            options: regions,
            onChange: value => handleAttributeChange('filterByRegions', value)
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(SelectControl, {
            label: "Select an Office",
            value: filterByOffices,
            options: offices,
            onChange: value => handleAttributeChange('filterByOffices', value)
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
              children: "Select Statuses"
            })
          }), statusOptions.map(option => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(CheckboxControl, {
            label: option.label,
            checked: selectedStatuses.includes(option.value),
            onChange: () => handleStatusChange(option.value)
          }, option.value)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
              children: "Property Type"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(RadioControl, {
            label: "Select Property Type",
            selected: property_types,
            options: [{
              label: 'All Listings',
              value: 'Residential, Residential Lease, Lots & Acreage, Commercial, Multi-Family'
            }, {
              label: 'Sale',
              value: 'Residential'
            }, {
              label: 'Lease',
              value: 'Residential Lease'
            }, {
              label: 'Lots & Acreage',
              value: 'Lots & Acreage'
            }, {
              label: 'Commercial',
              value: 'Commercial'
            }],
            onChange: handlePropertyTypeChange
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Minimum Price",
            value: minimum_price,
            type: "number",
            onChange: value => setAttributes({
              minimum_price: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Maximum Price",
            value: maximum_price,
            type: "number",
            onChange: value => setAttributes({
              maximum_price: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Minimum Lot Size (m\xB2)",
            value: minimum_lot_square_meters,
            type: "number",
            onChange: value => setAttributes({
              minimum_lot_square_meters: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Maximum Lot Size (m\xB2)",
            value: maximum_lot_square_meters,
            type: "number",
            onChange: value => setAttributes({
              maximum_lot_square_meters: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Minimum Bathrooms",
            value: minimum_bathrooms,
            type: "number",
            onChange: value => setAttributes({
              minimum_bathrooms: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Maximum Bathrooms",
            value: maximum_bathrooms,
            type: "number",
            onChange: value => setAttributes({
              maximum_bathrooms: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Minimum Square Meters",
            value: minimum_square_meters,
            type: "number",
            onChange: value => setAttributes({
              minimum_square_meters: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Maximum Square Meters",
            value: maximum_square_meters,
            type: "number",
            onChange: value => setAttributes({
              maximum_square_meters: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Minimum Year Built",
            value: minimum_year_built,
            type: "number",
            onChange: value => setAttributes({
              minimum_year_built: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Maximum Year Built",
            value: maximum_year_built,
            type: "number",
            onChange: value => setAttributes({
              maximum_year_built: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Minimum Bedrooms",
            value: minimum_bedrooms,
            type: "number",
            onChange: value => setAttributes({
              minimum_bedrooms: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Maximum Bedrooms",
            value: maximum_bedrooms,
            type: "number",
            onChange: value => setAttributes({
              maximum_bedrooms: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Listing Per Page",
            value: listing_per_page,
            type: "number",
            onChange: value => setAttributes({
              listing_per_page: value === '' ? '' : value.toString()
            })
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_listing_main__WEBPACK_IMPORTED_MODULE_3__["default"], {
        listing_per_page: listing_per_page,
        maximum_bedrooms: maximum_bedrooms,
        minimum_bedrooms: minimum_bedrooms,
        maximum_year_built: maximum_year_built,
        minimum_year_built: minimum_year_built,
        maximum_square_meters: maximum_square_meters,
        minimum_square_meters: minimum_square_meters,
        maximum_bathrooms: maximum_bathrooms,
        minimum_bathrooms: minimum_bathrooms,
        maximum_lot_square_meters: maximum_lot_square_meters,
        minimum_lot_square_meters: minimum_lot_square_meters,
        maximum_price: maximum_price,
        minimum_price: minimum_price,
        property_types: property_types,
        own_listing: own_listing,
        show_filter_bar: show_filter_bar,
        selectedStatuses: listing_statuses
      })]
    });
  },
  save() {
    return null; // Dynamic block, content will be generated by PHP
  }
});

//register contact lead channel block
registerBlockType('rch-rechat-plugin/leads-form-block', {
  title: 'Leads Form Block',
  description: 'Block for lead form submission',
  icon: 'admin-users',
  category: 'widgets',
  attributes: {
    formTitle: {
      type: 'string',
      default: 'Lead Form'
    },
    // New attribute for form title
    leadChannel: {
      type: 'string',
      default: ''
    },
    showFirstName: {
      type: 'boolean',
      default: true
    },
    showLastName: {
      type: 'boolean',
      default: true
    },
    showPhoneNumber: {
      type: 'boolean',
      default: true
    },
    showEmail: {
      type: 'boolean',
      default: true
    },
    showNote: {
      type: 'boolean',
      default: true
    },
    selectedTagsFrom: {
      type: 'array',
      default: []
    },
    // Array to hold selected tags
    emailForGetLead: {
      type: 'string',
      default: ''
    }
  },
  edit({
    attributes,
    setAttributes
  }) {
    const {
      formTitle,
      leadChannel,
      showFirstName,
      showLastName,
      showPhoneNumber,
      showEmail,
      showNote,
      selectedTagsFrom,
      emailForGetLead
    } = attributes;
    const [leadChannels, setLeadChannels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)();
    const [tags, setTags] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
    const [loadingChannels, setLoadingChannels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
    const [loadingTags, setLoadingTags] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
    const [isLoggedIn, setIsLoggedIn] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
    const [brandId, setBrandId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
    const [accessToken, setAccessToken] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      const checkUserLogin = async () => {
        try {
          const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
            path: '/wp/v2/users/me'
          });
          if (response && response.id) {
            setIsLoggedIn(true);
            fetchBrandId();
            fetchAccessToken();
          } else {
            setIsLoggedIn(false);
          }
        } catch (error) {
          setIsLoggedIn(false);
          console.error('Error checking user login:', error);
        }
      };
      checkUserLogin();
    }, []);
    const fetchBrandId = async () => {
      try {
        const brandResponse = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
          path: '/wp/v2/options'
        });
        if (brandResponse.rch_rechat_brand_id) {
          setBrandId(brandResponse.rch_rechat_brand_id);
        } else {
          console.error('Brand ID not found in WordPress options.');
        }
      } catch (error) {
        console.error('Error fetching brand ID:', error);
      }
    };
    const fetchAccessToken = async () => {
      try {
        const tokenResponse = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
          path: '/wp/v2/options'
        });
        if (tokenResponse.rch_rechat_access_token) {
          setAccessToken(tokenResponse.rch_rechat_access_token);
        } else {
          console.error('Access token not found in WordPress options.');
        }
      } catch (error) {
        console.error('Error fetching access token:', error);
      }
    };
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      if (isLoggedIn && brandId && accessToken) {
        const fetchLeadChannels = async () => {
          try {
            const channelResponse = await fetch(`https://api.rechat.com/brands/${brandId}/leads/channels`, {
              method: 'GET',
              headers: {
                'Authorization': `Bearer ${accessToken}`
              }
            });
            const channelData = await channelResponse.json();
            const options = channelData.data.map(channel => ({
              label: channel.title ? channel.title : 'Unnamed',
              value: channel.id
            }));
            // Add "Select your channel" option
            options.unshift({
              label: 'Select your channel',
              value: '' // Empty value to represent "nothing selected"
            });
            setLeadChannels(options);
          } catch (error) {
            console.error('Error fetching lead channels:', error);
          } finally {
            setLoadingChannels(false);
          }
        };
        fetchLeadChannels();

        // Fetch tags from the API
        const fetchTags = async () => {
          try {
            const tagsResponse = await fetch('https://api.rechat.com/contacts/tags', {
              method: 'GET',
              headers: {
                'Authorization': `Bearer ${accessToken}`,
                'X-RECHAT-BRAND': brandId
              }
            });
            const tagsData = await tagsResponse.json();
            const tagOptions = tagsData.data.map(tag => ({
              label: tag.tag,
              value: tag.tag
            }));
            setTags(tagOptions);
          } catch (error) {
            console.error('Error fetching tags:', error);
          } finally {
            setLoadingTags(false);
          }
        };
        fetchTags();
      }
    }, [isLoggedIn, brandId, accessToken]);
    if (isLoggedIn === false) {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        children: "Please log in to view and manage the lead channels and tags."
      });
    }
    if (isLoggedIn === null) {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        children: "Loading..."
      });
    }
    const handleTagChange = tagId => {
      const newSelectedTagsFrom = selectedTagsFrom.includes(tagId) ? selectedTagsFrom.filter(id => id !== tagId) : [...selectedTagsFrom, tagId];
      setAttributes({
        selectedTagsFrom: newSelectedTagsFrom
      });
    };
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(PanelBody, {
          title: "Lead Form Settings",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Form Title",
            value: formTitle,
            onChange: value => setAttributes({
              formTitle: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(SelectControl, {
            label: "Lead Channel",
            value: leadChannel,
            options: loadingChannels ? [{
              label: 'Loading channels...',
              value: ''
            }] : leadChannels,
            onChange: selectedChannel => setAttributes({
              leadChannel: selectedChannel
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(TextControl, {
            label: "Email for Get This Lead In you Inbox",
            value: emailForGetLead,
            placeholder: "Enter the email to receive leads",
            onChange: value => setAttributes({
              emailForGetLead: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ToggleControl, {
            label: "Show First Name Field",
            checked: showFirstName,
            onChange: value => setAttributes({
              showFirstName: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ToggleControl, {
            label: "Show Last Name Field",
            checked: showLastName,
            onChange: value => setAttributes({
              showLastName: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ToggleControl, {
            label: "Show Phone Number Field",
            checked: showPhoneNumber,
            onChange: value => setAttributes({
              showPhoneNumber: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ToggleControl, {
            label: "Show Email Field",
            checked: showEmail,
            onChange: value => setAttributes({
              showEmail: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ToggleControl, {
            label: "Show Note Field",
            checked: showNote,
            onChange: value => setAttributes({
              showNote: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            style: {
              maxHeight: '200px',
              overflowY: 'auto'
            },
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("fieldset", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("legend", {
                children: "Tags"
              }), loadingTags ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
                children: "Loading tags..."
              }) : tags.map(tag => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
                style: {
                  marginBottom: '8px'
                },
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("label", {
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("input", {
                    type: "checkbox",
                    value: tag.value,
                    checked: selectedTagsFrom.includes(tag.value),
                    onChange: () => handleTagChange(tag.value)
                  }), tag.label]
                })
              }, tag.value))]
            })
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default()), {
        block: "rch-rechat-plugin/leads-form-block",
        attributes: attributes
      })]
    });
  },
  save() {
    return null; // Server-rendered block
  }
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map