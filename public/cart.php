<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/Config/Database.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=' . urlencode('กรุณาเข้าสู่ระบบก่อนดูรถเข็น'));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$userId = $_SESSION['user_id'];

// ดึงรายการสินค้าในรถเข็นของผู้ใช้ แยกตามร้านค้า
try {
    $stmt = $pdo->prepare("
        SELECT 
            ci.id AS cart_item_id,
            ci.quantity,
            p.*,
            sp.store_name
        FROM carts c
        JOIN cart_items ci ON c.id = ci.cart_id
        JOIN products p ON ci.product_id = p.id
        LEFT JOIN seller_profiles sp ON p.seller_id = sp.id
        WHERE c.user_id = ?
        ORDER BY sp.id, ci.created_at DESC
    ");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // จัดกลุ่มสินค้าตามร้านค้า
    $groupedItems = [];
    foreach ($items as $item) {
        $sellerName = $item['store_name'] ?: 'ร้านค้าทั่วไป';
        $groupedItems[$sellerName][] = $item;
    }
} catch (PDOException $e) {
    error_log('[Hugmart cart] ' . $e->getMessage());
    $groupedItems = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รถเข็นสินค้า | Hugmart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex flex-col min-h-screen font-sans">

    <!-- Header / Navbar -->
    <header class="bg-white shadow-sm border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="index.php" class="text-3xl font-extrabold text-[#1B4332] tracking-tight">
                    Hugmart
                </a>
                <span class="text-xl font-medium text-gray-700 border-l-2 border-gray-300 pl-4">
                    รถเข็นสินค้า
                </span>
            </div>
            <a href="index.php" class="text-sm text-[#2D6A4F] hover:underline flex items-center gap-1">
                <i class="fa-solid fa-store"></i> เลือกซื้อสินค้าต่อ
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <?php if (empty($groupedItems)): ?>
            <!-- ตะกร้าว่างเปล่า -->
            <div class="bg-white rounded-lg shadow p-12 text-center my-8">
                <div class="w-24 h-24 bg-green-50 text-[#2D6A4F] rounded-full flex items-center justify-center mx-auto mb-4 text-4xl">
                    <i class="fa-solid fa-cart-flatbed"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">ไม่มีสินค้าในรถเข็นของคุณ</h3>
                <p class="text-gray-500 text-sm mb-6">เลือกดูสินค้าและเพิ่มลงในรถเข็นได้เลย!</p>
                <a href="index.php" class="inline-block bg-[#2D6A4F] hover:bg-[#1B4332] text-white font-medium px-8 py-3 rounded-md transition shadow">
                    เลือกช้อปสินค้า
                </a>
            </div>
        <?php else: ?>

            <!-- หัวตารางแสดงรายการ -->
            <div class="bg-white rounded-lg shadow mb-4 p-4 text-sm font-medium text-gray-600 hidden md:grid grid-cols-12 gap-4 items-center">
                <div class="col-span-6 flex items-center space-x-3">
                    <input type="checkbox" id="selectAllHeader" class="w-4 h-4 accent-[#2D6A4F] cursor-pointer" onclick="toggleSelectAll(this)">
                    <span>สินค้า</span>
                </div>
                <div class="col-span-2 text-center">ราคาต่อชิ้น</div>
                <div class="col-span-2 text-center">จำนวน</div>
                <div class="col-span-1 text-center">ราคารวม</div>
                <div class="col-span-1 text-center">แอคชั่น</div>
            </div>

            <!-- รายการสินค้าแยกตามร้านค้า (Shopee Style) -->
            <?php foreach ($groupedItems as $storeName => $products): ?>
                <div class="store-group bg-white rounded-lg shadow mb-4 overflow-hidden">
                    <!-- ชื่อร้านค้า -->
                    <div class="border-b p-4 bg-gray-50 flex items-center space-x-3">
                        <input type="checkbox" class="store-checkbox w-4 h-4 accent-[#2D6A4F] cursor-pointer" onclick="toggleStoreGroup(this)">
                        <span class="bg-[#2D6A4F] text-white text-xs px-2 py-0.5 rounded">ร้านค้า</span>
                        <span class="font-bold text-gray-800 text-sm flex items-center gap-2">
                            <i class="fa-solid fa-shop text-[#2D6A4F]"></i> <?php echo htmlspecialchars($storeName); ?>
                        </span>
                    </div>

                    <!-- รายการสินค้าในร้าน -->
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($products as $p):
                            $pName  = $p['name'] ?? $p['product_name'] ?? 'สินค้าไม่มีชื่อ';
                            $pStock = intval($p['stock'] ?? $p['product_stock'] ?? 0);
                        ?>
                            <div class="p-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-center item-row"
                                 data-id="<?php echo $p['cart_item_id']; ?>"
                                 data-price="<?php echo $p['price']; ?>"
                                 data-stock="<?php echo $pStock; ?>">
                                <!-- ข้อมูลสินค้า + Checkbox -->
                                <div class="md:col-span-6 flex items-center space-x-4">
                                    <input type="checkbox" class="item-checkbox w-4 h-4 accent-[#2D6A4F] cursor-pointer" onchange="calculateTotal()">
                                    <a href="product.php?id=<?php echo $p['id']; ?>">
                                        <img src="<?php echo htmlspecialchars($p['image_url'] ?: 'assets/images/no-image.png'); ?>" class="w-20 h-20 object-cover rounded border border-gray-200">
                                    </a>
                                    <div>
                                        <a href="product.php?id=<?php echo $p['id']; ?>" class="text-sm font-medium text-gray-800 line-clamp-2 hover:text-[#2D6A4F]"><?php echo htmlspecialchars($pName); ?></a>
                                    </div>
                                </div>

                                <!-- ราคาต่อชิ้น -->
                                <div class="md:col-span-2 text-left md:text-center">
                                    <span class="text-xs text-gray-400 md:hidden">ราคา: </span>
                                    <span class="text-sm font-semibold text-gray-700">฿<?php echo number_format($p['price'], 2); ?></span>
                                </div>

                                <!-- ปุ่มเพิ่ม-ลดจำนวน -->
                                <div class="md:col-span-2 flex items-center justify-start md:justify-center">
                                    <div class="flex items-center border border-gray-300 rounded overflow-hidden">
                                        <button type="button" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600" onclick="updateQty(this, -1)">-</button>
                                        <input type="number" value="<?php echo $p['quantity']; ?>" min="1" class="w-12 text-center text-sm py-1 focus:outline-none qty-input" onchange="onQtyInput(this)">
                                        <button type="button" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600" onclick="updateQty(this, 1)">+</button>
                                    </div>
                                </div>

                                <!-- ราคารวมรายการนี้ -->
                                <div class="md:col-span-1 text-left md:text-center">
                                    <span class="text-xs text-gray-400 md:hidden">รวม: </span>
                                    <span class="text-sm font-bold text-[#2D6A4F] item-total">฿<?php echo number_format($p['price'] * $p['quantity'], 2); ?></span>
                                </div>

                                <!-- ปุ่มลบ -->
                                <div class="md:col-span-1 text-right md:text-center">
                                    <button type="button" onclick="removeItem(this)" class="text-gray-400 hover:text-red-500 text-sm transition">
                                        <i class="fa-regular fa-trash-can"></i> ลบ
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- แถบคำนวณราคารวมและปุ่มสั่งสินค้า (Sticky ด้านล่างเหมือน Shopee) -->
            <div class="sticky bottom-0 bg-white shadow-lg border rounded-lg p-4 flex flex-col md:flex-row items-center justify-between gap-4 z-40">
                <div class="flex items-center space-x-4 w-full md:w-auto">
                    <input type="checkbox" id="selectAllFooter" class="w-4 h-4 accent-[#2D6A4F] cursor-pointer" onclick="toggleSelectAll(this)">
                    <label for="selectAllFooter" class="text-sm cursor-pointer select-none">เลือกทั้งหมด (<span id="selectedCount">0</span>)</label>
                    <button type="button" onclick="removeSelected()" class="text-xs text-gray-500 hover:text-red-500">ลบสินค้าที่เลือก</button>
                </div>

                <div class="flex items-center space-x-6 w-full md:w-auto justify-end">
                    <div class="text-right">
                        <span class="text-xs text-gray-500 block">รวมการสั่งซื้อ:</span>
                        <span class="text-2xl font-bold text-[#2D6A4F]" id="grandTotal">฿0.00</span>
                    </div>
                    <button type="button" onclick="proceedToCheckout()" class="bg-[#2D6A4F] hover:bg-[#1B4332] text-white font-bold px-8 py-3 rounded shadow transition duration-200 uppercase tracking-wide text-sm">
                        สั่งซื้อสินค้า
                    </button>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="bg-gray-100 py-6 border-t text-center text-xs text-gray-500 mt-auto">
        <p>© 2026 Hugmart Marketplace. All Rights Reserved.</p>
    </footer>

    <script>
    const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token']); ?>;

    // คิวบันทึกจำนวนสินค้า (กดต่อเนื่องแล้วไม่ตีกัน และรอให้เสร็จก่อนไปหน้าชำระเงิน)
    let pendingSaves = Promise.resolve();

    async function cartApi(payload) {
        const res = await fetch('cart_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify(payload)
        });
        return res.json();
    }

    function formatBaht(n) {
        return '฿' + n.toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function updateRowTotal(row) {
        const price = parseFloat(row.dataset.price);
        const qty = parseInt(row.querySelector('.qty-input').value) || 1;
        row.querySelector('.item-total').innerText = formatBaht(price * qty);
    }

    // สลับเลือกทั้งหมด
    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll('.item-checkbox, .store-checkbox, #selectAllHeader, #selectAllFooter');
        checkboxes.forEach(cb => cb.checked = master.checked);
        calculateTotal();
    }

    // สลับเลือกทั้งร้านค้า
    function toggleStoreGroup(storeCb) {
        const container = storeCb.closest('.store-group');
        const itemCbs = container.querySelectorAll('.item-checkbox');
        itemCbs.forEach(cb => cb.checked = storeCb.checked);
        calculateTotal();
    }

    // บันทึกจำนวนสินค้าของแถวนั้นลงฐานข้อมูล
    function saveQty(row) {
        const input = row.querySelector('.qty-input');
        let qty = Math.max(1, parseInt(input.value) || 1);
        input.value = qty;
        updateRowTotal(row);
        calculateTotal();

        pendingSaves = pendingSaves
            .then(() => cartApi({action: 'update', cart_item_id: row.dataset.id, quantity: qty}))
            .then(data => {
                if (!data.ok) {
                    alert(data.message || 'ไม่สามารถบันทึกจำนวนสินค้าได้');
                    return;
                }
                if (data.quantity !== qty) {
                    input.value = data.quantity;
                    updateRowTotal(row);
                    calculateTotal();
                    alert('สินค้านี้มีในสต็อกเพียง ' + data.quantity + ' ชิ้น');
                }
            })
            .catch(() => alert('ไม่สามารถบันทึกจำนวนสินค้าได้ กรุณาลองใหม่อีกครั้ง'));

        return pendingSaves;
    }

    // ปรับจำนวนสินค้า (+ / -)
    function updateQty(btn, delta) {
        const row = btn.closest('.item-row');
        const input = row.querySelector('.qty-input');
        let current = (parseInt(input.value) || 1) + delta;
        if (current < 1) current = 1;
        input.value = current;
        saveQty(row);
    }

    // พิมพ์จำนวนเอง
    function onQtyInput(input) {
        saveQty(input.closest('.item-row'));
    }

    // คำนวณราคารวมทั้งหมด
    function calculateTotal() {
        let total = 0;
        let count = 0;

        document.querySelectorAll('.item-row').forEach(row => {
            const cb = row.querySelector('.item-checkbox');
            if (cb && cb.checked) {
                const price = parseFloat(row.dataset.price);
                const qty = parseInt(row.querySelector('.qty-input').value) || 1;
                total += price * qty;
                count++;
            }
        });

        document.getElementById('grandTotal').innerText = formatBaht(total);
        document.getElementById('selectedCount').innerText = count;
    }

    // ลบแถวสินค้าออกจากหน้าจอ และลบกลุ่มร้านที่ว่างแล้ว
    function removeRows(rows) {
        rows.forEach(row => row.remove());
        document.querySelectorAll('.store-group').forEach(group => {
            if (group.querySelectorAll('.item-row').length === 0) {
                group.remove();
            }
        });
        if (document.querySelectorAll('.item-row').length === 0) {
            window.location.reload();
            return;
        }
        calculateTotal();
    }

    async function deleteRows(rows) {
        const ids = rows.map(r => parseInt(r.dataset.id));
        try {
            await pendingSaves;
            const data = await cartApi({action: 'remove', cart_item_ids: ids});
            if (!data.ok) {
                alert(data.message || 'ไม่สามารถลบสินค้าได้');
                return;
            }
            removeRows(rows);
        } catch (e) {
            alert('ไม่สามารถลบสินค้าได้ กรุณาลองใหม่อีกครั้ง');
        }
    }

    // ลบสินค้า
    function removeItem(btn) {
        if (confirm('คุณต้องการลบสินค้านี้ออกจากรถเข็นหรือไม่?')) {
            deleteRows([btn.closest('.item-row')]);
        }
    }

    // ลบรายการที่เลือก
    function removeSelected() {
        const selected = document.querySelectorAll('.item-checkbox:checked');
        if (selected.length === 0) {
            alert('กรุณาเลือกอย่างน้อย 1 รายการเพื่อลบ');
            return;
        }
        if (confirm('คุณต้องการลบสินค้าที่เลือกทั้งหมดใช่หรือไม่?')) {
            deleteRows(Array.from(selected).map(cb => cb.closest('.item-row')));
        }
    }

    // ไปยังหน้าชำระเงิน (Payment) พร้อมรายการที่เลือก
    async function proceedToCheckout() {
        const selected = document.querySelectorAll('.item-checkbox:checked');
        if (selected.length === 0) {
            alert('กรุณาเลือกสินค้าอย่างน้อย 1 ชิ้นเพื่อชำระเงิน');
            return;
        }
        const ids = Array.from(selected).map(cb => cb.closest('.item-row').dataset.id);
        await pendingSaves; // รอให้บันทึกจำนวนล่าสุดเสร็จก่อน
        window.location.href = 'payment.php?items=' + encodeURIComponent(ids.join(','));
    }
    </script>
</body>
</html>