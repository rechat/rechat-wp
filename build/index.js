/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/blocks/agents-block.js":
/*!************************************!*\
  !*** ./src/blocks/agents-block.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/server-side-render */ "@wordpress/server-side-render");
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _utils_api_helpers__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../utils/api-helpers */ "./src/utils/api-helpers.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
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
  SelectControl
} = wp.components;




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
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      (0,_utils_api_helpers__WEBPACK_IMPORTED_MODULE_2__.fetchData)('/wp/v2/regions?per_page=100', setRegions);
      (0,_utils_api_helpers__WEBPACK_IMPORTED_MODULE_2__.fetchData)('/wp/v2/offices?per_page=100', setOffices);
    }, []);
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(PanelBody, {
          title: "Settings",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(RangeControl, {
            label: "Posts Per Page",
            value: postsPerPage,
            onChange: value => setAttributes({
              postsPerPage: value
            }),
            min: 1,
            max: 20
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(SelectControl, {
            label: "Select a Region",
            value: filterByRegions,
            options: regions.length ? regions : [{
              label: 'Loading regions...',
              value: ''
            }],
            onChange: selectedRegion => setAttributes({
              filterByRegions: selectedRegion
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(SelectControl, {
            label: "Select an Office",
            value: filterByOffices,
            options: offices.length ? offices : [{
              label: 'Loading offices...',
              value: ''
            }],
            onChange: selectedOffice => setAttributes({
              filterByOffices: selectedOffice
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(SelectControl, {
            label: "Sort By",
            value: sortBy,
            options: [{
              label: 'Date',
              value: 'date'
            }, {
              label: 'Name',
              value: 'name'
            }, {
              label: 'Display order',
              value: 'display_order'
            }],
            onChange: selectedSort => setAttributes({
              sortBy: selectedSort
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(SelectControl, {
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
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("strong", {
              children: "Select your background color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(ColorPalette, {
            value: regionBgColor,
            onChange: color => setAttributes({
              regionBgColor: color
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("strong", {
              children: "Select your text color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(ColorPalette, {
            value: textColor,
            onChange: color => setAttributes({
              textColor: color
            })
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default()), {
        block: "rch-rechat-plugin/agents-block",
        attributes: attributes
      })]
    });
  },
  save() {
    return null;
  }
});

/***/ }),

/***/ "./src/blocks/leads-form-block.js":
/*!****************************************!*\
  !*** ./src/blocks/leads-form-block.js ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/server-side-render */ "@wordpress/server-side-render");
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
const {
  registerBlockType
} = wp.blocks;
const {
  InspectorControls
} = wp.blockEditor || wp.editor;
const {
  PanelBody,
  SelectControl,
  TextControl,
  ToggleControl
} = wp.components;




/** Keep in sync with PHP `RECHAT_API_BASE_URL` in the main plugin file. */

const RECHAT_API_BASE_URL = 'https://api.rechat.com';

/** Always offered next to API tags; selectable like other tags. */
const STATIC_MORTGAGE_QUESTIONNAIRE_TAG = 'Mortgage Questionnaire';
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
    leadChannel: {
      type: 'string',
      default: ''
    },
    assigneeAgentEmail: {
      type: 'string',
      default: ''
    },
    useMortgageQuestionLeadSource: {
      type: 'boolean',
      default: true
    },
    leadSource: {
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
    submitButtonText: {
      type: 'string',
      default: 'Submit Request'
    }
  },
  edit({
    attributes,
    setAttributes
  }) {
    const {
      formTitle,
      leadChannel,
      assigneeAgentEmail,
      useMortgageQuestionLeadSource,
      leadSource,
      showFirstName,
      showLastName,
      showPhoneNumber,
      showEmail,
      showNote,
      selectedTagsFrom,
      submitButtonText
    } = attributes;
    const [leadChannels, setLeadChannels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)();
    const [tags, setTags] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
    const [agentOptions, setAgentOptions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([{
      label: 'Loading agents…',
      value: ''
    }]);
    const [loadingChannels, setLoadingChannels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
    const [loadingTags, setLoadingTags] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
    const [loadingAgents, setLoadingAgents] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
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
      if (isLoggedIn !== true) {
        return;
      }
      let cancelled = false;
      const loadAgents = async () => {
        setLoadingAgents(true);
        try {
          const res = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
            path: '/rch/v1/leads-form-agents'
          });
          const agents = Array.isArray(res?.agents) ? res.agents : [];
          const opts = [{
            label: 'Select agent to receive this lead',
            value: ''
          }, ...agents.map(a => ({
            label: a.name && a.email ? `${a.name} (${a.email})` : a.email || a.name || 'Agent',
            value: a.email || ''
          }))];
          if (!cancelled) {
            setAgentOptions(opts);
          }
        } catch (e) {
          console.error('Error loading agents for lead form:', e);
          if (!cancelled) {
            setAgentOptions([{
              label: 'Could not load agents (check agent posts have email meta)',
              value: ''
            }]);
          }
        } finally {
          if (!cancelled) {
            setLoadingAgents(false);
          }
        }
      };
      loadAgents();
      return () => {
        cancelled = true;
      };
    }, [isLoggedIn]);
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      if (isLoggedIn && brandId && accessToken) {
        const fetchLeadChannels = async () => {
          try {
            const channelResponse = await fetch(`${RECHAT_API_BASE_URL}/brands/${brandId}/leads/channels`, {
              method: 'GET',
              headers: {
                Authorization: `Bearer ${accessToken}`
              }
            });
            const channelData = await channelResponse.json();
            const options = channelData.data.map(channel => ({
              label: channel.title ? channel.title : 'Unnamed',
              value: channel.id
            }));
            options.unshift({
              label: 'Select your channel',
              value: ''
            });
            setLeadChannels(options);
          } catch (error) {
            console.error('Error fetching lead channels:', error);
          } finally {
            setLoadingChannels(false);
          }
        };
        fetchLeadChannels();
        const fetchTags = async () => {
          try {
            const tagsResponse = await fetch(`${RECHAT_API_BASE_URL}/contacts/tags`, {
              method: 'GET',
              headers: {
                Authorization: `Bearer ${accessToken}`,
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
    const tagsForCheckboxes = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
      const list = Array.isArray(tags) ? [...tags] : [];
      const seen = new Set(list.map(t => t.value));
      if (!seen.has(STATIC_MORTGAGE_QUESTIONNAIRE_TAG)) {
        list.push({
          label: STATIC_MORTGAGE_QUESTIONNAIRE_TAG,
          value: STATIC_MORTGAGE_QUESTIONNAIRE_TAG
        });
      }
      return list;
    }, [tags]);
    const handleTagChange = tagId => {
      const newSelectedTagsFrom = selectedTagsFrom.includes(tagId) ? selectedTagsFrom.filter(id => id !== tagId) : [...selectedTagsFrom, tagId];
      setAttributes({
        selectedTagsFrom: newSelectedTagsFrom
      });
    };
    if (isLoggedIn === false) {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
        children: "Please log in to view and manage the lead channels and tags."
      });
    }
    if (isLoggedIn === null) {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
        children: "Loading..."
      });
    }
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(PanelBody, {
          title: "Lead Form Settings",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(TextControl, {
            label: "Form Title",
            value: formTitle,
            onChange: value => setAttributes({
              formTitle: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(TextControl, {
            label: "Submit button text",
            value: submitButtonText,
            onChange: value => setAttributes({
              submitButtonText: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(SelectControl, {
            label: "Lead Channel",
            value: leadChannel,
            options: loadingChannels ? [{
              label: 'Loading channels...',
              value: ''
            }] : leadChannels,
            onChange: selectedChannel => setAttributes({
              leadChannel: selectedChannel
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(SelectControl, {
            label: "Assignee agent (from Agents CPT)",
            value: assigneeAgentEmail,
            options: loadingAgents ? [{
              label: 'Loading agents…',
              value: ''
            }] : agentOptions,
            onChange: value => setAttributes({
              assigneeAgentEmail: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
            style: {
              marginTop: '-8px',
              fontSize: '12px',
              color: '#757575'
            },
            children: "Uses each agent post\u2019s email meta. Only agents with a valid email appear."
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(ToggleControl, {
            label: "Send lead_source as \"Mortgage Question From\"",
            checked: useMortgageQuestionLeadSource,
            onChange: value => setAttributes({
              useMortgageQuestionLeadSource: value
            })
          }), !useMortgageQuestionLeadSource && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(TextControl, {
            label: "Custom lead source",
            value: leadSource,
            onChange: value => setAttributes({
              leadSource: value
            }),
            placeholder: "e.g. Contact page"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(ToggleControl, {
            label: "Show First Name Field",
            checked: showFirstName,
            onChange: value => setAttributes({
              showFirstName: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(ToggleControl, {
            label: "Show Last Name Field",
            checked: showLastName,
            onChange: value => setAttributes({
              showLastName: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(ToggleControl, {
            label: "Show Phone Number Field",
            checked: showPhoneNumber,
            onChange: value => setAttributes({
              showPhoneNumber: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(ToggleControl, {
            label: "Show Email Field",
            checked: showEmail,
            onChange: value => setAttributes({
              showEmail: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(ToggleControl, {
            label: "Show Note Field",
            checked: showNote,
            onChange: value => setAttributes({
              showNote: value
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
            style: {
              maxHeight: '200px',
              overflowY: 'auto'
            },
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("fieldset", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("legend", {
                children: "Tags"
              }), loadingTags ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
                children: "Loading tags..."
              }) : tagsForCheckboxes.map(tag => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
                style: {
                  marginBottom: '8px'
                },
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("label", {
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                    type: "checkbox",
                    value: tag.value,
                    checked: selectedTagsFrom.includes(tag.value),
                    onChange: () => handleTagChange(tag.value)
                  }), tag.label, tag.value === STATIC_MORTGAGE_QUESTIONNAIRE_TAG ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
                    style: {
                      color: '#757575',
                      fontSize: '11px',
                      marginLeft: '6px'
                    },
                    children: "(fixed option)"
                  }) : null]
                })
              }, tag.value))]
            })
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default()), {
        block: "rch-rechat-plugin/leads-form-block",
        attributes: attributes
      })]
    });
  },
  save() {
    return null;
  }
});

/***/ }),

/***/ "./src/blocks/listing-block.js":
/*!*************************************!*\
  !*** ./src/blocks/listing-block.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/server-side-render */ "@wordpress/server-side-render");
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _utils_map_selector__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../utils/map-selector */ "./src/utils/map-selector.js");
/* harmony import */ var _utils_api_helpers__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../utils/api-helpers */ "./src/utils/api-helpers.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
const {
  registerBlockType
} = wp.blocks;
const {
  InspectorControls,
  MediaUpload,
  MediaUploadCheck
} = wp.blockEditor || wp.editor;
const {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  CheckboxControl,
  RadioControl,
  Button,
  Spinner
} = wp.components;






/**
 * `/rch/v1/boundary-countries` and `/rch/v1/boundary-states` return `{ options: [{ value, label }] }`
 * already normalized in PHP (`rch_rechat_normalize_boundary_options`). Only validate for SelectControl.
 *
 * @param {unknown} res REST JSON body
 * @returns {{ label: string, value: string }[]}
 */

function parseBoundaryRestOptions(res) {
  if (!res || typeof res !== 'object') {
    return [];
  }
  const options = /** @type {Record<string, unknown>} */res.options;
  if (!Array.isArray(options)) {
    return [];
  }
  const out = [];
  for (const row of options) {
    if (!row || typeof row !== 'object') {
      continue;
    }
    const o = /** @type {Record<string, unknown>} */row;
    const label = o.label != null ? String(o.label).trim() : '';
    const value = o.value != null ? String(o.value).trim() : '';
    if (label !== '' && value !== '') {
      out.push({
        label,
        value
      });
    }
  }
  return out;
}
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
    minimum_square_feet: {
      type: 'string',
      default: ''
    },
    maximum_square_feet: {
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
    minimum_lot_square_feet: {
      type: 'string',
      default: ''
    },
    maximum_lot_square_feet: {
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
      default: ''
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
    disable_filter_address: {
      type: 'boolean',
      default: false
    },
    disable_filter_price: {
      type: 'boolean',
      default: false
    },
    disable_filter_beds: {
      type: 'boolean',
      default: false
    },
    disable_filter_baths: {
      type: 'boolean',
      default: false
    },
    disable_filter_property_types: {
      type: 'boolean',
      default: false
    },
    disable_filter_advanced: {
      type: 'boolean',
      default: false
    },
    own_listing: {
      type: 'boolean',
      default: true
    },
    property_types: {
      type: 'string',
      default: ''
    },
    filter_open_houses: {
      type: 'boolean',
      default: false
    },
    office_exclusive: {
      type: 'boolean',
      default: false
    },
    disable_sort: {
      type: 'boolean',
      default: false
    },
    map_latitude: {
      type: 'string',
      default: ''
    },
    map_longitude: {
      type: 'string',
      default: ''
    },
    map_zoom: {
      type: 'string',
      default: '12'
    },
    map_style: {
      type: 'string',
      default: ''
    },
    map_style_url: {
      type: 'string',
      default: ''
    },
    map_id: {
      type: 'string',
      default: ''
    },
    sort_by: {
      type: 'string',
      default: '-list_date'
    },
    filter_address: {
      type: 'string',
      default: ''
    },
    filter_search_limit: {
      type: 'string',
      default: ''
    },
    filter_suggestions_limit: {
      type: 'string',
      default: ''
    },
    filter_pagination_offset: {
      type: 'string',
      default: ''
    },
    property_subtypes: {
      type: 'string',
      default: ''
    },
    architectural_styles: {
      type: 'string',
      default: ''
    },
    filter_baths: {
      type: 'string',
      default: ''
    },
    minimum_parking_spaces: {
      type: 'string',
      default: ''
    },
    minimum_sold_date: {
      type: 'string',
      default: ''
    },
    filter_pool: {
      type: 'boolean',
      default: false
    },
    filter_agents: {
      type: 'string',
      default: ''
    },
    list_offices: {
      type: 'string',
      default: ''
    },
    filter_brand_id: {
      type: 'string',
      default: ''
    },
    disable_filter_loading_indicator: {
      type: 'boolean',
      default: false
    },
    filter_boundary_country: {
      type: 'string',
      default: ''
    },
    filter_boundary_state: {
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
      minimum_square_feet,
      maximum_square_feet,
      minimum_bathrooms,
      maximum_bathrooms,
      minimum_lot_square_feet,
      maximum_lot_square_feet,
      minimum_year_built,
      maximum_year_built,
      minimum_bedrooms,
      maximum_bedrooms,
      listing_per_page,
      filterByRegions,
      filterByOffices,
      selectedStatuses,
      disable_filter_address,
      disable_filter_price,
      disable_filter_beds,
      disable_filter_baths,
      disable_filter_property_types,
      disable_filter_advanced,
      own_listing,
      property_types,
      filter_open_houses,
      office_exclusive,
      filter_pool,
      disable_sort,
      listing_statuses,
      map_latitude,
      map_longitude,
      map_zoom,
      map_style,
      map_style_url,
      map_id,
      sort_by,
      filter_address,
      filter_search_limit,
      filter_suggestions_limit,
      filter_pagination_offset,
      property_subtypes,
      architectural_styles,
      filter_baths,
      minimum_parking_spaces,
      minimum_sold_date,
      filter_agents,
      list_offices,
      filter_brand_id,
      disable_filter_loading_indicator,
      filter_boundary_country,
      filter_boundary_state
    } = attributes;
    const [regions, setRegions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
    const [offices, setOffices] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
    const [googleMapsApiKey, setGoogleMapsApiKey] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
    const [siteBoundaryDefaults, setSiteBoundaryDefaults] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
    const [boundaryCountryOptions, setBoundaryCountryOptions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([{
      label: 'Any',
      value: ''
    }]);
    const [boundaryStateOptions, setBoundaryStateOptions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([{
      label: 'Any',
      value: ''
    }]);
    const [boundaryStatesLoading, setBoundaryStatesLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
    const defaultsSeededRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(false);
    const statusOptions = [{
      label: 'Active',
      value: 'Active'
    }, {
      label: 'Pending',
      value: 'Pending'
    }, {
      label: 'Closed',
      value: 'Closed'
    }, {
      label: 'Archived',
      value: 'Archived'
    }];
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      (0,_utils_api_helpers__WEBPACK_IMPORTED_MODULE_4__.fetchDataWithMeta)('/wp/v2/regions?per_page=100', setRegions);
      (0,_utils_api_helpers__WEBPACK_IMPORTED_MODULE_4__.fetchDataWithMeta)('/wp/v2/offices?per_page=100', setOffices);
      _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/wp/v2/options'
      }).then(options => {
        if (options.rch_rechat_google_map_api_key) {
          setGoogleMapsApiKey(options.rch_rechat_google_map_api_key);
        }
        setSiteBoundaryDefaults({
          country: options.rch_selected_country ? String(options.rch_selected_country).toUpperCase() : '',
          state: options.rch_selected_state ? String(options.rch_selected_state) : ''
        });
      }).catch(error => {
        console.error('Error fetching editor options:', error);
        setSiteBoundaryDefaults({
          country: '',
          state: ''
        });
      });
      _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/rch/v1/boundary-countries'
      }).then(res => {
        const rows = parseBoundaryRestOptions(res);
        setBoundaryCountryOptions([{
          label: 'Any',
          value: ''
        }, ...rows]);
      }).catch(error => {
        console.error('Error loading boundary countries:', error);
      });
    }, []);
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      if (defaultsSeededRef.current || siteBoundaryDefaults === null) {
        return;
      }
      defaultsSeededRef.current = true;
      const sc = siteBoundaryDefaults.country || '';
      const ss = siteBoundaryDefaults.state || '';
      const patch = {};
      if (!filter_boundary_country && !filter_boundary_state && sc && ss) {
        patch.filter_boundary_country = sc;
        patch.filter_boundary_state = ss;
      } else if (!filter_boundary_country && sc) {
        patch.filter_boundary_country = sc;
      } else if (!filter_boundary_state && ss && filter_boundary_country && sc && filter_boundary_country === sc) {
        patch.filter_boundary_state = ss;
      }
      if (Object.keys(patch).length) {
        setAttributes(patch);
      }
    }, [siteBoundaryDefaults, filter_boundary_country, filter_boundary_state, setAttributes]);
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
      if (!filter_boundary_country) {
        setBoundaryStatesLoading(false);
        setBoundaryStateOptions([{
          label: 'Any',
          value: ''
        }]);
        return;
      }
      let cancelled = false;
      setBoundaryStatesLoading(true);
      _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: `/rch/v1/boundary-states?country=${encodeURIComponent(filter_boundary_country)}`
      }).then(res => {
        if (cancelled) {
          return;
        }
        const rows = parseBoundaryRestOptions(res);
        setBoundaryStateOptions([{
          label: 'Any',
          value: ''
        }, ...rows]);
      }).catch(error => {
        if (!cancelled) {
          console.error('Error loading boundary states:', error);
          setBoundaryStateOptions([{
            label: 'Any',
            value: ''
          }]);
        }
      }).finally(() => {
        if (!cancelled) {
          setBoundaryStatesLoading(false);
        }
      });
      return () => {
        cancelled = true;
      };
    }, [filter_boundary_country]);
    const handleAttributeChange = (attr, value) => {
      setAttributes({
        [attr]: value
      });
    };
    const handleStatusChange = status => {
      const updatedStatuses = selectedStatuses.includes(status) ? selectedStatuses.filter(s => s !== status) : [...selectedStatuses, status];
      const listingStatuses = updatedStatuses.flatMap(statusKey => ({
        Active: ['Active', 'Incoming', 'Coming Soon'],
        Pending: ['Pending'],
        Closed: ['Sold', 'Leased'],
        Archived: ['Withdrawn', 'Expired']
      })[statusKey] || []);
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
    const handleMapLocationChange = location => {
      if (location && location.lat && location.lng) {
        setAttributes({
          map_latitude: location.lat.toString(),
          map_longitude: location.lng.toString()
        });
      }
    };
    const handleZoomChange = zoom => {
      setAttributes({
        map_zoom: zoom.toString()
      });
    };
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(InspectorControls, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(PanelBody, {
          title: "Listing Settings",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Only our own listings",
            checked: own_listing,
            onChange: () => setAttributes({
              own_listing: !own_listing
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Open Houses Only",
            checked: filter_open_houses,
            onChange: () => setAttributes({
              filter_open_houses: !filter_open_houses
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Hide Sort By",
            checked: disable_sort,
            onChange: () => setAttributes({
              disable_sort: !disable_sort
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Office Exclusive",
            checked: office_exclusive,
            onChange: () => setAttributes({
              office_exclusive: !office_exclusive
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(SelectControl, {
            label: "Boundary country (filter_boundary_country)",
            help: "Defaults from General Settings; change here to scope this block only. ISO code from Rechat (e.g. US).",
            value: filter_boundary_country,
            options: boundaryCountryOptions,
            onChange: value => setAttributes({
              filter_boundary_country: value ? String(value).toUpperCase() : '',
              filter_boundary_state: ''
            })
          }), filter_boundary_country && boundaryStatesLoading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("p", {
            className: "components-base-control__help",
            style: {
              display: 'flex',
              alignItems: 'center',
              gap: 8,
              marginBottom: 12
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(Spinner, {}), "Loading states for this country\u2026"]
          }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(SelectControl, {
            label: "Boundary state / province (filter_boundary_state)",
            help: !filter_boundary_country ? 'Choose a country first, or leave both as Any.' : boundaryStatesLoading ? '' : 'Uses the state title expected by the Rechat SDK (same as General Settings).',
            value: filter_boundary_state,
            options: boundaryStateOptions,
            disabled: !filter_boundary_country || boundaryStatesLoading,
            onChange: value => setAttributes({
              filter_boundary_state: value || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(SelectControl, {
            label: "Select a Region",
            value: filterByRegions,
            options: regions,
            onChange: value => handleAttributeChange('filterByRegions', value)
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(SelectControl, {
            label: "Select an Office",
            value: filterByOffices,
            options: offices,
            onChange: value => handleAttributeChange('filterByOffices', value)
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
              children: "Select Statuses"
            })
          }), statusOptions.map(option => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: option.label,
            checked: selectedStatuses.includes(option.value),
            onChange: () => handleStatusChange(option.value)
          }, option.value)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
              children: "Property Type"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(RadioControl, {
            label: "Select Property Type",
            selected: property_types,
            options: [{
              label: 'All Listings',
              value: ''
            }, {
              label: 'Residential',
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
            }, {
              label: 'Multi-Family',
              value: 'Multi-Family'
            }],
            onChange: handlePropertyTypeChange
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Minimum Price",
            value: minimum_price,
            type: "number",
            onChange: value => setAttributes({
              minimum_price: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Maximum Price",
            value: maximum_price,
            type: "number",
            onChange: value => setAttributes({
              maximum_price: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Minimum Square Feet",
            value: minimum_square_feet,
            type: "number",
            onChange: value => setAttributes({
              minimum_square_feet: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Maximum Square Feet",
            value: maximum_square_feet,
            type: "number",
            onChange: value => setAttributes({
              maximum_square_feet: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Minimum Bathrooms",
            value: minimum_bathrooms,
            type: "number",
            onChange: value => setAttributes({
              minimum_bathrooms: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Maximum Bathrooms",
            value: maximum_bathrooms,
            type: "number",
            onChange: value => setAttributes({
              maximum_bathrooms: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Minimum Lot Square Feet",
            value: minimum_lot_square_feet,
            type: "number",
            onChange: value => setAttributes({
              minimum_lot_square_feet: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Maximum Lot Square Feet",
            value: maximum_lot_square_feet,
            type: "number",
            onChange: value => setAttributes({
              maximum_lot_square_feet: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Minimum Year Built",
            value: minimum_year_built,
            type: "number",
            onChange: value => setAttributes({
              minimum_year_built: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Maximum Year Built",
            value: maximum_year_built,
            type: "number",
            onChange: value => setAttributes({
              maximum_year_built: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Minimum Bedrooms",
            value: minimum_bedrooms,
            type: "number",
            onChange: value => setAttributes({
              minimum_bedrooms: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Maximum Bedrooms",
            value: maximum_bedrooms,
            type: "number",
            onChange: value => setAttributes({
              maximum_bedrooms: value === '' ? '' : value.toString()
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(SelectControl, {
            label: "Sort By",
            value: sort_by,
            options: [{
              label: 'Sort by Date',
              value: '-list_date'
            }, {
              label: 'Sort by Price',
              value: '-price'
            }],
            onChange: value => setAttributes({
              sort_by: value
            })
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(PanelBody, {
          title: "Filter Visibility Settings",
          initialOpen: false,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
              children: "Disable Filters (check to hide)"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Disable Address Filter",
            checked: disable_filter_address,
            onChange: () => setAttributes({
              disable_filter_address: !disable_filter_address
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Disable Price Filter",
            checked: disable_filter_price,
            onChange: () => setAttributes({
              disable_filter_price: !disable_filter_price
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Disable Beds Filter",
            checked: disable_filter_beds,
            onChange: () => setAttributes({
              disable_filter_beds: !disable_filter_beds
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Disable Baths Filter",
            checked: disable_filter_baths,
            onChange: () => setAttributes({
              disable_filter_baths: !disable_filter_baths
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Disable Property Types Filter",
            checked: disable_filter_property_types,
            onChange: () => setAttributes({
              disable_filter_property_types: !disable_filter_property_types
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Disable Advanced Filter",
            checked: disable_filter_advanced,
            onChange: () => setAttributes({
              disable_filter_advanced: !disable_filter_advanced
            })
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(PanelBody, {
          title: "Additional Rechat filters (optional)",
          initialOpen: false,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Initial address / map boundary (filter_address)",
            help: "Sets filter_address on the list view (e.g. city or place search).",
            value: filter_address,
            onChange: v => setAttributes({
              filter_address: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Max result count (filter_search_limit)",
            type: "number",
            value: filter_search_limit,
            onChange: v => setAttributes({
              filter_search_limit: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Search suggestions limit (filter_suggestions_limit)",
            type: "number",
            value: filter_suggestions_limit,
            onChange: v => setAttributes({
              filter_suggestions_limit: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Initial pagination offset (filter_pagination_offset)",
            type: "number",
            value: filter_pagination_offset,
            onChange: v => setAttributes({
              filter_pagination_offset: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Property subtypes (comma-separated)",
            value: property_subtypes,
            onChange: v => setAttributes({
              property_subtypes: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Architectural styles (comma-separated)",
            value: architectural_styles,
            onChange: v => setAttributes({
              architectural_styles: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Exact baths (filter_baths)",
            type: "number",
            value: filter_baths,
            onChange: v => setAttributes({
              filter_baths: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Min parking spaces",
            type: "number",
            value: minimum_parking_spaces,
            onChange: v => setAttributes({
              minimum_parking_spaces: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Minimum sold date (Unix ms, filter_minimum_sold_date)",
            value: minimum_sold_date,
            onChange: v => setAttributes({
              minimum_sold_date: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Map ID (map_id, Cloud map styling)",
            value: map_id,
            onChange: v => setAttributes({
              map_id: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Override brand ID (filter_brand_id)",
            value: filter_brand_id,
            onChange: v => setAttributes({
              filter_brand_id: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Agent IDs (filter_agents, comma-separated)",
            value: filter_agents,
            onChange: v => setAttributes({
              filter_agents: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Office IDs (list_offices / filter_list_offices, comma-separated)",
            value: list_offices,
            onChange: v => setAttributes({
              list_offices: v || ''
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Pool only (filter_pool)",
            checked: filter_pool,
            onChange: () => setAttributes({
              filter_pool: !filter_pool
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(CheckboxControl, {
            label: "Disable filter loading indicator",
            checked: disable_filter_loading_indicator,
            onChange: () => setAttributes({
              disable_filter_loading_indicator: !disable_filter_loading_indicator
            })
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(PanelBody, {
          title: "Map Settings",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(SelectControl, {
            label: "Map style (preset)",
            value: map_style || '',
            options: [{
              label: 'Default (liberty)',
              value: ''
            }, {
              label: 'Liberty',
              value: 'liberty'
            }, {
              label: 'Bright',
              value: 'bright'
            }, {
              label: 'Positron',
              value: 'positron'
            }, {
              label: 'Dark',
              value: 'dark'
            }],
            onChange: v => setAttributes({
              map_style: v || ''
            }),
            help: "MapLibre preset on rechat-map. Custom URL below overrides preset."
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
            label: "Map style URL (style_url)",
            value: map_style_url,
            onChange: v => setAttributes({
              map_style_url: v || ''
            }),
            help: "Optional MapLibre style JSON URL. When set, overrides preset."
          }), googleMapsApiKey ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
                children: "Location Selector"
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_utils_map_selector__WEBPACK_IMPORTED_MODULE_3__["default"], {
              apiKey: googleMapsApiKey,
              latitude: map_latitude,
              longitude: map_longitude,
              zoom: map_zoom,
              onLocationChange: handleMapLocationChange,
              onZoomChange: handleZoomChange
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
              label: "Latitude",
              value: map_latitude,
              onChange: value => setAttributes({
                map_latitude: value
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(TextControl, {
              label: "Longitude",
              value: map_longitude,
              onChange: value => setAttributes({
                map_longitude: value
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(RangeControl, {
              label: "Zoom Level",
              value: parseInt(map_zoom) || 12,
              onChange: handleZoomChange,
              min: 1,
              max: 20
            })]
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
            children: "Google Maps API key not found. Please make sure it is configured in the WordPress settings."
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default()), {
        block: "rch-rechat-plugin/listing-block",
        attributes: attributes
      })]
    });
  },
  save() {
    return null;
  }
});

/***/ }),

/***/ "./src/blocks/offices-block.js":
/*!*************************************!*\
  !*** ./src/blocks/offices-block.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/server-side-render */ "@wordpress/server-side-render");
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
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
  SelectControl
} = wp.components;




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
    const [regions, setRegions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);

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
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(PanelBody, {
          title: "Settings",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(RangeControl, {
            label: "Posts Per Page",
            value: postsPerPage,
            onChange: value => setAttributes({
              postsPerPage: value
            }),
            min: 1,
            max: 20
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(SelectControl, {
            label: "Select a Region",
            value: filterByRegions,
            options: regions.length ? regions : [{
              label: 'Loading regions...',
              value: ''
            }],
            onChange: selectedRegion => setAttributes({
              filterByRegions: selectedRegion
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("strong", {
              children: "Select your background color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(ColorPalette, {
            value: regionBgColor,
            onChange: color => setAttributes({
              regionBgColor: color
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("strong", {
              children: "Select your text color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(ColorPalette, {
            value: textColor,
            onChange: color => setAttributes({
              textColor: color
            })
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_1___default()), {
        block: "rch-rechat-plugin/offices-block",
        attributes: attributes
      })]
    });
  },
  save() {
    return null;
  }
});

/***/ }),

/***/ "./src/blocks/regions-block.js":
/*!*************************************!*\
  !*** ./src/blocks/regions-block.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/server-side-render */ "@wordpress/server-side-render");
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
const {
  registerBlockType
} = wp.blocks;
const {
  InspectorControls,
  ColorPalette
} = wp.blockEditor || wp.editor;
const {
  PanelBody,
  RangeControl
} = wp.components;


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
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)(PanelBody, {
          title: 'Setting',
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(RangeControl, {
            label: "Posts Per Page",
            value: postsPerPage,
            onChange: updatePostPerPage,
            min: 1,
            max: 20
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("strong", {
              children: "Select your background color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(ColorPalette, {
            value: regionBgColor,
            onChange: regionBackgroundSelect
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("p", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("strong", {
              children: "Select your text color"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(ColorPalette, {
            value: textColor,
            onChange: textColorSelect
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_0___default()), {
        block: "rch-rechat-plugin/regions-block",
        attributes: attributes
      })]
    });
  },
  save() {
    return null;
  }
});

/***/ }),

/***/ "./src/utils/api-helpers.js":
/*!**********************************!*\
  !*** ./src/utils/api-helpers.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   fetchData: () => (/* binding */ fetchData),
/* harmony export */   fetchDataWithMeta: () => (/* binding */ fetchDataWithMeta),
/* harmony export */   fetchWPOption: () => (/* binding */ fetchWPOption)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);


/**
 * Fetch data from WordPress REST API
 * @param {string} endpoint - API endpoint path
 * @param {Function} setState - State setter function
 */
const fetchData = async (endpoint, setState) => {
  try {
    const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
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

/**
 * Fetch data with custom value mapping
 * @param {string} path - API endpoint path
 * @param {Function} setState - State setter function
 */
const fetchDataWithMeta = async (path, setState) => {
  try {
    const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path
    });
    setState([{
      label: 'None',
      value: ''
    }, ...data.map(item => ({
      label: item.title.rendered,
      value: item.meta?.region_id || item.meta?.office_id || item.id
    }))]);
  } catch (error) {
    console.error('Error fetching data:', error);
  }
};

/**
 * Fetch WordPress options
 * @param {string} optionKey - The option key to retrieve
 * @returns {Promise<any>} The option value
 */
const fetchWPOption = async optionKey => {
  try {
    const options = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: '/wp/v2/options'
    });
    return options[optionKey] || null;
  } catch (error) {
    console.error(`Error fetching option ${optionKey}:`, error);
    return null;
  }
};

/***/ }),

/***/ "./src/utils/map-selector.js":
/*!***********************************!*\
  !*** ./src/utils/map-selector.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


const MapSelector = ({
  apiKey,
  latitude,
  longitude,
  zoom,
  onLocationChange,
  onZoomChange
}) => {
  const mapRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const markerRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const mapInstanceRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const searchBoxRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);

  // Initialize map when component mounts
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (!apiKey || !window.google || !window.google.maps) {
      // Load Google Maps API
      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places,drawing`;
      script.async = true;
      script.onload = initMap;
      document.head.appendChild(script);
      return () => {
        // Clean up script when component unmounts
        document.head.removeChild(script);
      };
    } else {
      // Google Maps API already loaded
      initMap();
    }
  }, [apiKey]);

  // Re-center map when lat/lng changes from external source
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (mapInstanceRef.current && markerRef.current && latitude && longitude) {
      const position = new window.google.maps.LatLng(parseFloat(latitude), parseFloat(longitude));
      mapInstanceRef.current.setCenter(position);
      markerRef.current.setPosition(position);
    }
  }, [latitude, longitude]);

  // Update zoom when it changes from external source
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (mapInstanceRef.current && zoom) {
      mapInstanceRef.current.setZoom(parseInt(zoom));
    }
  }, [zoom]);
  const initMap = () => {
    if (!window.google || !window.google.maps) return;

    // Default position if no coordinates provided
    const defaultLat = latitude ? parseFloat(latitude) : 37.7749;
    const defaultLng = longitude ? parseFloat(longitude) : -122.4194;
    const defaultZoom = zoom ? parseInt(zoom) : 12;
    const mapOptions = {
      center: {
        lat: defaultLat,
        lng: defaultLng
      },
      zoom: defaultZoom,
      mapTypeId: window.google.maps.MapTypeId.ROADMAP,
      zoomControl: true,
      mapTypeControl: true,
      scaleControl: true,
      streetViewControl: false,
      rotateControl: false,
      fullscreenControl: true
    };

    // Create map instance
    const mapInstance = new window.google.maps.Map(mapRef.current, mapOptions);
    mapInstanceRef.current = mapInstance;

    // Create marker at center
    const marker = new window.google.maps.Marker({
      position: {
        lat: defaultLat,
        lng: defaultLng
      },
      map: mapInstance,
      draggable: true
    });
    markerRef.current = marker;

    // Add event listener for marker drag
    marker.addListener('dragend', function () {
      const position = marker.getPosition();
      if (onLocationChange) {
        onLocationChange({
          lat: position.lat(),
          lng: position.lng()
        });
      }
    });

    // Add event listener for map click
    mapInstance.addListener('click', function (event) {
      marker.setPosition(event.latLng);
      if (onLocationChange) {
        onLocationChange({
          lat: event.latLng.lat(),
          lng: event.latLng.lng()
        });
      }
    });

    // Add event listener for zoom changed
    mapInstance.addListener('zoom_changed', function () {
      if (onZoomChange) {
        onZoomChange(mapInstance.getZoom());
      }
    });

    // Create search box if Places library is available
    if (window.google.maps.places) {
      const input = document.createElement('input');
      input.setAttribute('type', 'text');
      input.setAttribute('placeholder', 'Search for a location...');
      input.style.width = '70%';
      input.style.padding = '12px';
      input.style.borderRadius = '4px';
      input.style.marginTop = '10px';
      input.style.boxSizing = 'border-box';
      const searchBox = new window.google.maps.places.SearchBox(input);
      searchBoxRef.current = searchBox;
      mapInstance.controls[window.google.maps.ControlPosition.TOP_CENTER].push(input);

      // Bias search results to current map viewport
      mapInstance.addListener('bounds_changed', function () {
        searchBox.setBounds(mapInstance.getBounds());
      });

      // Listen for search box selections
      searchBox.addListener('places_changed', function () {
        const places = searchBox.getPlaces();
        if (places.length === 0) return;
        const place = places[0];
        if (!place.geometry || !place.geometry.location) return;

        // Update marker and map position
        marker.setPosition(place.geometry.location);
        mapInstance.setCenter(place.geometry.location);

        // Update stored location
        if (onLocationChange) {
          onLocationChange({
            lat: place.geometry.location.lat(),
            lng: place.geometry.location.lng()
          });
        }
      });
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
    style: {
      height: '300px',
      marginBottom: '20px',
      position: 'relative'
    },
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
      ref: mapRef,
      style: {
        height: '100%',
        width: '100%'
      }
    })
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (MapSelector);

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
/* harmony import */ var _blocks_regions_block__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./blocks/regions-block */ "./src/blocks/regions-block.js");
/* harmony import */ var _blocks_offices_block__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./blocks/offices-block */ "./src/blocks/offices-block.js");
/* harmony import */ var _blocks_agents_block__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./blocks/agents-block */ "./src/blocks/agents-block.js");
/* harmony import */ var _blocks_listing_block__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./blocks/listing-block */ "./src/blocks/listing-block.js");
/* harmony import */ var _blocks_leads_form_block__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./blocks/leads-form-block */ "./src/blocks/leads-form-block.js");
/**
 * Main entry point for Rechat Plugin Gutenberg Blocks
 * 
 * This file imports and registers all custom blocks for the plugin.
 * Each block is organized in its own file for better maintainability.
 */

// Import all block components





})();

/******/ })()
;
//# sourceMappingURL=index.js.map