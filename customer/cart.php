<?php
$pageTitle = 'Shopping Cart - QuickMed';
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8" x-data="cartManager()">
    <h1 class="text-3xl font-bold mb-8">Shopping Cart 🛒</h1>

    <div class="grid lg:grid-cols-3 gap-8">
        <!-- Cart Items -->
        <div class="lg:col-span-2">
            <div x-show="cartItems.length === 0" class="bg-white rounded-xl shadow-lg p-12 text-center">
                <div class="text-6xl mb-4">🛒</div>
                <p class="text-gray-600 text-xl mb-6">Your cart is empty</p>
                <a href="index.php" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700">
                    Start Shopping
                </a>
            </div>

            <div x-show="cartItems.length > 0" class="space-y-4">
                <template x-for="item in cartItems" :key="item.inventory_id">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex gap-4">
                            <img :src="'/quickmed/assets/images/uploads/products/' + item.image" 
                                 :alt="item.medicine_name"
                                 class="w-24 h-24 object-cover rounded"
                                 onerror="this.src='https://via.placeholder.com/100?text=Medicine'">
                            
                            <div class="flex-1">
                                <h3 class="font-bold text-lg" x-text="item.medicine_name"></h3>
                                <p class="text-gray-600 text-sm" x-text="item.generic_name"></p>
                                <p class="text-indigo-600 font-semibold text-sm" x-text="item.shop_name"></p>
                                
                                <div class="flex items-center gap-4 mt-3">
                                    <p class="text-2xl font-bold text-indigo-600" x-text="'৳' + item.selling_price"></p>
                                    
                                    <div class="flex items-center gap-2">
                                        <button @click="updateQuantity(item.inventory_id, -1)"
                                                class="bg-gray-200 w-8 h-8 rounded hover:bg-gray-300">-</button>
                                        <input type="number" 
                                               :value="item.quantity"
                                               @change="setQuantity(item.inventory_id, $event.target.value)"
                                               min="1"
                                               :max="item.available_stock"
                                               class="w-16 text-center border border-gray-300 rounded">
                                        <button @click="updateQuantity(item.inventory_id, 1)"
                                                :disabled="item.quantity >= item.available_stock"
                                                class="bg-gray-200 w-8 h-8 rounded hover:bg-gray-300 disabled:opacity-50">+</button>
                                    </div>
                                    
                                    <p class="text-sm text-gray-500" x-text="'Stock: ' + item.available_stock"></p>
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <p class="text-xl font-bold" x-text="'৳' + (item.selling_price * item.quantity).toFixed(2)"></p>
                                <button @click="removeItem(item.inventory_id)" 
                                        class="text-red-600 text-sm mt-2 hover:underline">Remove</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Order Summary -->
        <div x-show="cartItems.length > 0">
            <div class="bg-white rounded-xl shadow-lg p-6 sticky top-24">
                <h2 class="text-2xl font-bold mb-6">Order Summary</h2>
                
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal (<span x-text="cartItems.length"></span> items)</span>
                        <span class="font-semibold" x-text="'৳' + subtotal.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Delivery Charge</span>
                        <span class="font-semibold" x-text="'৳' + deliveryCharge.toFixed(2)"></span>
                    </div>
                    <div class="border-t pt-3 flex justify-between text-xl font-bold">
                        <span>Total</span>
                        <span class="text-indigo-600" x-text="'৳' + total.toFixed(2)"></span>
                    </div>
                </div>

                <a href="checkout.php" 
                   class="block w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold text-center hover:bg-indigo-700 transition">
                    Proceed to Checkout
                </a>

                <button @click="clearCart()" 
                        class="block w-full mt-3 border border-red-600 text-red-600 py-3 rounded-lg font-semibold hover:bg-red-50 transition">
                    Clear Cart
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function cartManager() {
    return {
        cartItems: [],
        subtotal: 0,
        deliveryCharge: 60,
        total: 0,

        async init() {
            await this.loadCart();
        },

        async loadCart() {
            const cart = JSON.parse(localStorage.getItem('quickmed_cart') || '[]');
            
            if (cart.length === 0) {
                this.cartItems = [];
                this.calculateTotal();
                return;
            }

            const inventoryIds = cart.map(item => item.inventory_id);

            try {
                const response = await fetch('/quickmed/api/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ inventory_ids: inventoryIds })
                });

                const data = await response.json();
                
                this.cartItems = data.items.map(item => {
                    const cartItem = cart.find(c => c.inventory_id === item.inventory_id);
                    return {
                        ...item,
                        quantity: Math.min(cartItem.quantity, item.available_stock)
                    };
                });

                this.calculateTotal();
            } catch (error) {
                console.error('Failed to load cart:', error);
            }
        },

        updateQuantity(inventoryId, change) {
            const item = this.cartItems.find(i => i.inventory_id === inventoryId);
            if (!item) return;

            const newQuantity = item.quantity + change;
            if (newQuantity < 1 || newQuantity > item.available_stock) return;

            item.quantity = newQuantity;
            this.saveCart();
            this.calculateTotal();
        },

        setQuantity(inventoryId, quantity) {
            const item = this.cartItems.find(i => i.inventory_id === inventoryId);
            if (!item) return;

            quantity = parseInt(quantity) || 1;
            item.quantity = Math.min(Math.max(quantity, 1), item.available_stock);
            this.saveCart();
            this.calculateTotal();
        },

        removeItem(inventoryId) {
            if (!confirm('Remove this item from cart?')) return;

            this.cartItems = this.cartItems.filter(item => item.inventory_id !== inventoryId);
            this.saveCart();
            this.calculateTotal();
            window.dispatchEvent(new Event('cartUpdated'));
        },

        clearCart() {
            if (!confirm('Clear entire cart?')) return;

            this.cartItems = [];
            this.saveCart();
            this.calculateTotal();
            window.dispatchEvent(new Event('cartUpdated'));
        },

        saveCart() {
            const cart = this.cartItems.map(item => ({
                inventory_id: item.inventory_id,
                quantity: item.quantity
            }));
            localStorage.setItem('quickmed_cart', JSON.stringify(cart));
        },

        calculateTotal() {
            this.subtotal = this.cartItems.reduce((sum, item) => 
                sum + (item.selling_price * item.quantity), 0
            );
            this.total = this.subtotal + this.deliveryCharge;
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>