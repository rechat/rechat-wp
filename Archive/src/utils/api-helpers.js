import apiFetch from '@wordpress/api-fetch';

/**
 * Fetch data from WordPress REST API
 * @param {string} endpoint - API endpoint path
 * @param {Function} setState - State setter function
 */
export const fetchData = async (endpoint, setState) => {
    try {
        const data = await apiFetch({ path: endpoint });
        const options = data.map(item => ({
            label: item.title.rendered,
            value: item.id,
        }));
        options.unshift({ label: 'None', value: '' });
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
export const fetchDataWithMeta = async (path, setState) => {
    try {
        const data = await apiFetch({ path });
        setState([{ label: 'None', value: '' }, ...data.map(item => ({
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
export const fetchWPOption = async (optionKey) => {
    try {
        const options = await apiFetch({ path: '/wp/v2/options' });
        return options[optionKey] || null;
    } catch (error) {
        console.error(`Error fetching option ${optionKey}:`, error);
        return null;
    }
};
