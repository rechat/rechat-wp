<div id="pagination" class="rch-listing-pagination">
    <script>
        function changePage(direction) {
            currentPage += direction; // Update current page
            updateListingList(); // Update the listing list based on current page
        }

        function updatePagination() {
            const pagination = document.getElementById('pagination'); // Get pagination element
            pagination.innerHTML = ''; // Clear existing pagination

            const prevIconPath = `<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>left-arrow.svg`; // Dynamic path for the previous icon
            const nextIconPath = `<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>right-arrow.svg`; // Dynamic path for the next icon

            if (totalPages > 1) {
                // Show "Previous" button if the current page is greater than 1
                if (currentPage > 1) {
                    const prevButton = document.createElement('button');
                    const prevIcon = document.createElement('img');
                    prevIcon.src = prevIconPath;
                    prevIcon.alt = 'Previous';
                    prevIcon.className = 'pagination-icon'; // Optional CSS class for styling
                    prevButton.appendChild(prevIcon);
                    prevButton.onclick = () => changePage(-1);
                    pagination.appendChild(prevButton);
                }

                // Generate page numbers dynamically
                for (let page = 1; page <= totalPages; page++) {
                    if (
                        page === 1 || // Always show the first page
                        page === totalPages || // Always show the last page
                        Math.abs(page - currentPage) <= 2 // Show 2 pages before and after the current page
                    ) {
                        const pageButton = document.createElement('button');
                        pageButton.textContent = page;
                        pageButton.className = page === currentPage ? 'active' : '';
                        pageButton.onclick = () => goToPage(page);
                        pagination.appendChild(pageButton);
                    } else if (
                        page === currentPage - 3 || // Add dots before skipped pages
                        page === currentPage + 3 // Add dots after skipped pages
                    ) {
                        const dots = document.createElement('span');
                        dots.textContent = '...';
                        pagination.appendChild(dots);
                    }
                }

                // Show "Next" button if the current page is less than the total pages
                if (currentPage < totalPages) {
                    const nextButton = document.createElement('button');
                    const nextIcon = document.createElement('img');
                    nextIcon.src = nextIconPath;
                    nextIcon.alt = 'Next';
                    nextIcon.className = 'pagination-icon'; // Optional CSS class for styling
                    nextButton.appendChild(nextIcon);
                    nextButton.onclick = () => changePage(1);
                    pagination.appendChild(nextButton);
                }
            }
        }


        function goToPage(page) {
            currentPage = page; // Update the current page
            updateListingList(); // Fetch the listings for the new page
        }
    </script>