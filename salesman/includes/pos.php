<?php
$pageTitle = 'POS System - QuickMed';
include 'includes/header.php';

// Get categories
$categories = $pdo->query("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container mx-auto px-4 py-6" x-data="posSystem()">
    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Left: Product Selection -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Search & Filter -->
            <div class="bg-white rounded-xl shadow-lg p-4">
                <div class="grid md:grid-cols-3 gap-3">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <input type="text" 
                               x-model="searchQuery"
                               @input.debounce.300ms="searchProducts()"
                               placeholder="Search medicine by name, generic, or company..."
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- Category Filter -->
                    <select x-model="selectedCategory" @change="searchProducts()"
                            class="px-4 py-3 border border-gray-300 rounded-lg">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="font-bold text-lg mb-4">Available Products</h3>
                
                <div x-show="loading" class="text-center py-12">
                    <div class="text-4xl mb-2">⏳</div>
                    <p class="text-gray-600">Loading products...</p>
                </div>

                <div x-show="!loading && products.length === 0" class="text-center py-12">
                    <div class="text-4xl mb-2">🔍</div>
                    <p class="text-gray-600">No products found</p>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-[600px] overflow-y-auto">
                    <template x-for="product in products" :key="product.id">
                        <div @click="addToCart(product)" 
                             class="border rounded-lg p-3 cursor-pointer hover:border-indigo-600 hover:shadow-lg transition">
                            <img :src="'/quickmed/assets/images/uploads/products/' + product.image" 
                                 :alt="product.medicine_name"
                                 class="w-full h-24 object-cover rounded mb-2"
                                 onerror="this.src='https://via.placeholder.com/150?text=Medicine'">
                            <p class="font-semibold text-sm truncate" x-text="product.medicine_name"></p>
                            <p class="text-xs text-gray-600 truncate" x-text="product.generic_name"></p>
                            <div class="flex justify-between items-center mt-2">
                                <p class="text-indigo-600 font-bold" x-text="'৳' + product.selling_price"></p>
                                <p class="text-xs" :class="product.quantity < 10 ? 'text-red-600' : 'text-green-600'"
                                   x-text="'Stock: ' + product.quantity"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Right: Cart & Billing -->
        <div class="space-y-4">
            <!-- Customer Search -->
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="font-bold mb-3">Customer (Optional)</h3>
                <div class="relative" x-data="{ customerSearch: '', customerResults: [] }">
                    <input type="text" 
                           x-model="customerSearch"
                           @input.debounce.300ms="searchCustomer(customerSearch)"
                           placeholder="Search by email or phone..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    
                    <div x-show="customerResults.length > 0" 
                         class="absolute w-full bg-white mt-1 rounded-lg shadow-xl max-h-48 overflow-y-auto z-10">
                        <template x-for="customer in customerResults" :key="customer.id">
                            <div @click="selectCustomer(customer); customerResults = []" 
                                 class="px-4 py-2 hover:bg-gray-100 cursor-pointer">
                                <p class="font-semibold text-sm" x-text="customer.name"></p>
                                <p class="text-xs text-gray-600" x-text="customer.email"></p>
                            </div>
                        </template>
                    </div>
                </div>
                
                <div x-show="selectedCustomer" class="mt-3 p-3 bg-indigo-50 rounded-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-semibold" x-text="selectedCustomer?.name"></p>
                            <p class="text-sm text-gray-600" x-text="'Points: ' + selectedCustomer?.points"></p>
                        </div>
                        <button @click="selectedCustomer = null" class="text-red-600 text-sm">Remove</button>
                    </div>
                </div>
            </div>

            <!-- Cart -->
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="font-bold mb-3">Cart Items (<span x-text="cart.length"></span>)</h3>
                
                <div x-show="cart.length === 0" class="text-center py-8 text-gray-500">
                    <p class="text-4xl mb-2">🛒</p>
                    <p class="text-sm">Cart is empty</p>
                </div>

                <div class="space-y-2 max-h-64 overflow-y-auto">
                    <template x-for="(item, index) in cart" :key="index">
                        <div class="border rounded-lg p-2">
                            <div class="flex justify-between items-start mb-2">
                                <p class="font-semibold text-sm flex-1" x-text="item.medicine_name"></p>
                                <button @click="removeFromCart(index)" class="text-red-600 ml-2">✕</button>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <button @click="updateQuantity(index, -1)" 
                                            class="bg-gray-200 w-6 h-6 rounded text-sm hover:bg-gray-300">-</button>
                                    <input type="number" 
                                           :value="item.quantity"
                                           @change="setQuantity(index, $event.target.value)"
                                           min="1"
                                           :max="item.available_stock"
                                           class="w-12 text-center border border-gray-300 rounded text-sm py-1">
                                    <button @click="updateQuantity(index, 1)" 
                                            :disabled="item.quantity >= item.available_stock"
                                            class="bg-gray-200 w-6 h-6 rounded text-sm hover:bg-gray-300 disabled:opacity-50">+</button>
                                </div>
                                <p class="font-bold text-indigo-600" x-text="'৳' + (item.selling_price * item.quantity).toFixed(2)"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Bill Summary -->
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="font-bold mb-3">Bill Summary</h3>
                
                <div class="space-y-2 mb-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-semibold" x-text="'৳' + subtotal.toFixed(2)"></span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">VAT (%)</span>
                        <input type="number" x-model="vatPercent" @input="calculate()" min="0" max="100" step="0.5"
                               class="w-20 text-right border border-gray-300 rounded px-2 py-1">
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">VAT Amount</span>
                        <span x-text="'৳' + vatAmount.toFixed(2)"></span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Discount (৳)</span>
                        <input type="number" x-model="discount" @input="calculate()" min="0" :max="subtotal"
                               class="w-20 text-right border border-gray-300 rounded px-2 py-1">
                    </div>
                    
                    <div class="border-t pt-2 flex justify-between text-xl font-bold">
                        <span>Total</span>
                        <span class="text-indigo-600" x-text="'৳' + total.toFixed(2)"></span>
                    </div>
                </div>

                <div class="space-y-3">
                    <select x-model="paymentMethod" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="mobile_banking">Mobile Banking</option>
                    </select>

                    <button @click="completeSale()" 
                            :disabled="cart.length === 0 || processing"
                            class="w-full bg-green-600 text-white py-3 rounded-lg font-bold hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!processing">💰 Complete Sale</span>
                        <span x-show="processing">Processing...</span>
                    </button>

                    <button @click="clearCart()" 
                            class="w-full border border-red-600 text-red-600 py-2 rounded-lg hover:bg-red-50">
                        Clear Cart
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Receipt (Hidden) -->
    <div id="printArea" class="hidden">
        <!-- Will be populated dynamically -->
    </div>
</div>

<script>
function posSystem() {
    return {
        products: [],
        cart: [],
        selectedCustomer: null,
        customerResults: [],
        searchQuery: '',
        selectedCategory: '',
        loading: false,
        processing: false,
        
        subtotal: 0,
        vatPercent: 5,
        vatAmount: 0,
        discount: 0,
        total: 0,
        paymentMethod: 'cash',

        async init() {
            await this.searchProducts();
        },

        async searchProducts() {
            this.loading = true;
            try {
                const response = await fetch(`/quickmed/api/pos-products.php?shop_id=<?= $salesman['shop_id'] ?>&q=${this.searchQuery}&category=${this.selectedCategory}`);
                const data = await response.json();
                this.products = data.products || [];
            } catch (error) {
                console.error('Failed to load products:', error);
            } finally {
                this.loading = false;
            }
        },

        async searchCustomer(query) {
            if (query.length < 3) {
                this.customerResults = [];
                return;
            }

            const response = await fetch(`/quickmed/api/search-customer.php?q=${query}`);
            const data = await response.json();
            this.customerResults = data.customers || [];
        },

        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.customerSearch = '';
        },

        addToCart(product) {
            const existing = this.cart.find(item => item.inventory_id === product.id);
            
            if (existing) {
                if (existing.quantity < product.quantity) {
                    existing.quantity++;
                } else {
                    alert('Maximum stock reached!');
                }
            } else {
                this.cart.push({
                    inventory_id: product.id,
                    medicine_name: product.medicine_name,
                    selling_price: parseFloat(product.selling_price),
                    quantity: 1,
                    available_stock: product.quantity
                });
            }
            
            this.calculate();
        },

        removeFromCart(index) {
            this.cart.splice(index, 1);
            this.calculate();
        },

        updateQuantity(index, change) {
            const item = this.cart[index];
            const newQty = item.quantity + change;
            
            if (newQty < 1 || newQty > item.available_stock) return;
            
            item.quantity = newQty;
            this.calculate();
        },

        setQuantity(index, value) {
            const item = this.cart[index];
            const qty = parseInt(value) || 1;
            item.quantity = Math.min(Math.max(qty, 1), item.available_stock);
            this.calculate();
        },

        calculate() {
            this.subtotal = this.cart.reduce((sum, item) => sum + (item.selling_price * item.quantity), 0);
            this.vatAmount = (this.subtotal * parseFloat(this.vatPercent)) / 100;
            this.total = this.subtotal + this.vatAmount - parseFloat(this.discount);
        },

        clearCart() {
            if (this.cart.length === 0) return;
            if (confirm('Clear entire cart?')) {
                this.cart = [];
                this.calculate();
            }
        },

        async completeSale() {
            if (this.cart.length === 0) {
                alert('Cart is empty!');
                return;
            }

            if (!confirm(`Complete sale of ৳${this.total.toFixed(2)}?`)) return;

            this.processing = true;

            try {
                const response = await fetch('/quickmed/api/pos-complete-sale.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        items: this.cart,
                        customer_id: this.selectedCustomer?.id || null,
                        subtotal: this.subtotal,
                        vat: this.vatAmount,
                        discount: this.discount,
                        total: this.total,
                        payment_method: this.paymentMethod
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('✅ Sale completed successfully!');
                    
                    // Print receipt
                    this.printReceipt(data.sale);
                    
                    // Reset
                    this.cart = [];
                    this.selectedCustomer = null;
                    this.discount = 0;
                    this.calculate();
                } else {
                    alert('❌ Sale failed: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                alert('❌ Sale failed. Please try again.');
                console.error(error);
            } finally {
                this.processing = false;
            }
        },

        printReceipt(sale) {
            const printContent = `
                <div style="width: 300px; font-family: monospace; padding: 20px;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;">QuickMed</h2>
                        <p style="margin: 5px 0;"><?= htmlspecialchars($salesman['shop_name']) ?></p>
                        <p style="margin: 0; font-size: 12px;">INVOICE</p>
                    </div>
                    
                    <div style="border-top: 2px dashed #000; border-bottom: 2px dashed #000; padding: 10px 0; margin: 10px 0;">
                        <p style="margin: 5px 0;"><strong>Invoice:</strong> ${sale.invoice_number}</p>
                        <p style="margin: 5px 0;"><strong>Date:</strong> ${new Date().toLocaleString()}</p>
                        <p style="margin: 5px 0;"><strong>Cashier:</strong> <?= htmlspecialchars($salesman['name']) ?></p>
                    </div>
                    
                    <table style="width: 100%; margin: 10px 0; font-size: 12px;">
                        ${this.cart.map(item => `
                            <tr>
                                <td colspan="3" style="padding: 5px 0;">${item.medicine_name}</td>
                            </tr>
                            <tr>
                                <td style="padding-bottom: 10px;">${item.quantity} x ৳${item.selling_price.toFixed(2)}</td>
                                <td></td>
                                <td style="text-align: right; padding-bottom: 10px;">৳${(item.quantity * item.selling_price).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </table>
                    
                    <div style="border-top: 2px dashed #000; padding-top: 10px; margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                            <span>Subtotal:</span>
                            <span>৳${this.subtotal.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                            <span>VAT (${this.vatPercent}%):</span>
                            <span>৳${this.vatAmount.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                            <span>Discount:</span>
                            <span>৳${this.discount.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin: 10px 0; font-size: 16px; font-weight: bold;">
                            <span>TOTAL:</span>
                            <span>৳${this.total.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                            <span>Payment:</span>
                            <span>${this.paymentMethod.toUpperCase()}</span>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px; font-size: 12px;">
                        <p>Thank you for your purchase!</p>
                        <p>Visit again</p>
                    </div>
                </div>
            `;
            
            const printArea = document.getElementById('printArea');
            printArea.innerHTML = printContent;
            printArea.classList.remove('hidden');
            
            window.print();
            
            setTimeout(() => {
                printArea.classList.add('hidden');
            }, 100);
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>