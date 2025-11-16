<?php
$pageTitle = 'Checkout - QuickMed';
include 'includes/header.php';

// Get user addresses
$stmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE user_id = ? ORDER BY is_default DESC");
$stmt->execute([$_SESSION['user_id']]);
$addresses = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8" x-data="checkoutManager()">
    <h1 class="text-3xl font-bold mb-8">Checkout</h1>

    <div class="grid lg:grid-cols-3 gap-8">
        <!-- Checkout Form -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Delivery Address -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-4">Delivery Address</h2>
                
                <?php if (count($addresses) > 0): ?>
                    <div class="space-y-3 mb-4">
                        <?php foreach ($addresses as $addr): ?>
                            <label class="block border rounded-lg p-4 cursor-pointer hover:border-indigo-600 transition">
                                <input type="radio" 
                                       name="address" 
                                       value="<?= $addr['id'] ?>"
                                       x-model="selectedAddress"
                                       <?= $addr['is_default'] ? 'checked' : '' ?>
                                       class="mr-3">
                                <span class="font-semibold"><?= htmlspecialchars($addr['address_line']) ?></span>
                                <span class="text-gray-600">, <?= htmlspecialchars($addr['city']) ?> - <?= htmlspecialchars($addr['postal_code']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <button @click="showAddressForm = !showAddressForm" 
                        class="text-indigo-600 font-semibold hover:underline">
                    + Add New Address
                </button>

                <!-- New Address Form -->
                <div x-show="showAddressForm" x-cloak class="mt-4 space-y-3">
                    <input type="text" x-model="newAddress.address_line" placeholder="Address Line" 
                           class="w-full border border-gray-300 rounded-lg px-4 py-2">
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" x-model="newAddress.city" placeholder="City" 
                               class="border border-gray-300 rounded-lg px-4 py-2">
                        <input type="text" x-model="newAddress.postal_code" placeholder="Postal Code" 
                               class="border border-gray-300 rounded-lg px-4 py-2">
                    </div>
                    <button @click="saveAddress()" 
                            class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">
                        Save Address
                    </button>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-4">Payment Method</h2>
                
                <div class="space-y-3">
                    <label class="block border rounded-lg p-4 cursor-pointer hover:border-indigo-600">
                        <input type="radio" name="payment" value="cod" x-model="paymentMethod" checked class="mr-3">
                        <span class="font-semibold">Cash on Delivery</span>
                    </label>
                    <label class="block border rounded-lg p-4 cursor-pointer hover:border-indigo-600 opacity-50">
                        <input type="radio" name="payment" value="online" disabled class="mr-3">
                        <span class="font-semibold">Online Payment (Coming Soon)</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div>
            <div class="bg-white rounded-xl shadow-lg p-6 sticky top-24">
                <h2 class="text-2xl font-bold mb-4">Order Summary</h2>
                
                <div class="space-y-2 mb-4 max-h-64 overflow-y-auto">
                    <template x-for="item in cartItems" :key="item.inventory_id">
                        <div class="flex justify-between text-sm">
                            <span x-text="item.medicine_name + ' x' + item.quantity"></span>
                            <span x-text="'৳' + (item.selling_price * item.quantity).toFixed(2)"></span>
                        </div>
                    </template>
                </div>

                <div class="border-t pt-4 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-semibold" x-text="'৳' + subtotal.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Delivery</span>
                        <span class="font-semibold" x-text="'৳' + deliveryCharge.toFixed(2)"></span>
                    </div>
                    <div class="border-t pt-2 flex justify-between text-xl font-bold">
                        <span>Total</span>
                        <span class="text-indigo-600" x-text="'৳' + total.toFixed(2)"></span>
                    </div>
                </div>

                <button @click="placeOrder()" 
                        :disabled="!selectedAddress || loading"
                        class="w-full mt-6 bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!loading">Place Order</span>
                    <span x-show="loading">Processing...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function checkoutManager() {
    return {
        cartItems: [],
        selectedAddress: <?= $addresses[0]['id'] ?? 'null' ?>,
        paymentMethod: 'cod',
        showAddressForm: false,
        loading: false,
        newAddress: {
            address_line: '',
            city: '',
            postal_code: ''
        },
        subtotal: 0,
        deliveryCharge: 60,
        total: 0,

        async init() {
            await this.loadCart();
        },

        async loadCart() {
            const cart = JSON.parse(localStorage.getItem('quickmed_cart') || '[]');
            
            if (cart.length === 0) {
                window.location.href = 'index.php';
                return;
            }

            const inventoryIds = cart.map(item => item.inventory_id);

            const response = await fetch('/quickmed/api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ inventory_ids: inventoryIds })
            });

            const data = await response.json();
            
            this.cartItems = data.items.map(item => {
                const cartItem = cart.find(c => c.inventory_id === item.inventory_id);
                return { ...item, quantity: cartItem.quantity };
            });

            this.calculateTotal();
        },

        calculateTotal() {
            this.subtotal = this.cartItems.reduce((sum, item) => 
                sum + (item.selling_price * item.quantity), 0
            );
            this.total = this.subtotal + this.deliveryCharge;
        },

        async saveAddress() {
            if (!this.newAddress.address_line || !this.newAddress.city) {
                alert('Please fill all address fields');
                return;
            }

            const response = await fetch('/quickmed/api/save-address.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.newAddress)
            });

            const data = await response.json();

            if (data.success) {
                alert('Address saved successfully!');
                window.location.reload();
            } else {
                alert('Failed to save address');
            }
        },

        async placeOrder() {
            if (!this.selectedAddress) {
                alert('Please select a delivery address');
                return;
            }

            if (!confirm('Confirm order placement?')) return;

            this.loading = true;

            try {
                const response = await fetch('/quickmed/api/place-order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        items: this.cartItems.map(item => ({
                            inventory_id: item.inventory_id,
                            quantity: item.quantity
                        })),
                        address_id: this.selectedAddress,
                        payment_method: this.paymentMethod,
                        subtotal: this.subtotal,
                        delivery_charge: this.deliveryCharge,
                        total: this.total
                    })
                });

                const data = await response.json();

                if (data.success) {
                    localStorage.removeItem('quickmed_cart');
                    window.dispatchEvent(new Event('cartUpdated'));
                    alert('✅ Order placed successfully!');
                    window.location.href = 'order-success.php?order=' + data.order_number;
                } else {
                    alert('❌ Order failed: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                alert('❌ Order failed. Please try again.');
                console.error(error);
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>