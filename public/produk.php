<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>FoodCo Product Catalog</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Epilogue:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2E7D32",
                        "background-light": "#FAFAFA",
                        "background-dark": "#121212",
                        "secondary": "#616161",
                        "accent-yellow": "#FFCA28",
                        "accent-red": "#E53935",
                    },
                    fontFamily: {
                        "display": ["Epilogue", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.5rem",
                        "lg": "0.75rem",
                        "xl": "1rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        
        /* CSS tambahan untuk loading spinner */
        .loading-spinner {
            width: 2rem;
            height: 2rem;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #2E7D32;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-secondary">
<div class="relative flex h-auto min-h-screen w-full flex-col group/design-root overflow-x-hidden">
<div class="layout-container flex h-full grow flex-col">
<div class="flex flex-1 justify-center py-5">
<div class="layout-content-container flex flex-col w-full max-w-7xl px-4 md:px-8">
<header class="flex items-center justify-between whitespace-nowrap border-b border-gray-200 dark:border-gray-800 px-4 py-3">
<div class="flex items-center gap-8">
<div class="flex items-center gap-2 text-gray-900 dark:text-white">
<span class="material-symbols-outlined text-primary text-3xl">
                                    ramen_dining
                                </span>
<h2 class="text-xl font-bold leading-tight tracking-[-0.015em]">FoodCo</h2>
</div>
<div id="category-filters" class="hidden md:flex items-center gap-6">
</div>
</div>
<div class="flex flex-1 justify-end items-center gap-4">
<label class="relative flex-1 max-w-sm">
<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-gray-500">search</span>
</div>
<input class="form-input w-full min-w-0 flex-1 resize-none overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary h-10 placeholder:text-gray-500 pl-10 pr-4 text-sm font-normal leading-normal" placeholder="Search products..." value=""/>
</label>
<button class="flex-shrink-0 flex items-center justify-center rounded-full h-10 w-10 bg-gray-100 dark:bg-gray-800 text-secondary hover:bg-primary/20 dark:hover:bg-primary/20 hover:text-accent-red dark:hover:text-accent-red">
<span class="material-symbols-outlined text-xl">favorite_border</span>
</button>
</div>
</header>
<main class="flex-1">
<h1 class="text-gray-900 dark:text-white tracking-light text-2xl md:text-3xl font-bold leading-tight px-4 pb-3 pt-6">Our Products</h1>
<div class="flex gap-3 p-4 overflow-x-auto">
<button class="flex h-10 shrink-0 items-center justify-center gap-x-2 rounded-lg bg-gray-100 dark:bg-gray-800 px-4">
<p class="text-secondary text-sm font-medium leading-normal">Sort by Name</p>
<span class="material-symbols-outlined text-secondary">expand_more</span>
</button>
<button class="flex h-10 shrink-0 items-center justify-center gap-x-2 rounded-lg bg-gray-100 dark:bg-gray-800 px-4">
<p class="text-secondary text-sm font-medium leading-normal">Sort by Stock</p>
<span class="material-symbols-outlined text-secondary">expand_more</span>
</button>
<button class="flex h-10 shrink-0 items-center justify-center gap-x-2 rounded-lg bg-gray-100 dark:bg-gray-800 px-4">
<p class="text-secondary text-sm font-medium leading-normal">Filter by Type</p>
<span class="material-symbols-outlined text-secondary">expand_more</span>
</button>
</div>
<div id="product-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6 p-4">
    <!-- Product Cards will be dynamically loaded here -->
    <div class="flex justify-center items-center h-64 col-span-full">
        <div class="loading-spinner mr-3"></div>
        <span class="text-gray-600 dark:text-gray-400">Loading products...</span>
    </div>
</div>
<h2 class="text-gray-900 dark:text-white text-2xl font-bold leading-tight tracking-[-0.015em] px-4 pb-3 pt-8">You might also like</h2>
<div id="recommendation-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6 p-4">
    <div class="flex justify-center items-center h-64 col-span-full">
        <span class="text-gray-600 dark:text-gray-400">Recommendations will appear here...</span>
    </div>
</div>
</main>
<footer class="mt-16 border-t border-gray-200 dark:border-gray-800 py-8 px-4">
<div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
<div class="flex items-center gap-2 text-gray-900 dark:text-white">
<span class="material-symbols-outlined text-primary text-2xl">ramen_dining</span>
<h2 class="text-lg font-bold">FoodCo</h2>
</div>
<div class="flex items-center gap-6 text-sm font-medium">
<a class="hover:text-primary" href="#">About Us</a>
<a class="hover:text-primary" href="#">Contact</a>
<a class="hover:text-primary" href="#">Privacy Policy</a>
</div>
<div class="flex items-center gap-4 text-secondary">
<a class="hover:text-primary" href="#"><svg class="w-6 h-6" fill="currentColor" viewbox="0 0 24 24"><path d="M22.46,6C21.69,6.35 20.86,6.58 20,6.69C20.88,6.16 21.56,5.32 21.88,4.31C21.05,4.81 20.13,5.16 19.16,5.36C18.37,4.5 17.26,4 16,4C13.65,4 11.73,5.92 11.73,8.29C11.73,8.63 11.77,8.96 11.84,9.27C8.28,9.09 5.11,7.38 3,4.79C2.63,5.42 2.42,6.16 2.42,6.94C2.42,8.43 3.17,9.75 4.33,10.5C3.62,10.48 2.96,10.28 2.38,9.95C2.38,9.97 2.38,9.99 2.38,10.02C2.38,12.11 3.86,13.85 5.82,14.24C5.46,14.34 5.08,14.39 4.69,14.39C4.42,14.39 4.15,14.36 3.89,14.31C4.45,16.03 6.17,17.25 8.29,17.29C6.83,18.45 4.97,19.14 2.95,19.14C2.6,19.14 2.25,19.12 1.92,19.07C3.99,20.41 6.48,21.2 9.16,21.2C16,14.99 19.64,8.21 19.64,2.5C19.64,2.28 19.64,2.06 19.63,1.84C20.44,1.27 21.16,0.56 21.75, -0.25C21.04,0.07 20.27,0.32 19.47,0.45C20.31,-0.06 20.95,-0.82 21.27,-1.72C20.5,-1.39 19.66,-1.15 18.79,-0.97C18.03,-1.81 16.91,-2.33 15.67,-2.33C13.43,-2.33 11.61,-0.5 11.61,1.84C11.61,2.17 11.65,2.5 11.72,2.81C8.23,2.63 5.13,0.96 3.03,-1.59C2.67,-0.98 2.46,-0.25 2.46,0.52C2.46,1.98 3.2,3.27 4.33,4.04C3.65,4.02 3,3.82 2.43,3.5C2.43,3.51 2.43,3.53 2.43,3.55C2.43,5.58 3.88,7.28 5.8,7.67C5.45,7.77 5.08,7.82 4.7,7.82C4.43,7.82 4.16,7.79 3.9,7.74C4.45,9.41 6.13,10.58 8.2,10.63C6.79,11.75 4.97,12.41 2.98,12.41C2.64,12.41 2.3,12.39 1.96,12.34C4.01,13.65 6.46,14.42 9.09,14.42C15.89,8.51 19.53,1.9 19.53,-3.8C19.53,-4.02 19.53,-4.24 19.52,-4.46C20.31,-5 21.01,-5.69 21.6,-6.45L22.46,6Z" transform="translate(-1.5, 6.5)"></path></svg></a>
<a class="hover:text-primary" href="#"><svg class="w-6 h-6" fill="currentColor" viewbox="0 0 24 24"><path d="M12 2.04C6.5 2.04 2 6.53 2 12.06C2 17.06 5.66 21.21 10.44 21.96V14.96H7.9V12.06H10.44V9.85C10.44 7.32 11.93 5.96 14.22 5.96C15.31 5.96 16.45 6.15 16.45 6.15V8.62H15.19C13.95 8.62 13.56 9.39 13.56 10.18V12.06H16.34L15.89 14.96H13.56V21.96C18.34 21.21 22 17.06 22 12.06C22 6.53 17.5 2.04 12 2.04Z"></path></svg></a>
<a class="hover:text-primary" href="#"><svg class="w-6 h-6" fill="currentColor" viewbox="0 0 24 24"><path d="M7.8,2H16.2C19.4,2 22,4.6 22,7.8V16.2A5.8,5.8 0 0,1 16.2,22H7.8C4.6,22 2,19.4 2,16.2V7.8A5.8,5.8 0 0,1 7.8,2M7.6,4A3.6,3.6 0 0,0 4,7.6V16.4C4,18.39 5.61,20 7.6,20H16.4A3.6,3.6 0 0,0 20,16.4V7.6C20,5.61 18.39,4 16.4,4H7.6M17.25,5.5A1.25,1.25 0 0,1 18.5,6.75A1.25,1.25 0 0,1 17.25,8A1.25,1.25 0 0,1 16,6.75A1.25,1.25 0 0,1 17.25,5.5M12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9Z"></path></svg></a>
</div>
</div>
</footer>
</div>
</div>
</div>
</div>

<script>
let allProducts = []; // Store all products for filtering

document.addEventListener('DOMContentLoaded', function() {
    const productApiUrl = window.location.origin + '/_resto007/api/produk/list_produk.php';
    const categoryApiUrl = window.location.origin + '/_resto007/api/kategori_menu/list_kategori.php';

    // Fetch products and categories
    Promise.all([fetch(productApiUrl).then(res => res.json()), fetch(categoryApiUrl).then(res => res.json())])
        .then(([products, categories]) => {
            allProducts = products;
            displayProducts(products);
            displayRecommendations(products);
            displayCategoryFilters(categories);
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            document.getElementById('product-grid').innerHTML = '<div class="text-center py-8 col-span-full text-red-500">Failed to load products. Please check if the API is working properly.</div>';
        });

    // Setup search functionality
    const searchInput = document.querySelector('input[placeholder="Search products..."]');
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const filteredProducts = allProducts.filter(product => 
            product.nama_produk.toLowerCase().includes(searchTerm)
        );
        displayProducts(filteredProducts);
    });

    // Function to display category filters
    function displayCategoryFilters(categories) {
        const filtersContainer = document.getElementById('category-filters');
        if (!filtersContainer) return;

        filtersContainer.innerHTML = ''; // Clear static filters

        // Add 'All' filter
        const allButton = document.createElement('a');
        allButton.className = 'text-sm font-medium text-primary dark:text-primary'; // Active by default
        allButton.href = '#';
        allButton.textContent = 'All';
        filtersContainer.appendChild(allButton);

        // Add filters from API
        categories.forEach(category => {
            const categoryButton = document.createElement('a');
            categoryButton.className = 'text-sm font-medium hover:text-primary dark:hover:text-primary';
            categoryButton.href = '#';
            categoryButton.textContent = category;
            filtersContainer.appendChild(categoryButton);
        });

        // Add event listener for filtering
        filtersContainer.addEventListener('click', function(e) {
            e.preventDefault();
            if (e.target.tagName !== 'A') return;

            const selectedCategory = e.target.textContent;

            // Update active state
            Array.from(filtersContainer.children).forEach(child => {
                child.className = 'text-sm font-medium hover:text-primary dark:hover:text-primary';
            });
            e.target.className = 'text-sm font-medium text-primary dark:text-primary';

            // Filter products
            if (selectedCategory === 'All') {
                displayProducts(allProducts);
            } else {
                const filteredProducts = allProducts.filter(product => product.nama_kategori === selectedCategory);
                displayProducts(filteredProducts);
            }
        });
    }
    
    // Function to display products in the grid
    function displayProducts(products) {
        const productGrid = document.getElementById('product-grid');
        
        if (products.length > 0) {
            productGrid.innerHTML = '';
            products.forEach(product => {
                const productCard = createProductCard(product);
                productGrid.appendChild(productCard);
            });
        } else {
            productGrid.innerHTML = '<div class="text-center py-8 col-span-full text-gray-600 dark:text-gray-400">No products found for this category.</div>';
        }
    }
    
    // Function to display recommendation products
    function displayRecommendations(products) {
        const recommendationGrid = document.getElementById('recommendation-grid');
        
        if (products.length > 0) {
            const sampleSize = Math.min(5, products.length);
            const sampleProducts = [...products].sort(() => 0.5 - Math.random()).slice(0, sampleSize);
            
            recommendationGrid.innerHTML = '';
            
            sampleProducts.forEach(product => {
                const productCard = createProductCard(product, true);
                recommendationGrid.appendChild(productCard);
            });
        } else {
            recommendationGrid.innerHTML = '<div class="text-center py-8 col-span-full text-gray-600 dark:text-gray-400">No recommendations available</div>';
        }
    }
    
    // Function to create product cards
    function createProductCard(product, isRecommendation = false) {
        let stockStatus = '';
        let stockColor = '';
        
        if (product.stok > 5) {
            stockStatus = 'In Stock';
            stockColor = 'bg-green-500 text-green-600 dark:text-green-400';
        } else if (product.stok > 0) {
            stockStatus = 'Low Stock';
            stockColor = 'bg-accent-yellow text-amber-600 dark:text-amber-400';
        } else {
            stockStatus = 'Out of Stock';
            stockColor = 'bg-accent-red text-accent-red dark:text-red-400';
        }
        
        const formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(product.harga_jual);
        
        const card = document.createElement('div');
        card.className = 'flex flex-col gap-3 pb-3 rounded-lg overflow-hidden bg-white dark:bg-gray-900 shadow-sm hover:shadow-lg transition-shadow duration-300 group';
        
        const imageUrl = `images/${product.kode_produk}.jpg`;
        
        card.innerHTML = `
            <div class="relative w-full aspect-square">
                <div class="w-full h-full bg-center bg-no-repeat bg-cover" 
                     style="background-image: url('${imageUrl}');"
                     onerror="this.style.backgroundImage='url(images/noimage.jpg)'; this.onerror=null;">
                    <button class="absolute top-2 right-2 flex items-center justify-center h-8 w-8 rounded-full bg-white/70 dark:bg-black/50 text-secondary group-hover:text-accent-red transition-colors">
                        <span class="material-symbols-outlined">favorite_border</span>
                    </button>
                </div>
            </div>
            <div class="px-4 pb-4">
                <p class="text-gray-900 dark:text-white text-base font-semibold leading-normal">${product.nama_produk}</p>
                <p class="text-secondary text-sm font-normal leading-normal">${formattedPrice}</p>
                <div class="flex items-center gap-2 mt-1">
                    <span class="w-2.5 h-2.5 rounded-full ${stockColor.split(' ')[0]}"></span>
                    <p class="${stockColor.split(' ')[1]} text-sm font-medium leading-normal">${stockStatus}</p>
                </div>
            </div>
        `;
        return card;
    }
});
</script>
</body></html>