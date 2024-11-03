/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

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
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
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
  TextControl
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
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(PanelBody, {
          title: 'Setting',
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(RangeControl, {
            label: "Posts Per Page",
            value: postsPerPage,
            onChange: updatePostPerPage,
            min: 1,
            max: 20
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
              children: "Select your background color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(ColorPalette, {
            value: regionBgColor,
            onChange: regionBackgroundSelect
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
              children: "Select your text color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(ColorPalette, {
            value: textColor,
            onChange: textColorSelect
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default()), {
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
      // Dynamically get the base URL including the current subdirectory
      const baseUrl = `${window.location.origin}`;
      const apiUrl = `${baseUrl}/wp-json/wp/v2/regions?per_page=100`;
      fetch(apiUrl).then(response => response.json()).then(data => {
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
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(PanelBody, {
          title: 'Settings',
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(RangeControl, {
            label: "Posts Per Page",
            value: postsPerPage,
            onChange: value => setAttributes({
              postsPerPage: value
            }),
            min: 1,
            max: 20
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
              children: "Select aregion for filter"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(SelectControl, {
            label: "Select a Region",
            value: filterByRegions,
            options: regions.length ? regions : [{
              label: 'Loading regions...',
              value: ''
            }],
            onChange: selectedRegion => setAttributes({
              filterByRegions: selectedRegion
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
              children: "Select your background color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(ColorPalette, {
            value: regionBgColor,
            onChange: color => setAttributes({
              regionBgColor: color
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
              children: "Select your text color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(ColorPalette, {
            value: textColor,
            onChange: color => setAttributes({
              textColor: color
            })
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default()), {
        block: "rch-rechat-plugin/offices-block",
        attributes: attributes
      })]
    });
  },
  save() {
    return null;
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
      default: 'date' // Default sort by date
    },
    sortOrder: {
      type: 'string',
      default: 'desc' // Default sort order
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
    const [regions, setRegions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]); // State to store fetched regions
    const [offices, setOffices] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]); // State to store fetched offices

    // Fetch the custom post type 'regions'
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      const baseUrl = `${window.location.origin}`;
      const apiUrl = `${baseUrl}/wp-json/wp/v2/regions?per_page=100`;
      fetch(apiUrl).then(response => response.json()).then(data => {
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

    // Fetch the custom post type 'offices'
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      const baseUrl = `${window.location.origin}`;
      const apiUrl = `${baseUrl}/wp-json/wp/v2/offices?per_page=100`;
      fetch(apiUrl).then(response => response.json()).then(data => {
        const options = data.map(office => ({
          label: office.title.rendered,
          value: office.id
        }));
        options.unshift({
          label: 'None',
          value: ''
        });
        setOffices(options);
      }).catch(error => console.error('Error fetching offices:', error));
    }, []);
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(PanelBody, {
          title: 'Settings',
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(RangeControl, {
            label: "Posts Per Page",
            value: postsPerPage,
            onChange: value => setAttributes({
              postsPerPage: value
            }),
            min: 1,
            max: 20
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
              children: "Select a Region for filter"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(SelectControl, {
            label: "Select a Region",
            value: filterByRegions,
            options: regions.length ? regions : [{
              label: 'Loading regions...',
              value: ''
            }],
            onChange: selectedRegion => setAttributes({
              filterByRegions: selectedRegion
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
              children: "Select an Office for filter"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(SelectControl, {
            label: "Select an Office",
            value: filterByOffices,
            options: offices.length ? offices : [{
              label: 'Loading offices...',
              value: ''
            }],
            onChange: selectedOffice => setAttributes({
              filterByOffices: selectedOffice
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(SelectControl, {
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
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(SelectControl, {
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
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
              children: "Select your background color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(ColorPalette, {
            value: regionBgColor,
            onChange: color => setAttributes({
              regionBgColor: color
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
              children: "Select your text color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(ColorPalette, {
            value: textColor,
            onChange: color => setAttributes({
              textColor: color
            })
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default()), {
        block: "rch-rechat-plugin/agents-block",
        attributes: attributes
      })]
    });
  },
  save() {
    return null;
  }
});
registerBlockType('rch-rechat-plugin/listing-block', {
  title: 'Listing Block',
  description: 'Block for showing property listings',
  icon: 'building',
  // You can change the icon to something related to listings.
  category: 'widgets',
  attributes: {
    minimum_price: {
      type: 'number',
      default: 0
    },
    maximum_price: {
      type: 'number',
      default: 0
    },
    minimum_lot_square_meters: {
      type: 'number',
      default: 0
    },
    maximum_lot_square_meters: {
      type: 'number',
      default: 0
    },
    minimum_bathrooms: {
      type: 'number',
      default: 0
    },
    maximum_bathrooms: {
      type: 'number',
      default: 0
    },
    minimum_square_meters: {
      type: 'number',
      default: 0
    },
    maximum_square_meters: {
      type: 'number',
      default: 0
    },
    minimum_year_built: {
      type: 'number',
      default: 0
    },
    maximum_year_built: {
      type: 'number',
      default: 0
    },
    minimum_bedrooms: {
      type: 'number',
      default: 0
    },
    maximum_bedrooms: {
      type: 'number',
      default: 0
    },
    listing_per_page: {
      type: 'number',
      default: 5
    },
    filterByRegions: {
      type: 'string',
      default: ''
    },
    filterByOffices: {
      type: 'string',
      default: ''
    },
    brand: {
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
      brand
    } = attributes;
    // React state for holding regions and offices data
    const [regions, setRegions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
    const [offices, setOffices] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);

    // Fetch Regions on component mount
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      const baseUrl = `${window.location.origin}`;
      const apiUrl = `${baseUrl}/wp-json/wp/v2/regions?per_page=100`;
      fetch(apiUrl).then(response => response.json()).then(data => {
        const options = data.map(region => ({
          label: region.title.rendered,
          value: region.meta.region_id
        }));
        options.unshift({
          label: 'None',
          value: ''
        }); // Add "None" option
        setRegions(options);
      }).catch(error => console.error('Error fetching regions:', error));
    }, []);

    // Fetch Offices on component mount
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      const baseUrl = `${window.location.origin}`;
      const apiUrl = `${baseUrl}/wp-json/wp/v2/offices?per_page=100`;
      fetch(apiUrl).then(response => response.json()).then(data => {
        const options = data.map(office => ({
          label: office.title.rendered,
          value: office.meta.office_id
        }));
        options.unshift({
          label: 'None',
          value: ''
        }); // Add "None" option
        setOffices(options);
      }).catch(error => console.error('Error fetching offices:', error));
    }, []);

    // Handle Region selection
    const handleRegionChange = selectedRegion => {
      setAttributes({
        ...attributes,
        filterByRegions: selectedRegion,
        filterByOffices: '',
        // Clear office selection
        brand: selectedRegion || '' // Set brand to region ID or default to empty
      });
    };
    // Handle Office selection
    const handleOfficeChange = selectedOffice => {
      setAttributes({
        ...attributes,
        filterByOffices: selectedOffice,
        filterByRegions: '',
        // Clear region selection
        brand: selectedOffice || '' // Set brand to office ID or default to empty
      });
    };
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(PanelBody, {
          title: 'Listing Settings',
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
              children: "Select a Regions for filter"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(SelectControl, {
            label: "Select a Region",
            value: filterByRegions,
            options: regions,
            onChange: handleRegionChange
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
              children: "Select an Office for filter"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(SelectControl, {
            label: "Select an Office",
            value: filterByOffices,
            options: offices,
            onChange: handleOfficeChange
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Minimum Price",
            value: minimum_price,
            type: "number",
            onChange: value => setAttributes({
              minimum_price: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Maximum Price",
            value: maximum_price,
            type: "number",
            onChange: value => setAttributes({
              maximum_price: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Minimum Lot Size (m\xB2)",
            value: minimum_lot_square_meters,
            type: "number",
            onChange: value => setAttributes({
              minimum_lot_square_meters: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Maximum Lot Size (m\xB2)",
            value: maximum_lot_square_meters,
            type: "number",
            onChange: value => setAttributes({
              maximum_lot_square_meters: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Minimum Bathrooms",
            value: minimum_bathrooms,
            type: "number",
            onChange: value => setAttributes({
              minimum_bathrooms: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Maximum Bathrooms",
            value: maximum_bathrooms,
            type: "number",
            onChange: value => setAttributes({
              maximum_bathrooms: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Minimum Square Meters",
            value: minimum_square_meters,
            type: "number",
            onChange: value => setAttributes({
              minimum_square_meters: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Maximum Square Meters",
            value: maximum_square_meters,
            type: "number",
            onChange: value => setAttributes({
              maximum_square_meters: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Minimum Year Built",
            value: minimum_year_built,
            type: "number",
            onChange: value => setAttributes({
              minimum_year_built: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Maximum Year Built",
            value: maximum_year_built,
            type: "number",
            onChange: value => setAttributes({
              maximum_year_built: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Minimum Bedrooms",
            value: minimum_bedrooms,
            type: "number",
            onChange: value => setAttributes({
              minimum_bedrooms: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "Maximum Bedrooms",
            value: maximum_bedrooms,
            type: "number",
            onChange: value => setAttributes({
              maximum_bedrooms: value === '' ? '' : parseInt(value) || 0
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(TextControl, {
            label: "listing Per Page",
            value: listing_per_page,
            type: "number",
            onChange: value => setAttributes({
              listing_per_page: value === '' ? '' : parseInt(value) || 1
            })
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
        className: "listing-block-preview",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
            children: "Listing Block:"
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
          children: "We display listing items based on your selected filters on the front end of the site."
        })]
      })]
    });
  },
  save() {
    return null; // Dynamic block, content will be generated by PHP
  }
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map