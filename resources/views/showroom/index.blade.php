<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الكتالوج - @if ($mode === 'retail')
            التجزئة
        @else
            الجملة
        @endif - {{ $companySettings->company_name }}</title>

    <!-- TailwindCSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Cairo Font -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Image lightbox animation */
        .lightbox {
            animation: fadeIn 0.2s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen" x-data="showroomApp()"
    @@keydown.escape="closeLightbox()">

    <!-- Header -->
    <header class="bg-gradient-to-l from-blue-600 to-blue-700 text-white shadow-lg sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <!-- Company Logo/Name -->
                <div class="flex items-center gap-4">
                    @if ($companySettings->logo)
                        <img src="{{ Storage::url($companySettings->logo) }}" alt="{{ $companySettings->company_name }}"
                            class="h-12 md:h-16 object-contain">
                    @else
                        <h1 class="text-xl md:text-2xl font-bold">
                            {{ $companySettings->company_name }}
                        </h1>
                    @endif

                    <!-- Mode Badge -->
                    <span class="bg-white/20 px-3 py-1 rounded-full text-sm font-semibold backdrop-blur-sm">
                        @if ($mode === 'retail')
                            التجزئة
                        @else
                            الجملة
                        @endif
                    </span>
                </div>

                <!-- Company Contact -->
                <div class="hidden md:block text-left text-sm">
                    <p class="font-semibold">{{ $companySettings->company_phone }}</p>
                    <p class="opacity-90">{{ $companySettings->company_email }}</p>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="mt-4">
                <div class="relative">
                    <input type="text" x-model="searchQuery" @@input="filterProducts()"
                        placeholder="ابحث عن منتج بالاسم أو الكود..."
                        class="w-full px-4 py-3 pr-12 rounded-lg text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </header>

    <!-- WhatsApp Warning Banner -->
    @if (!$whatsappConfigured)
        <div class="bg-yellow-100 border-r-4 border-yellow-500 text-yellow-800 px-4 py-3 text-center">
            <p class="font-semibold">⚠️ رقم واتساب الأعمال غير مكوّن. يرجى إضافته من الإعدادات.</p>
        </div>
    @endif

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8 pb-32">
        <!-- No Results Message -->
        <div x-show="filteredProducts.length === 0" class="text-center py-12">
            <svg class="w-24 h-24 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="text-xl font-bold text-gray-700 mb-2">لا توجد منتجات مطابقة</h3>
            <p class="text-gray-500">جرب البحث بكلمات مختلفة</p>
        </div>

        <!-- Products Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <template x-for="product in filteredProducts" :key="product.id">
                <div
                    class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-2xl transition-shadow duration-300 transform hover:-translate-y-1">

                    <!-- Product Image -->
                    <div class="relative aspect-square bg-gray-100 cursor-pointer"
                        @@click="openLightbox(product.display_image, product.name)">
                        <template x-if="product.display_image">
                            <img :src="product.display_image" :alt="product.name" class="w-full h-full object-cover"
                                loading="lazy"
                                @@error="$el.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-gray-400\'><svg class=\'w-20 h-20\' fill=\'currentColor\' viewBox=\'0 0 20 20\'><path fill-rule=\'evenodd\' d=\'M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z\' clip-rule=\'evenodd\'/></svg></div>'">
                        </template>
                        <template x-if="!product.display_image">
                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                <svg class="w-20 h-20" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </template>

                        <!-- Stock Badge -->
                        <div
                            class="absolute top-3 left-3 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-bold shadow-lg">
                            متوفر: <span x-text="product.stock"></span>
                        </div>

                        <!-- Category Badge (if exists) -->
                        <template x-if="product.category_name">
                            <div
                                class="absolute top-3 right-3 bg-blue-500/90 text-white px-3 py-1 rounded-full text-xs font-semibold backdrop-blur-sm">
                                <span x-text="product.category_name"></span>
                            </div>
                        </template>
                    </div>

                    <!-- Product Info -->
                    <div class="p-5">
                        <!-- Product Name -->
                        <h3 class="text-lg font-bold text-gray-800 mb-2 line-clamp-2 min-h-[3.5rem]"
                            x-text="product.name"></h3>

                        <!-- Product Code -->
                        <div class="mb-4">
                            <span class="bg-gray-100 px-2 py-1 rounded text-xs text-gray-600"
                                x-text="'كود: ' + product.sku"></span>
                        </div>

                        <!-- Pricing (Retail Mode) -->
                        <template x-if="'{{ $mode }}' === 'retail'">
                            <div class="mb-4">
                                <p class="text-sm text-gray-600 mb-1">سعر التجزئة</p>
                                <p class="text-2xl font-bold text-blue-600" x-text="formatPrice(product.retail_price)">
                                </p>
                                <p class="text-xs text-gray-500" x-text="'لكل ' + product.small_unit_name"></p>
                            </div>
                        </template>

                        <!-- Pricing (Wholesale Mode) -->
                        <template x-if="'{{ $mode }}' === 'wholesale'">
                            <div class="mb-4">
                                <!-- Has Large Unit: Side by Side Display -->
                                <template x-if="product.large_unit_name">
                                    <div class="flex items-center gap-3">
                                        <!-- Small Unit -->
                                        <div class="flex-1">
                                            <p class="text-xs text-gray-600 mb-1" x-text="product.small_unit_name"></p>
                                            <p class="text-xl font-bold text-green-600"
                                                x-text="formatPrice(product.wholesale_price)"></p>
                                        </div>

                                        <!-- Separator -->
                                        <div class="text-gray-400 text-2xl font-light">|</div>

                                        <!-- Large Unit -->
                                        <div class="flex-1">
                                            <p class="text-xs text-gray-600 mb-1">
                                                <span x-text="product.large_unit_name"></span>
                                                <span class="text-blue-500 font-semibold" x-text="' (' + product.factor + ')'"></span>
                                            </p>
                                            <p class="text-xl font-bold text-green-600"
                                                x-text="formatPrice(product.large_wholesale_price)"></p>
                                        </div>
                                    </div>
                                </template>

                                <!-- No Large Unit: Single Price Display -->
                                <template x-if="!product.large_unit_name">
                                    <div>
                                        <p class="text-xs text-gray-600 mb-1" x-text="product.small_unit_name"></p>
                                        <p class="text-2xl font-bold text-green-600"
                                            x-text="formatPrice(product.wholesale_price)"></p>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Add to Cart Section -->
                        <div class="border-t pt-4">
                            <template x-if="!cart[product.id]">
                                <button @@click="addToCart(product)"
                                    class="w-full bg-gradient-to-l from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold py-3 px-4 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    إضافة للطلب
                                </button>
                            </template>

                            <template x-if="cart[product.id]">
                                <div class="flex items-center gap-3">
                                    <button @@click="decreaseQuantity(product.id)"
                                        class="flex-shrink-0 w-10 h-10 bg-red-500 hover:bg-red-600 text-white font-bold rounded-lg shadow transition-colors flex items-center justify-center">
                                        <span class="text-xl">-</span>
                                    </button>

                                    <div class="flex-1 text-center">
                                        <p class="text-2xl font-bold text-gray-800" x-text="cart[product.id]"></p>
                                        <p class="text-xs text-gray-500" x-text="product.small_unit_name"></p>
                                    </div>

                                    <button @@click="increaseQuantity(product.id)"
                                        class="flex-shrink-0 w-10 h-10 bg-green-500 hover:bg-green-600 text-white font-bold rounded-lg shadow transition-colors flex items-center justify-center">
                                        <span class="text-xl">+</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </main>

    <!-- Floating Cart Button -->
    <div x-show="cartItemsCount > 0"
        class="fixed bottom-0 left-0 right-0 bg-gradient-to-t from-white via-white to-transparent pt-6 pb-4 px-4 z-50"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full"
        x-transition:enter-end="opacity-1 translate-y-0">
        <div class="container mx-auto max-w-lg">
            <button @@click="sendWhatsAppOrder()" {{ $whatsappConfigured ? '' : 'disabled' }}
                class="w-full bg-gradient-to-l from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed text-white font-bold py-4 px-6 rounded-2xl shadow-2xl hover:shadow-3xl transition-all duration-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                    </svg>
                    <span class="text-lg">إرسال الطلب عبر واتساب</span>
                </div>
                <div class="bg-white text-green-600 font-bold px-3 py-1 rounded-full text-sm">
                    <span x-text="cartItemsCount"></span> منتج
                </div>
            </button>
        </div>
    </div>

    <!-- Image Lightbox -->
    <div x-show="lightbox.open" @@click="closeLightbox()"
        class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4 lightbox"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="relative max-w-4xl w-full">
            <button @@click.stop="closeLightbox()"
                class="absolute -top-12 left-0 text-white hover:text-gray-300 transition-colors">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <img :src="lightbox.image" :alt="lightbox.title" class="w-full h-auto rounded-lg shadow-2xl"
                @@click.stop>
            <p class="text-white text-center mt-4 text-lg font-semibold" x-text="lightbox.title"></p>
        </div>
    </div>

    <!-- Alpine.js App Logic -->
    <script>
        function showroomApp() {
            return {
                allProducts: {!! json_encode(
                    $products->map(function ($p) {
                            return [
                                'id' => $p->id,
                                'name' => $p->name,
                                'sku' => $p->sku,
                                'barcode' => $p->barcode,
                                'category_name' => optional($p->category)->name,
                                'small_unit_name' => optional($p->smallUnit)->name,
                                'large_unit_name' => optional($p->largeUnit)->name,
                                'factor' => $p->factor,
                                'retail_price' => $p->retail_price,
                                'wholesale_price' => $p->wholesale_price,
                                'large_retail_price' => $p->large_retail_price,
                                'large_wholesale_price' => $p->large_wholesale_price,
                                'stock' => $p->stock_movements_sum_quantity ?? 0,
                                'display_image' => $p->display_image,
                            ];
                        })->values(),
                ) !!},
                filteredProducts: [],
                searchQuery: '',
                cart: {},
                lightbox: {
                    open: false,
                    image: '',
                    title: ''
                },

                init() {
                    this.filteredProducts = this.allProducts;
                    // Load cart from localStorage
                    const savedCart = localStorage.getItem('showroom_cart_{{ $mode }}');
                    if (savedCart) {
                        this.cart = JSON.parse(savedCart);
                    }
                },

                filterProducts() {
                    const query = this.searchQuery.toLowerCase().trim();
                    if (!query) {
                        this.filteredProducts = this.allProducts;
                        return;
                    }

                    this.filteredProducts = this.allProducts.filter(p =>
                        p.name.toLowerCase().includes(query) ||
                        p.sku.toLowerCase().includes(query) ||
                        p.barcode.toLowerCase().includes(query) ||
                        (p.category_name && p.category_name.toLowerCase().includes(query))
                    );
                },

                addToCart(product) {
                    this.cart[product.id] = 1;
                    this.saveCart();
                },

                increaseQuantity(productId) {
                    this.cart[productId]++;
                    this.saveCart();
                },

                decreaseQuantity(productId) {
                    if (this.cart[productId] > 1) {
                        this.cart[productId]--;
                    } else {
                        delete this.cart[productId];
                    }
                    this.saveCart();
                },

                saveCart() {
                    localStorage.setItem('showroom_cart_{{ $mode }}', JSON.stringify(this.cart));
                },

                get cartItemsCount() {
                    return Object.keys(this.cart).length;
                },

                formatPrice(price) {
                    return parseFloat(price).toFixed(2);
                },

                openLightbox(image, title) {
                    if (!image) return;
                    this.lightbox = {
                        open: true,
                        image: image,
                        title: title
                    };
                },

                closeLightbox() {
                    this.lightbox.open = false;
                },

                sendWhatsAppOrder() {
                    if (Object.keys(this.cart).length === 0) {
                        alert('السلة فارغة!');
                        return;
                    }

                    const mode = '{{ $mode }}';
                    const modeLabel = mode === 'retail' ? 'التجزئة' : 'الجملة';
                    let message = `مرحباً، أود طلب المنتجات التالية من كتالوج ${modeLabel}:\n\n`;

                    Object.entries(this.cart).forEach(([productId, quantity]) => {
                        const product = this.allProducts.find(p => p.id === productId);
                        if (product) {
                            message += `• ${product.name}\n`;
                            message += `  الكمية: ${quantity} ${product.small_unit_name}\n`;

                            if (mode === 'retail') {
                                message +=
                                    `  السعر: ${this.formatPrice(product.retail_price)} × ${quantity} = ${this.formatPrice(product.retail_price * quantity)}\n`;
                            } else {
                                message += `  سعر الجملة: ${this.formatPrice(product.wholesale_price)}\n`;
                            }

                            message += `  الكود: ${product.sku}\n\n`;
                        }
                    });

                    message += `\nشكراً لكم.`;

                    const whatsappNumber = '{{ $companySettings->business_whatsapp_number }}';
                    const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;

                    window.open(whatsappUrl, '_blank');

                    // Optionally clear cart after sending
                    // this.cart = {};
                    // this.saveCart();
                }
            };
        }
    </script>
</body>

</html>
