// Conversion factor from square feet to square meters
const conversionFactor = 0.092903
/**
 * Converts square footage value to square meters.
 * @param {number} value - The square footage value to convert.
 * @returns {number} - The converted value in square meters.
 */
function convertToSquareMeters(value) {
    return value * conversionFactor
}
// Function to get URL parameters and set the content field
function setContentFromURLParams() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('content')) {
        const contentValue = urlParams.get('content');
        const contentInput = document.getElementById('content');
        console.log('Setting content from URL param:', contentValue);
        if (contentInput) {
            contentInput.value = contentValue;
            // Trigger filter application with the loaded value
            applyFilters();
        }
    }
}

// Select all elements with the specific ID
const squareMeterInputs = document.querySelectorAll('#minimum_square_meters, #maximum_square_meters')
const squareLotInputs = document.querySelectorAll('#minimum_lot_square_meters, #maximum_lot_square_meters')

// Attach event listener to all elements
squareMeterInputs.forEach(inputElement => {
    inputElement.addEventListener('keyup', handleConversion)
})
squareLotInputs.forEach(inputElement => {
    inputElement.addEventListener('keyup', handleConversion)
})
/**
 * Handles the conversion of square footage to square meters on input.
 * Updates the filters object with the converted value.
 * @param {Event} event - The keyup event object.
 */
function handleConversion(event) {
    // Wait for the keypress to complete
    setTimeout(() => {
        const inputElement = event.target
        const valueInFeet = parseFloat(inputElement.value)
        // Ensure the input is a valid number
        if (!isNaN(valueInFeet)) {
            const valueInMeters = convertToSquareMeters(valueInFeet)
            const inputName = inputElement.id

            // Dynamically update the filters object
            filters[inputName] = valueInMeters.toFixed(5)
        } else {
            const inputName = inputElement.id
            filters[inputName] = ''
        }
    }, 0);

    // Apply filters after updating
    updateFilterBadge()
    applyFilters()
}

/**
 * Applies all filter values from the form inputs to the filters object.
 * Updates the listing display with the new filter settings.
 */

function applyFilters() {
    // Update the filters with user inputs
    // filters.content = document.getElementById('content').value;
    filters.property_types = getSelectedRadioCheckboxValue('property_types');
    
    // For price filters, only update if DOM has a value OR if filter was explicitly cleared
    const minPriceValue = document.getElementById('minimum_price').value;
    const maxPriceValue = document.getElementById('maximum_price').value;
    
    // Update only if the dropdown has been populated and has a value (including empty string for "No Min/Max")
    // This prevents overwriting default values with empty strings during initialization
    if (document.getElementById('minimum_price').options.length > 0) {
        filters.minimum_price = minPriceValue;
    }
    if (document.getElementById('maximum_price').options.length > 0) {
        filters.maximum_price = maxPriceValue;
    }
    
    filters.minimum_bedrooms = document.getElementById('minimum_bedrooms').value;
    filters.maximum_bedrooms = document.getElementById('maximum_bedrooms').value;
    filters.listing_statuses = getSelectedCheckboxValues('listing_statuses');
    
    // Handle open_house checkbox - only set to true if checked, otherwise remove from filters
    const openHouseCheckbox = document.getElementById('mobile_open_house') || document.getElementById('desktop_open_house');
    if (openHouseCheckbox && openHouseCheckbox.checked) {
        filters.open_house = true;
    } else {
        delete filters.open_house;
    }
    
    filters.minimum_square_meters = document.getElementById('minimum_square_meters').value;
    filters.maximum_square_meters = document.getElementById('maximum_square_meters').value;
    
    // For year built filters, only update if dropdowns are populated
    if (document.getElementById('minimum_year_built').options.length > 0) {
        filters.minimum_year_built = document.getElementById('minimum_year_built').value;
    }
    if (document.getElementById('maximum_year_built').options.length > 0) {
        filters.maximum_year_built = document.getElementById('maximum_year_built').value;
    }
    
    filters.points = document.getElementById('query-string').value,
        currentPage = 1; // Reset to the first page after applying filters
    updateActiveClass()
    updateListingList(); // Fetch filtered listings
    
    // Track the search with SDK (only after user interaction, not on initial load)
    if (typeof window.trackSearchedListings === 'function') {
        window.trackSearchedListings(filters);
    }

    // Save filter state for persistence (when user navigates away)
    if (typeof window.RCH_FilterPersistence !== 'undefined') {
        window.RCH_FilterPersistence.saveState(filters, currentPage);
    }
}

/**
 * Closes all dropdown filter options.
 */
function closeAllOptions() {
    document.querySelectorAll('.rch-inside-filters').forEach(div => {
        div.style.display = 'none'; // Close all dropdowns
    });
}
document.getElementById('content').addEventListener('input', function () {
    if (this.value.trim() === "") {
        // Call applyFilters() when the input is cleared
        applyFilters();
    }
});
// Add event listeners for each dropdown
document.querySelectorAll('.rch-filters .box-filter-listing').forEach(box => {
    const toggle = box.querySelector('.toggleMain');
    const optionsDiv = box.querySelector('.rch-inside-filters');

    // Add click event listener to toggle button
    toggle.addEventListener('click', function (event) {
        event.stopPropagation(); // Prevent bubbling to document

        // Check the current visibility of the dropdown
        if (optionsDiv.style.display === 'none' || optionsDiv.style.display === '') {
            closeAllOptions(); // Close all other dropdowns
            optionsDiv.style.display = 'flex'; // Open the current dropdown

        } else {
            optionsDiv.style.display = 'none'; // Close the current dropdown

        }
    });

    // Prevent closing the dropdown when clicking inside it
    optionsDiv.addEventListener('click', function (event) {
        event.stopPropagation(); // Prevent bubbling to document
    });
});
document.querySelectorAll('.rch-filters-mobile .box-filter-listing .toggleMain').forEach((toggle) => {
    toggle.addEventListener('click', function () {
        const parentBox = this.parentElement; // Get the parent box-filter-listing
        const filterContent = parentBox.querySelector('.rch-inside-filters'); // Get the filter content

        // Close all other filters first
        document.querySelectorAll('.rch-filters-mobile .box-filter-listing .rch-inside-filters').forEach(filter => {
            if (filter !== filterContent) {
                filter.style.display = 'none';
            }
        });

        // Toggle the clicked filter
        if (filterContent.style.display === 'none' || !filterContent.style.display) {
            filterContent.style.display = 'block'; // Open the filter
        } else {
            filterContent.style.display = 'none'; // Close the filter
        }
    });
});
// Close all dropdowns when clicking outside
document.addEventListener('click', function (event) {
    closeAllOptions(); // Close all dropdowns

    const filtersContainer = document.getElementById('filters-container');
    const toggleButton = document.querySelector('.filter-toggle-btn');

    // Check if the elements exist before accessing their properties
    if (filtersContainer && toggleButton) {
        // Check if the clicked element is outside the filters container and toggle button
        if (!filtersContainer.contains(event.target) && !toggleButton.contains(event.target)) {
            filtersContainer.classList.remove('show');
        }
    }
});

/**
 * Updates the maximum bedrooms dropdown options based on the selected minimum.
 * Ensures the maximum value is always greater than the minimum value.
 */
function updateMaxBedsOptions() {
    const minSelect = document.getElementById('minimum_bedrooms');
    const maxSelect = document.getElementById('maximum_bedrooms');
    const selectedMin = parseInt(minSelect.value) || 0;

    // Clear all options in the maximum dropdown
    maxSelect.innerHTML = '';

    // Add the "No Max" option
    const noMaxOption = document.createElement('option');
    noMaxOption.value = '';
    noMaxOption.textContent = 'No Max';
    maxSelect.appendChild(noMaxOption);

    // Add options greater than the selected minimum
    for (let i = selectedMin + 1; i <= 6; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = i;
        maxSelect.appendChild(option);
    }
    applyFilters()
}
// Handle button sFor Bathroom
setupFilterButtons('.rch-bath-filter-listing', 'minimum_bathrooms', 'rch-baths-text-filter', 'Baths');
//handle parking select
setupFilterButtons('.rch-parking-filter-listing', 'minimum_parking_spaces', 'rch-parking-text-filter', 'Parking');

/**
 * Restore filter UI state from saved filters object
 * This syncs the UI controls with the filter values restored from sessionStorage
 */
function restoreFilterUI(savedFilters) {
    console.log('Restoring filter UI with:', savedFilters);
    
    // Restore property types (radio buttons)
    if (savedFilters.property_types) {
        const propertyTypesValue = Array.isArray(savedFilters.property_types) 
            ? savedFilters.property_types.join(',') 
            : savedFilters.property_types;
        
        const propertyTypeRadios = document.querySelectorAll('input[name="property_types"]');
        propertyTypeRadios.forEach(radio => {
            if (radio.value === propertyTypesValue) {
                radio.checked = true;
            }
        });
    }
    
    // Restore price filters
    if (savedFilters.minimum_price !== undefined && document.getElementById('minimum_price').options.length > 0) {
        document.getElementById('minimum_price').value = savedFilters.minimum_price;
    }
    if (savedFilters.maximum_price !== undefined && document.getElementById('maximum_price').options.length > 0) {
        document.getElementById('maximum_price').value = savedFilters.maximum_price;
    }
    
    // Restore bedroom filters
    if (savedFilters.minimum_bedrooms !== undefined) {
        document.getElementById('minimum_bedrooms').value = savedFilters.minimum_bedrooms;
    }
    if (savedFilters.maximum_bedrooms !== undefined) {
        document.getElementById('maximum_bedrooms').value = savedFilters.maximum_bedrooms;
    }
    
    // Restore bathroom filters
    if (savedFilters.minimum_bathrooms !== undefined) {
        const bathButtons = document.querySelectorAll('.rch-bath-filter-listing .filter-btn');
        bathButtons.forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('data-value') === String(savedFilters.minimum_bathrooms)) {
                btn.classList.add('active');
            }
        });
    }
    
    // Restore parking filters
    if (savedFilters.minimum_parking_spaces !== undefined) {
        const parkingButtons = document.querySelectorAll('.rch-parking-filter-listing .filter-btn');
        parkingButtons.forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('data-value') === String(savedFilters.minimum_parking_spaces)) {
                btn.classList.add('active');
            }
        });
    }
    
    // Restore square footage
    if (savedFilters.minimum_square_meters !== undefined) {
        document.getElementById('minimum_square_meters').value = savedFilters.minimum_square_meters;
    }
    if (savedFilters.maximum_square_meters !== undefined) {
        document.getElementById('maximum_square_meters').value = savedFilters.maximum_square_meters;
    }
    
    // Restore lot size
    if (savedFilters.minimum_lot_square_meters !== undefined) {
        document.getElementById('minimum_lot_square_meters').value = savedFilters.minimum_lot_square_meters;
    }
    if (savedFilters.maximum_lot_square_meters !== undefined) {
        document.getElementById('maximum_lot_square_meters').value = savedFilters.maximum_lot_square_meters;
    }
    
    // Restore year built
    if (savedFilters.minimum_year_built !== undefined && document.getElementById('minimum_year_built').options.length > 0) {
        document.getElementById('minimum_year_built').value = savedFilters.minimum_year_built;
    }
    if (savedFilters.maximum_year_built !== undefined && document.getElementById('maximum_year_built').options.length > 0) {
        document.getElementById('maximum_year_built').value = savedFilters.maximum_year_built;
    }
    
    // Restore open house checkbox
    const openHouseCheckboxMobile = document.getElementById('mobile_open_house');
    const openHouseCheckboxDesktop = document.getElementById('desktop_open_house');
    if (savedFilters.open_house === true) {
        if (openHouseCheckboxMobile) openHouseCheckboxMobile.checked = true;
        if (openHouseCheckboxDesktop) openHouseCheckboxDesktop.checked = true;
    }
    
    // Restore listing statuses (checkboxes)
    if (savedFilters.listing_statuses) {
        const statusCheckboxes = document.querySelectorAll('input[name="listing_statuses"]');
        const statusArray = Array.isArray(savedFilters.listing_statuses) 
            ? savedFilters.listing_statuses 
            : [savedFilters.listing_statuses];
        
        statusCheckboxes.forEach(checkbox => {
            checkbox.checked = statusArray.includes(checkbox.value);
        });
    }
    
    // Update all filter display texts
    updateActiveClass();
    updateDropdownTextGeneric("minimum_price", "maximum_price", "rch-price-text-filter", {
        prefix: "Price",
        formatNumbers: true,
        fallbackMin: "Any",
        fallbackMax: "Any",
    });
    updateDropdownTextGeneric("minimum_bedrooms", "maximum_bedrooms", "rch-beds-text-filter", {
        prefix: "Beds",
        formatNumbers: false,
        fallbackMin: "Any",
        fallbackMax: "Any",
    });
    
    console.log('Filter UI restored successfully');
}

document.addEventListener('DOMContentLoaded', () => {
    // Initialize both mobile and desktop bath/parking filters
    initFilterButtons();

    // Retrieve default values from the server.
    // (Make sure these PHP variables are defined and output correctly.)
    const defaultMinPrice = defaultFilters.minimum_price
    const defaultMaxPrice = defaultFilters.maximum_price;
    const defaultMinYear = defaultFilters.minimum_year_built
    const defaultMaxYear = defaultFilters.maximum_year_built;
    const currentYear = new Date().getFullYear();
    const priceOptions = generatePriceOptions();

    // Get all sets of min and max selects for price and year built
    const minPriceSelects = document.querySelectorAll('[id="minimum_price"]');
    const maxPriceSelects = document.querySelectorAll('[id="maximum_price"]');
    const minYearSelects = document.querySelectorAll('[id="minimum_year_built"]');
    const maxYearSelects = document.querySelectorAll('[id="maximum_year_built"]');

    // Initialize filters for each set of selects.
    // For price, pass the default values.
    minPriceSelects.forEach((minPriceSelect, index) => {
        const maxPriceSelect = maxPriceSelects[index];
        initializeFilter(minPriceSelect, maxPriceSelect, generatePriceOptions, priceOptions, true, defaultMinPrice, defaultMaxPrice);
    });

    // For year built filters (without defaults in this example)
    minYearSelects.forEach((minYearSelect, index) => {
        const maxYearSelect = maxYearSelects[index];
        initializeFilter(minYearSelect, maxYearSelect, () => generateYearOptions(currentYear), [], false, defaultMinYear, defaultMaxYear);
    });

    // Call this AFTER initializing all filters so dropdowns are populated first
    setContentFromURLParams();
    
    // Check if we need to restore filter state from sessionStorage
    // This happens when user returns from a single listing via Back button
    if (typeof window.RCH_FilterPersistence !== 'undefined') {
        const savedState = window.RCH_FilterPersistence.getState();
        
        if (savedState && savedState.filters) {
            // Delay restoration slightly to ensure all DOM elements are ready
            setTimeout(() => {
                restoreFilterUI(savedState.filters);
            }, 100);
        }
    }

    // Initialize a Generic Filter (Price or Year Built)
    // Added parameters defaultMin and defaultMax to be used for price defaults.
    function initializeFilter(minSelect, maxSelect, optionsGenerator, options, isPrice, defaultMin = null, defaultMax = null) {
        // Populate the minimum select and apply a default if provided
        populateMinOptions(minSelect, optionsGenerator(), isPrice, defaultMin);
        // Populate (or update) the maximum select and apply a default if provided
        updateMaxOptions(minSelect, maxSelect, optionsGenerator, isPrice, defaultMax);

        // When the user changes the min selection, update the max options.
        // Don't pass defaultMax here - only use it for initial load
        minSelect.addEventListener('change', () => updateMaxOptions(minSelect, maxSelect, optionsGenerator, isPrice, null));
        //update default class come from server
        updateActiveClass();
        //update default price value come from server
        updateDropdownTextGeneric("minimum_price", "maximum_price", "rch-price-text-filter", {
            prefix: "Price",
            formatNumbers: true, // Enable number formatting (e.g., "K", "M")
            fallbackMin: "Any",
            fallbackMax: "Any",
        });
        //update default Beds value come from server
        updateDropdownTextGeneric("minimum_bedrooms", "maximum_bedrooms", "rch-beds-text-filter", {
            prefix: "Beds",
            formatNumbers: false, // No number formatting
            fallbackMin: "Any",
            fallbackMax: "Any",
        });
        setupFilterButtons('.rch-bath-filter-listing', 'minimum_bathrooms', 'rch-baths-text-filter', 'Baths');
    }

    // Helper function to generate year options (from 1990 to current year)
    function generateYearOptions(currentYear) {
        const years = [];
        for (let year = 1990; year <= currentYear; year++) {
            years.push(year);
        }
        return years;
    }

    // Helper function to generate price options
    function generatePriceOptions() {
        const options = [];
        for (let i = 25000; i <= 999999; i += 25000) {
            options.push(i);
        }
        for (let i = 1000000; i <= 5000000; i += 1000000) {
            options.push(i);
        }
        return options;
    }

    // Populate the min select element and set its default value if provided.
    function populateMinOptions(selectElement, options, isPrice, defaultValue = null) {

        const noMinOption = createOptionElement('', 'Min');
        selectElement.appendChild(noMinOption);
        options.forEach(optionValue => {
            const formattedValue = isPrice ? formatNumber(optionValue) : optionValue;
            const option = createOptionElement(optionValue, formattedValue);
            selectElement.appendChild(option);
        });
        if (defaultValue) {
            let candidateValue = '';
            for (let i = 0; i < selectElement.options.length; i++) {
                let option = selectElement.options[i];
                if (option.value && parseInt(option.value) <= defaultValue) {
                    candidateValue = option.value;
                }
            }
            if (candidateValue) {
                selectElement.value = candidateValue;
            }
        }
    }

    function updateMaxOptions(minSelect, maxSelect, optionsGenerator, isPrice, defaultValue = null) {
        const selectedMin = parseInt(minSelect.value) || 0;
        maxSelect.innerHTML = '';
        const noMaxOption = createOptionElement('', 'Max');
        maxSelect.appendChild(noMaxOption);
        const options = optionsGenerator();
        options.forEach(optionValue => {
            if (optionValue >= selectedMin) {
                const formattedValue = isPrice ? formatNumber(optionValue) : optionValue;
                const option = createOptionElement(optionValue, formattedValue);
                maxSelect.appendChild(option);
            }
        });
        if (defaultValue) {
            let candidateValue = '';
            for (let i = 0; i < maxSelect.options.length; i++) {
                let option = maxSelect.options[i];
                if (option.value && parseInt(option.value) <= defaultValue) {
                    candidateValue = option.value;
                }
            }
            if (candidateValue) {
                maxSelect.value = candidateValue;
            }
        }
    }

    // Helper to create an option element.
    function createOptionElement(value, textContent) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = textContent;
        return option;
    }

    // (The rest of your code—for postal codes and tag handling—remains unchanged.)
    const input = document.querySelector("#postal_codes");
    const tagsContainer = document.querySelector("#tags-container");
    let zipCodesArray = []; // Array to store ZIP codes

    // Function to update the tags UI
    const updateTagsUI = () => {
        tagsContainer.innerHTML = ""; // Clear existing tags
        zipCodesArray.forEach((zipCode, index) => {
            const tag = document.createElement("span");
            tag.className = "zip-code-tag";
            tag.textContent = zipCode;

            // Add a remove button to each tag
            const removeBtn = document.createElement("button");
            removeBtn.className = "remove-tag";
            removeBtn.textContent = "×";
            removeBtn.onclick = () => {
                // Remove the ZIP code from the array and update the UI
                zipCodesArray.splice(index, 1);
                updateTagsUI();
                filters.postal_codes = zipCodesArray;
                applyFilters();
            };

            tag.appendChild(removeBtn);
            tagsContainer.appendChild(tag);
        });
    };

    // Event listener for ZIP code input
    input.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.keyCode === 13) {
            e.preventDefault();
            const value = input.value.trim();

            // Add the ZIP code if not empty and not already added
            if (value && !zipCodesArray.includes(value)) {
                zipCodesArray.push(value);
                updateTagsUI();
            }
            filters.postal_codes = zipCodesArray;
            input.value = ""; // Clear the input field
            applyFilters();
        }
    });

    // Prevent user to not enter character in lot and square meter
    function restrictToNumbers(event) {
        let input = event.target;
        input.value = input.value.replace(/\D/g, ""); // Remove non-numeric characters
    }

    // Select all relevant input fields
    let squareInputs = document.querySelectorAll("#minimum_square_meters, #maximum_square_meters, #minimum_lot_square_meters, #maximum_lot_square_meters");

    // Attach the event listener to each input field
    squareInputs.forEach(input => {
        input.addEventListener("input", restrictToNumbers);
    });
});


/**
 * Initialize filter buttons for both desktop and mobile views
 */
function initFilterButtons() {
    // Mobile bathroom buttons
    document.querySelectorAll('.rch-filters-mobile .rch-bath-filter-listing .filter-btn').forEach(button => {
        button.addEventListener('click', () => {
            const selectedValue = button.dataset.value;
            filters.minimum_bathrooms = selectedValue;

            // Update UI
            const siblings = button.parentElement.querySelectorAll('.filter-btn');
            siblings.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            // Apply filters
            applyFilters();
        });
    });

    // Mobile parking buttons
    document.querySelectorAll('.rch-filters-mobile .rch-parking-filter-listing .filter-btn').forEach(button => {
        button.addEventListener('click', () => {
            const selectedValue = button.dataset.value;
            filters.minimum_parking_spaces = selectedValue;

            // Update UI
            const siblings = button.parentElement.querySelectorAll('.filter-btn');
            siblings.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            // Apply filters
            applyFilters();
        });
    });
}

//Helper function for setting buttons
function setupFilterButtons(containerSelector, filterKey, textElementId, prefix = "") {
    const textElement = document.getElementById(textElementId);

    if (!textElement) {
        return;
    }

    // Default value for set first
    const initialValue = filters[filterKey] || "";
    textElement.textContent = initialValue ? `${prefix} +${initialValue}` : prefix;

    // Loop through all the filter buttons inside the container
    document.querySelectorAll(`${containerSelector} .filter-btn`).forEach(button => {
        button.addEventListener('click', () => {
            const selectedValue = button.dataset.value; // Get the selected value from the button

            // Update the filters object with the selected value
            filters[filterKey] = selectedValue;

            // Update the UI by adding 'active' class to the clicked button
            const siblings = button.parentElement.querySelectorAll('.filter-btn');
            siblings.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            // Update the text element with the selected value or just the prefix if empty
            const displayValue = selectedValue ? `${prefix} +${selectedValue}` : prefix;
            textElement.textContent = displayValue;

            // Update the active class on the container
            const closestBoxFilter = button.closest('.box-filter-listing');
            if (closestBoxFilter) {
                if (selectedValue) {
                    closestBoxFilter.classList.add('active');
                } else {
                    closestBoxFilter.classList.remove('active');
                }
            }
            
            // Update More Filters container if this button is inside it
            updateMoreFiltersActiveClass();

            // Call applyFilters to apply the filter and update the listings
            applyFilters();
        });
    });
}


/**
 * Resets a specific filter or all filters.
 * @param {string} filterName - Name of the filter to reset, or 'all' for all filters.
 * @param {boolean} skipApply - If true, doesn't apply filters after resetting.
 */
function resetFilter(filterName, skipApply = false) {
    // Helper function to clear input values
    const clearInputs = (selector) => {
        document.querySelectorAll(selector).forEach(input => input.value = '');
    };

    // Helper function to reset buttons
    const resetButtons = (containerSelector, defaultClass) => {
        const buttons = document.querySelectorAll(`${containerSelector} .filter-btn`);
        buttons.forEach(btn => btn.classList.remove('active'));

        const defaultButtons = document.querySelectorAll(`${containerSelector} .${defaultClass}`);
        defaultButtons.forEach(btn => btn.classList.add('active'));
    };

    // Helper function to update text content
    const updateText = (elementId, text) => {
        const element = document.getElementById(elementId);
        if (!element) {
            return; // Exit the function if the element is not found
        }
        element.textContent = text;
    };

    switch (filterName) {
        case 'property_types':
            document.querySelectorAll('input[name="property_types"]').forEach(input => input.checked = false);
            updateText('rch-property-type-text', 'Property Type');
            break;

        case 'price':
            clearInputs('.rch-price');
            updateText('rch-price-text-filter', 'Price');
            break;

        case 'beds':
            clearInputs('.rch-beds');
            updateText('rch-beds-text-filter', 'Beds');
            break;

        case 'baths':
            filters.minimum_bathrooms = '';
            resetButtons('.rch-main-beds', 'default-btn');
            updateText('rch-baths-text-filter', 'Baths');
            break;

        case 'parking':
            filters.minimum_parking_spaces = '';
            resetButtons('.rch-main-parking', 'default-btn');
            updateText('rch-parking-text-filter', 'Parking Spaces');
            break;

        case 'square':
            clearInputs('.rch-square');
            filters.minimum_square_meters = '';
            filters.maximum_square_meters = '';
            updateText('rch-footage-text-filter', 'Square Footage');
            break;

        case 'lot':
            clearInputs('.rch-lot');
            filters.minimum_lot_square_meters = '';
            filters.maximum_lot_square_meters = '';
            updateText('rch-lot-text-filter', 'Lot Size');
            break;

        case 'yearBuilt':
            clearInputs('.rch-year');
            updateText('rch-year-text-filter', 'Year Built');
            break;

        case 'open_house':
            // Uncheck open house checkboxes
            const mobileOpenHouse = document.getElementById('mobile_open_house');
            const desktopOpenHouse = document.getElementById('desktop_open_house');
            if (mobileOpenHouse) mobileOpenHouse.checked = false;
            if (desktopOpenHouse) desktopOpenHouse.checked = false;
            delete filters.open_house;
            break;

        case 'all':
            // Clear input search
            document.getElementById('content').value = '';
            filters.content = '';
            filters.points = '';
            // Reset listing statuses
            document.querySelectorAll('input[name="listing_statuses"]').forEach(input => input.checked = false);
            // Reset all individual filters without applying filters immediately
            resetFilter('property_types', true);
            resetFilter('price', true);
            resetFilter('beds', true);
            resetFilter('baths', true);
            resetFilter('parking', true);
            resetFilter('square', true);
            resetFilter('lot', true);
            resetFilter('yearBuilt', true);
            resetFilter('open_house', true);
            // Hide filter badge if exists
            const badge = document.getElementsByClassName('rch-filter-badge')[0];
            if (badge) {
                badge.style.display = 'none';
            }
            filters.postal_codes = [];
            break;

        default:
            console.warn(`Unknown filter: ${filterName}`);
    }

    // Only update active classes and apply filters if not skipped.
    if (!skipApply) {
        updateActiveClass();
        applyFilters();
    }
}



// Helper Function for Select Radio
let defaultOptionSet = false;

function normalizeValue(val) {
    return val.replace(/&amp;/g, '&').trim();
}

/**
 * Gets the value of the selected radio button or checkbox.
 * Handles setting default values from server data.
 * @param {string} name - Name attribute of the input elements.
 * @returns {Array} - Array of selected values.
 */
function getSelectedRadioCheckboxValue(name) {
    const serverData = filters.property_types;
    if (serverData && serverData.length > 0 && !defaultOptionSet) {
        const normalizedServerData = serverData.map(item => normalizeValue(item));
        const radioButtons = [...document.querySelectorAll(`input[name="${name}"]`)];
        let bestMatch = null;
        let bestMatchCount = 0;
        let allListingButton = null;
        let allListingValues = [];

        for (const button of radioButtons) {
            const buttonValues = button.value.split(',').map(v => normalizeValue(v));

            if (button.id === "all") {
                allListingButton = button;
                allListingValues = buttonValues;
                continue;
            }

            const matchCount = buttonValues.filter(value => normalizedServerData.includes(value)).length;

            if (matchCount > bestMatchCount) {
                bestMatch = button;
                bestMatchCount = matchCount;
            }
        }

        // Only select "All Listing" if all of its values are present in the server data
        if (allListingButton && allListingValues.every(value => normalizedServerData.includes(value))) {
            bestMatch = allListingButton;
        }
        if (bestMatch) {
            bestMatch.checked = true;
            const dataName = bestMatch.getAttribute('data-name');

            if (dataName) {
                document.getElementById('rch-property-type-text').textContent = dataName;
            }
        }

        defaultOptionSet = true;
    }

    const selected = document.querySelector(`input[name="${name}"]:checked`);
    if (selected) {
        const value = selected.value.split(',').map(v => normalizeValue(v));
        const dataName = selected.getAttribute('data-name');
        if (dataName) {
            document.getElementById('rch-property-type-text').textContent = dataName;
        }
        return value;
    }

    return [];
}

/**
 * Gets the values of checked checkboxes.
 * Handles setting default values from server data.
 * @param {string} name - Name attribute of the checkbox elements.
 * @returns {Array|string} - Array of selected values or empty string.
 */
let defaultOptionSetCheckbox = false;
function getSelectedCheckboxValues(name) {
    const serverData = filters.listing_statuses;

    if (serverData.length > 0 && !defaultOptionSetCheckbox) {

        const checkboxes = [...document.querySelectorAll(`input[name="${name}"]`)];
        let selectedCheckboxes = [];
        let allListingCheckbox = null;
        let allListingValues = [];

        for (const checkbox of checkboxes) {
            const checkboxValues = checkbox.value.split(',').map(v => normalizeValue(v));

            if (checkbox.id === "all") {
                allListingCheckbox = checkbox;
                allListingValues = checkboxValues;
                continue;
            }

            if (checkboxValues.some(value => serverData.includes(value))) {
                checkbox.checked = true;
                selectedCheckboxes.push(checkbox);
            }
        }

        // Select "All Listings" checkbox if all its values exist in server data
        if (allListingCheckbox && allListingValues.every(value => serverData.includes(value))) {
            allListingCheckbox.checked = true;
            selectedCheckboxes = [allListingCheckbox]; // Only select "All Listings"
        }

        defaultOptionSetCheckbox = true;
    }
    const selected = document.querySelector(`input[name="${name}"]:checked`);
    if (selected) {
        const value = selected.value.split(',').map(v => normalizeValue(v));
        return value;
    }
    return "";
}





// Helper function for Close all Options
function closeAllOptions() {
    document.querySelectorAll('.rch-inside-filters').forEach(optionsDiv => {
        if (optionsDiv.style.display === 'flex') {
            optionsDiv.style.display = 'none';
        }
    });
}

/**
 * Updates the active class for the "More Filters" container
 * based on whether any child filters are active.
 */
function updateMoreFiltersActiveClass() {
    // Find the main "More Filters" container (the one with .more-filter-text)
    const moreFiltersContainer = document.querySelector('.box-filter-listing .more-filter-text')?.closest('.box-filter-listing');
    
    if (!moreFiltersContainer) return;
    
    // Find all nested filter containers inside "More Filters"
    const nestedFilters = moreFiltersContainer.querySelectorAll('.rch-other-filter-listing .box-filter-listing');
    
    // Check if any nested filter has the active class
    let hasActiveFilter = false;
    nestedFilters.forEach(filter => {
        if (filter.classList.contains('active')) {
            hasActiveFilter = true;
        }
    });
    
    // Also check for active inputs/selects directly in the More Filters area
    const moreFiltersArea = moreFiltersContainer.querySelector('.rch-other-filter-listing');
    let hasActiveInput = false;
    
    if (moreFiltersArea) {
        // Check checkboxes
        if (moreFiltersArea.querySelector('input[type="checkbox"]:checked')) {
            hasActiveInput = true;
        }
        
        // Check text inputs with values
        if (!hasActiveInput) {
            const textInputs = moreFiltersArea.querySelectorAll('input[type="text"]');
            textInputs.forEach(input => {
                if (input.value && input.value.trim() !== '') {
                    hasActiveInput = true;
                }
            });
        }
        
        // Check select elements with non-empty values
        if (!hasActiveInput) {
            const selects = moreFiltersArea.querySelectorAll('select');
            selects.forEach(select => {
                if (select.value && select.value.trim() !== '') {
                    hasActiveInput = true;
                }
            });
        }
        
        // Check for active buttons (like parking buttons)
        if (!hasActiveInput && moreFiltersArea.querySelector('.filter-btn.active:not(.default-btn)')) {
            const activeBtn = moreFiltersArea.querySelector('.filter-btn.active:not(.default-btn)');
            if (activeBtn && activeBtn.dataset.value) {
                hasActiveInput = true;
            }
        }
    }
    
    if (hasActiveFilter || hasActiveInput) {
        moreFiltersContainer.classList.add('active');
    } else {
        moreFiltersContainer.classList.remove('active');
    }
}

/**
 * Updates CSS classes based on filter selections.
 * Adds 'active' class to containers with selected filters.
 */
function updateActiveClass() {
    // Add 'active' class for select inputs if the value is not empty
    document.querySelectorAll('input:not([type="search"]), select').forEach(element => {
        const updateClass = (element) => {
            const container = element.closest('.box-filter-listing');
            if (!container) return;

            if (
                (element.tagName === 'INPUT' &&
                    (element.type === 'checkbox' || element.type === 'radio' ? element.checked : element.value.trim() !== "")) ||
                (element.tagName === 'SELECT' && element.value.trim() !== "")
            ) {
                container.classList.add('active');
            } else {
                // Check if any other input or select inside this container is active
                const hasActiveElement = container.querySelector(
                    'input:checked, input:not([type="checkbox"]):not([type="radio"]):not([type="search"]):not(:placeholder-shown), select:not([value=""])'
                );

                if (!hasActiveElement) {
                    container.classList.remove('active');
                }
            }
            
            // Update "More Filters" parent container if this filter is inside it
            updateMoreFiltersActiveClass();
        };

        // Initial check on page load
        updateClass(element);
        // Listen for changes
        element.addEventListener('change', function () {
            updateClass(this);

        });

        // Handle typing for text inputs
        if (element.tagName === 'INPUT' && element.type !== 'checkbox' && element.type !== 'radio') {
            element.addEventListener('input', function () {
                updateClass(this);
            });
        }
    });

    // Add 'active' class for buttons inside filter lists
    document.querySelectorAll('.rch-bath-filter-listing .filter-btn, .rch-parking-filter-listing .filter-btn').forEach(button => {
        // Check if the button has a selected value (either from a default value or clicked)
        if (button.dataset.value) {
            // Add 'active' class to buttons that have a value
            if (button.checked || button.selected || button.classList.contains('active')) {
                button.closest('.box-filter-listing').classList.add('active');
            }
        }
        // Add event listener for click to toggle 'active' class
        button.addEventListener('click', function () {
            // Check if the dataset.value is not empty
            if (this.dataset.value) {
                this.closest('.box-filter-listing').classList.add('active');
            } else {
                // If empty value (like "Any"), check if other buttons in same container are active
                const container = this.closest('.box-filter-listing');
                const hasOtherActive = container.querySelector('.filter-btn.active:not([data-value=""])');
                if (!hasOtherActive) {
                    container.classList.remove('active');
                }
            }
            // Update More Filters container
            updateMoreFiltersActiveClass();
        });
    });

    // Remove 'active' class for reset buttons
    document.querySelectorAll('.reset-btn').forEach(button => {
        button.addEventListener('click', function () {
            this.closest('.box-filter-listing').classList.remove('active');
            // Update More Filters container if this reset is inside it
            updateMoreFiltersActiveClass();
        });
    });
    document.querySelectorAll('.reset-btn-all').forEach(button => {
        button.addEventListener('click', function () {
            // Select all elements with the class 'box-filter-listing'
            document.querySelectorAll('.box-filter-listing').forEach(box => {
                box.classList.remove('active'); // Remove 'active' class from each one
            });
        });
    });

}

/**
 * Toggles the visibility of the filters container.
 */
function toggleFilters() {
    const filtersContainer = document.getElementById('filters-container');
    filtersContainer.classList.toggle('show');
}

/**
 * Closes the filters container.
 */
function closeFilters() {
    const filtersContainer = document.getElementById('filters-container');
    filtersContainer.classList.remove('show');
    filtersContainer.classList.add('close');
}

function handlePriceChange() {
    updateDropdownTextGeneric("minimum_price", "maximum_price", "rch-price-text-filter", {
        prefix: "Price",
        formatNumbers: true, // Enable number formatting (e.g., "K", "M")
        fallbackMin: "Any",
        fallbackMax: "Any",
    });
    applyFilters();
}

/**
 * Handles bedroom filter changes.
 * Updates the display text and applies filters.
 * @param {string} value - Indicates which input changed ('min' or 'max').
 */
function handleBedsChange(value) {
    updateDropdownTextGeneric("minimum_bedrooms", "maximum_bedrooms", "rch-beds-text-filter", {
        prefix: "Beds",
        formatNumbers: false, // No number formatting
        fallbackMin: "Any",
        fallbackMax: "Any",
    });
    if (value == 'min') {
        updateMaxBedsOptions()
    }

    applyFilters();
}

function handleSquareChange() {
    updateDropdownTextGeneric("minimum_square_meters", "maximum_square_meters", "rch-footage-text-filter", {
        prefix: "Square Footage",
        formatNumbers: false,
        fallbackMin: "Any",
        fallbackMax: "Any"
    });
    applyFilters();
}

function handleLotChange() {
    updateDropdownTextGeneric("minimum_lot_square_meters", "maximum_lot_square_meters", "rch-lot-text-filter", {
        prefix: "Lot Size",
        formatNumbers: false,
        fallbackMin: "Any",
        fallbackMax: "Any"
    });
    applyFilters();
}

function handleYearChange() {
    updateDropdownTextGeneric("minimum_year_built", "maximum_year_built", "rch-year-text-filter", {
        formatNumbers: false, // No number formatting
        fallbackMin: "Any",
        fallbackMax: "Any",
    });
    applyFilters();
}
// Helper function for update label of filter
function updateDropdownTextGeneric(minSelectId, maxSelectId, textElementId, options = {}) {
    const {
        prefix = "", // Text prefix, e.g., "Beds" or "Price"
        formatNumbers = false, // Whether to format numbers with "K"/"M"
        fallbackMin = "Min", // Default text when minimum is not selected
        fallbackMax = "Max", // Default text when maximum is not selected
    } = options;

    // Helper function to format numbers if required
    function formatValue(value) {
        if (!value) return value === "" ? fallbackMin : fallbackMax; // Fallback for empty values
        return formatNumbers ? formatNumber(value) : value; // Format or return as is
    }

    // Get the dropdown elements
    const minSelect = document.getElementById(minSelectId);
    const maxSelect = document.getElementById(maxSelectId);
    const textElement = document.getElementById(textElementId);

    if (!minSelect || !maxSelect || !textElement) {

        return;
    }

    // Function to update the text content based on the selected values
    function updateTextContent() {
        if (!minSelect.value && !maxSelect.value) {
            textElement.textContent = prefix || fallbackMin;
            const closestBoxFilter = textElement.closest('.box-filter-listing');
            if (closestBoxFilter) {
                closestBoxFilter.classList.remove('active');
            }
            return; // Exit the function if both values are empty
        }

        // Get selected values and format them
        const minValue = formatValue(minSelect.value);
        const maxValue = formatValue(maxSelect.value);

        // Construct the text content
        const formattedMin = minValue !== fallbackMin ? `${minValue}` : minValue;
        const formattedMax = maxValue !== fallbackMax ? `${maxValue}` : fallbackMax; // Show fallbackMax when maxSelect is empty

        textElement.textContent = prefix ?
            `${prefix}: ${formattedMin} to ${formattedMax}` :
            `${formattedMin} to ${formattedMax}`;
    }

    // Initially update the text content
    updateTextContent();

    // Add event listener to minSelect for changes
    minSelect.addEventListener('change', updateTextContent);

    // Add event listener to maxSelect for changes
    maxSelect.addEventListener('change', updateTextContent);
}

// Helper function to format numbers with K/M
function formatNumber(value) {
    if (!value) return "Any";
    const num = parseInt(value, 10);
    if (isNaN(num)) return value;
    if (num >= 1_000_000) return `$${num / 1_000_000}M`; // Format millions
    if (num >= 1_000) return `$${num / 1_000}K`; // Format thousands
    return num.toString(); // Return as is for smaller numbers
}
updateActiveClass();

/**
 * Updates the filter badge count based on active filters.
 */
function updateFilterBadge() {
    const badge = document.getElementById('filter-badge');

    // Define filter conditions
    const filterConditions = [
        () => isAnyCheckboxChecked('.rch-status-filter-listing input[type="checkbox"]'),
        () => isAnyButtonActive('.rch-parking-filter-listing .filter-btn.active'),
        () => isInputValuePresent('minimum_square_meters') || isInputValuePresent('maximum_square_meters'),
        () => isInputValuePresent('minimum_lot_square_meters') || isInputValuePresent('maximum_lot_square_meters'),
        () => isInputValuePresent('minimum_year_built') || isInputValuePresent('maximum_year_built'),
        () => isInputValuePresent('postal_codes')
    ];

    // Calculate active filters
    const activeFilters = filterConditions.filter(condition => condition()).length;

    // Update the badge display
    updateBadgeDisplay(badge, activeFilters);
}

// Helper function: Check if any checkbox is checked
function isAnyCheckboxChecked(selector) {
    const checkboxes = document.querySelectorAll(selector);
    return Array.from(checkboxes).some(checkbox => checkbox.checked);
}

// Helper function: Check if any button is active
function isAnyButtonActive(selector) {
    const buttons = document.querySelectorAll(selector);
    return Array.from(buttons).some(button => button.dataset.value.trim() !== "");
}

// Helper function: Check if an input has a value
function isInputValuePresent(inputId) {
    const input = document.getElementById(inputId);
    return input && input.value.trim() !== "";
}

// Helper function: Update badge display
function updateBadgeDisplay(badge, activeFilters) {
    if (!badge) {
        console.error("Badge element not found.");
        return; // Exit the function if badge is null
    }

    if (activeFilters > 0) {
        badge.style.display = 'flex';
        badge.textContent = activeFilters;
    } else {
        badge.style.display = 'none';
    }
}

// Attach event listeners to filters
document.querySelectorAll('.rch-inside-filters input, .rch-inside-filters select').forEach(filter => {
    filter.addEventListener('change', updateFilterBadge);
});
document.querySelectorAll('.rch-other-inside-filters .filter-btn').forEach(filter => {
    filter.addEventListener('click', updateFilterBadge);
});

// Reset buttons should also update the badge
document.querySelectorAll('.reset-btn').forEach(resetButton => {
    resetButton.addEventListener('click', () => {
        updateFilterBadge();
    });
});
document.getElementById("toggle-map").addEventListener("change", function () {
    const map = document.getElementById("map");
    const listing = document.querySelector(".rch-under-main-listing");

    if (this.checked) {
        map.classList.remove("rch-map-hidden");
        listing.classList.remove("rch-expanded");
    } else {
        map.classList.add("rch-map-hidden");
        listing.classList.add("rch-expanded");
    }
});
// Call once to initialize on page load