// Conversion factor from square feet to square meters
const conversionFactor = 0.092903
// Function to convert square footage to square meters
function convertToSquareMeters(value) {
    return value * conversionFactor
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



function applyFilters() {
    // Update the filters with user inputs
    filters.property_types = getSelectedRadioCheckboxValue('property_types');
    filters.minimum_price = document.getElementById('minimum_price').value;
    filters.content = document.getElementById('content').value;
    filters.maximum_price = document.getElementById('maximum_price').value;
    filters.minimum_bedrooms = document.getElementById('minimum_bedrooms').value;
    filters.maximum_bedrooms = document.getElementById('maximum_bedrooms').value;
    filters.listing_statuses = getSelectedRadioCheckboxValue('listing_statuses');
    filters.minimum_year_built = document.getElementById('minimum_year_built').value;
    filters.maximum_year_built = document.getElementById('maximum_year_built').value;
    updateActiveClass();
    currentPage = 1; // Reset to the first page after applying filters
    updateListingList(); // Fetch filtered listings

}

// Add click event for toggling dropdown
// Function to close all dropdowns
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

document.addEventListener('DOMContentLoaded', () => {
    const currentYear = new Date().getFullYear();
    const priceOptions = generatePriceOptions();

    // Get all sets of min and max selects for price and year built
    const minPriceSelects = document.querySelectorAll('[id="minimum_price"]');
    const maxPriceSelects = document.querySelectorAll('[id="maximum_price"]');
    const minYearSelects = document.querySelectorAll('[id="minimum_year_built"]');
    const maxYearSelects = document.querySelectorAll('[id="maximum_year_built"]');

    // Initialize filters for each set of selects
    minPriceSelects.forEach((minPriceSelect, index) => {
        const maxPriceSelect = maxPriceSelects[index];
        initializeFilter(minPriceSelect, maxPriceSelect, generatePriceOptions, priceOptions, true);
    });

    minYearSelects.forEach((minYearSelect, index) => {
        const maxYearSelect = maxYearSelects[index];
        initializeFilter(minYearSelect, maxYearSelect, () => generateYearOptions(currentYear), [], false);
    });

    // Initialize a Generic Filter (Price or Year Built)
    function initializeFilter(minSelect, maxSelect, optionsGenerator, options, isPrice) {
        populateMinOptions(minSelect, optionsGenerator(), isPrice);
        updateMaxOptions(minSelect, maxSelect, optionsGenerator, isPrice);

        // Add event listener to update max options when min selection changes
        minSelect.addEventListener('change', () => updateMaxOptions(minSelect, maxSelect, optionsGenerator, isPrice));
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

    // General helper to populate min options
    function populateMinOptions(selectElement, options, isPrice) {
        const noMinOption = createOptionElement('', 'No Min');
        selectElement.appendChild(noMinOption);

        options.forEach(optionValue => {
            const formattedValue = isPrice ? formatNumber(optionValue) : optionValue; // Format only if price
            const option = createOptionElement(optionValue, formattedValue);
            selectElement.appendChild(option);
        });
    }

    // General helper to update max options based on the selected min value
    function updateMaxOptions(minSelect, maxSelect, optionsGenerator, isPrice) {
        const selectedMin = parseInt(minSelect.value) || 0;
        maxSelect.innerHTML = ''; // Clear existing options

        const noMaxOption = createOptionElement('', 'No Max');
        maxSelect.appendChild(noMaxOption);

        const options = optionsGenerator();
        options.forEach(optionValue => {
            if (optionValue >= selectedMin) {
                const formattedValue = isPrice ? formatNumber(optionValue) : optionValue; // Format only if price
                const option = createOptionElement(optionValue, formattedValue);
                maxSelect.appendChild(option);
            }
        });

        applyFilters(); // Assuming you have a function to apply filters
    }

    // Helper to create option elements
    function createOptionElement(value, textContent) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = textContent;
        return option;
    }
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
                filters.postal_codes = zipCodesArray
                applyFilters();
            };

            tag.appendChild(removeBtn);
            tagsContainer.appendChild(tag);
        });
    };

    // Event listener for input
    input.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.keyCode === 13) {
            e.preventDefault();
            const value = input.value.trim();

            // Add the ZIP code to the array if it's not empty and not already added
            if (value && !zipCodesArray.includes(value)) {
                zipCodesArray.push(value);
                updateTagsUI();
            }
            filters.postal_codes = zipCodesArray
            // Clear the input field
            input.value = "";
            applyFilters();
        }
    });
});

//Helper function for setting buttons
function setupFilterButtons(containerSelector, filterKey, textElementId, prefix = "") {
    const textElement = document.getElementById(textElementId);

    if (!textElement) {
        console.error("Invalid text element ID provided.");
        return;
    }

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

            // If selectedValue is empty, remove the 'active' class from the closest '.box-filter-listing'
            if (!selectedValue) {
                const closestBoxFilter = button.closest('.box-filter-listing');
                if (closestBoxFilter) {
                    closestBoxFilter.classList.remove('active');
                }
            }

            // Call applyFilters to apply the filter and update the listings
            applyFilters();
        });
    });

}

//Helper function for reset
function resetFilter(filterName) {
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

        case 'all':
            // Clear input search
            document.getElementById('content').value = '';
            filters.content = '';
            // Reset listing statuses
            document.querySelectorAll('input[name="listing_statuses"]').forEach(input => input.checked = false);
            // Reset all filters
            resetFilter('property_types');
            resetFilter('price');
            resetFilter('beds');
            resetFilter('baths');
            resetFilter('parking');
            resetFilter('square');
            resetFilter('lot');
            resetFilter('yearBuilt');
            const badge = document.getElementsByClassName('rch-filter-badge')[0];
            if (badge) {
                badge.style.display = 'none';
            }
            filters.postal_codes = []

            break;

        default:
            console.warn(`Unknown filter: ${filterName}`);
    }

    updateActiveClass();
    applyFilters();
}


// Helper Function for Select Radio
function getSelectedRadioCheckboxValue(name) {
    const selected = document.querySelector(`input[name="${name}"]:checked`);
    if (selected) {
        const value = selected.value.split(','); // Get the value and split it into an array
        const dataName = selected.getAttribute('data-name'); // Get the data-name attribute
        if (dataName) {
            document.getElementById('rch-property-type-text').textContent = dataName;
        }
        return value
    }

    return [];
}
// Helper function for Close all Options
function closeAllOptions() {
    document.querySelectorAll('.rch-inside-filters').forEach(optionsDiv => {
        if (optionsDiv.style.display === 'flex') {
            optionsDiv.style.display = 'none';
        }
    });
}
// Helper function to add or remove "active" class based on filter selections
function updateActiveClass() {
    // Add 'active' class for select inputs if the value is not empty
    document.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', function () {
            if (this.value.trim() !== "") {
                this.closest('.box-filter-listing').classList.add('active');
            } else {
                this.closest('.box-filter-listing').classList.remove('active');
            }
        });
    });

    // Add 'active' class for input fields if the value is not empty
    document.querySelectorAll('input:not([type="search"])').forEach(input => {
        input.addEventListener('change', function () {
            if (this.value.trim() !== "") {
                this.closest('.box-filter-listing').classList.add('active');
            } else {
                this.closest('.box-filter-listing').classList.remove('active');
            }
        });
    });

    // Add 'active' class for buttons inside filter lists
    document.querySelectorAll('.filter-btn').forEach(button => {
        button.addEventListener('click', function () {
            // Check if the dataset.value is not empty
            if (this.dataset.value) {
                this.closest('.box-filter-listing').classList.add('active');
            }
        });
    });

    // Remove 'active' class for reset buttons
    document.querySelectorAll('.reset-btn').forEach(button => {
        button.addEventListener('click', function () {
            this.closest('.box-filter-listing').classList.remove('active');
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


function toggleFilters() {
    const filtersContainer = document.getElementById('filters-container');
    filtersContainer.classList.toggle('show');
}

// Close the filters container
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
        fallbackMin = "No Min", // Default text when minimum is not selected
        fallbackMax = "No Max", // Default text when maximum is not selected
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
        console.error("Invalid element IDs provided.");
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

// Call once to initialize on page load