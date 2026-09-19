# 🛒 E-Commerce System Integration & REST API

โปรเจกต์ระบบร้านค้าออนไลน์ (E-Commerce Web Application) พร้อมสถาปัตยกรรม Microservices และ Containerization ด้วย Docker

---

## 📊 1. การประเมินผลงานตนเอง (Self-Assessment)
* **ความคืบหน้าภาพรวม:** `85%` (เสร็จสิ้นระบบการทำงานหลักทั้งหมด)

---

## 📌 2. รายงานสรุปสถานะการทำงาน (Progress Report)

### ✅ ส่วนที่เสร็จสิ้น (Completed)
* **ระบบสิทธิ์และผู้ใช้:** สมัครสมาชิก, ล็อกอิน, ล็อกเอาต์ (`auth.php`, `logout.php`)[cite: 1]
* **ระบบสินค้า:** หน้ารายการสินค้า, เพิ่ม/แก้ไขสินค้า (`index.php`, `add_product.php`, `edit_product.php`)[cite: 1]
* **ระบบสั่งซื้อ:** ตะกร้าสินค้า, สั่งซื้อสินค้า, แจ้งชำระเงิน (`cart.php`, `checkout.php`, `payment.php`)[cite: 1]
* **ระบบผู้ขาย:** ลงทะเบียนผู้ขาย, แดชบอร์ดผู้ขาย, ศูนย์จัดการร้านค้า (`apply_seller.php`, `seller_center.php`, `seller_dashboard.php`)[cite: 1]
* **Docker Setup:** กำหนดไฟล์ `Dockerfile` และ `docker-compose.yml` สำหรับรัน Container

### ⏳ ส่วนที่ยังไม่เสร็จ (Pending)
* **ระบบแอดมิน:** ตรวจสอบอนุมัติสลิปชำระเงิน (`admin_qrcode.php`)[cite: 1]
* **Security & Validation:** เพิ่มระบบตรวจสอบความปลอดภัยของ API เพิ่มเติม

---

## 🏗️ 3. Architecture & Tech Stack Diagrams

### 3.1 Microservices Architecture Diagram
```mermaid
graph TD
    classDef client fill:#e1f5fe,stroke:#01579b,stroke-width:2px;
    classDef gateway fill:#fff3e0,stroke:#e65100,stroke-width:2px;
    classDef service fill:#e8f5e9,stroke:#2e7d32,stroke-width:2px;
    classDef db fill:#f3e5f5,stroke:#4a148c,stroke-width:2px;

    User[💻 Client / Browser]:::client
    Gateway[🌐 Apache Router / API Gateway]:::gateway

    subgraph Microservices Layer
        AuthSvc["🔐 Auth Service<br/>(auth.php, logout.php)"]:::service
        ProductSvc["📦 Product Service<br/>(index.php, add/edit_product.php)"]:::service
        CartOrderSvc["🛒 Cart & Order Service<br/>(cart.php, checkout.php)"]:::service
        PaymentSvc["💳 Payment Service<br/>(payment.php, admin_qrcode.php)"]:::service
        SellerSvc["🏪 Seller Service<br/>(seller_center.php, seller_dashboard.php)"]:::service
    end

    subgraph Storage Layer
        DB_Auth[("🗄️ Auth DB")]:::db
        DB_Product[("🗄️ Product DB")]:::db
        DB_Order[("🗄️ Order DB")]:::db
        DB_Payment[("🗄️ Payment DB")]:::db
        DB_Seller[("🗄️ Seller DB")]:::db
        Storage["📁 File Storage (/uploads)"]:::db
    end

    User --> Gateway
    Gateway --> AuthSvc
    Gateway --> ProductSvc
    Gateway --> CartOrderSvc
    Gateway --> PaymentSvc
    Gateway --> SellerSvc

    AuthSvc --> DB_Auth
    ProductSvc --> DB_Product
    ProductSvc --> Storage
    CartOrderSvc --> DB_Order
    PaymentSvc --> DB_Payment
    SellerSvc --> DB_Seller
```

### 3.2 Technology Stack Diagram
```mermaid
graph TB
    subgraph Frontend ["🎨 Frontend"]
        HTML["HTML5"]
        CSS["CSS3 (assets/)"]
        JS["JavaScript (assets/)"]
    end

    subgraph Backend ["⚙️ Backend"]
        PHP["PHP 8.2 Engine"]
        Config["Includes & Config Modules"]
    end

    subgraph Data ["🗄️ Data & Storage"]
        MySQL[("MySQL 8.0")]
        Uploads["Uploads Storage (/uploads)"]
    end

    subgraph Container ["🐳 Container & Tools"]
        Docker["Docker & Docker Compose"]
        Apache["Apache Web Server"]
        Git["Git Version Control"]
    end

    Frontend <--> Backend
    Backend <--> MySQL
    Backend --> Uploads
    Backend --- Container
```

---

## 📡 4. REST API Specification

### Authentication & User Management
* `POST /api/v1/auth/register` - สมัครสมาชิก
* `POST /api/v1/auth/login` - เข้าสู่ระบบ (`auth.php`)[cite: 1]
* `POST /api/v1/auth/logout` - ออกจากระบบ (`logout.php`)[cite: 1]
* `GET /api/v1/auth/me` - ดึงข้อมูลผู้ใช้ปัจจุบัน

### Product Management
* `GET /api/v1/products` - ดึงรายการสินค้าทั้งหมด (`index.php`)[cite: 1]
* `GET /api/v1/products/{id}` - ดึงรายละเอียดสินค้า
* `POST /api/v1/products` - เพิ่มสินค้าใหม่ (`add_product.php`)[cite: 1]
* `PUT /api/v1/products/{id}` - แก้ไขสินค้า (`edit_product.php`)[cite: 1]
* `DELETE /api/v1/products/{id}` - ลบสินค้า

### Cart & Orders
* `GET /api/v1/cart` - ดึงรายการในตะกร้า (`cart.php`)[cite: 1]
* `POST /api/v1/cart/items` - เพิ่มสินค้าลงตะกร้า
* `POST /api/v1/orders/checkout` - เช็กเอาต์สั่งซื้อ (`checkout.php`)[cite: 1]

### Seller System
* `POST /api/v1/sellers/apply` - สมัครเป็นผู้ขาย (`apply_seller.php`)[cite: 1]
* `GET /api/v1/sellers/dashboard` - ดูแดชบอร์ดร้านค้า (`seller_dashboard.php`)[cite: 1]
* `GET /api/v1/sellers/products` - รายการสินค้าในร้าน (`seller_center.php`)[cite: 1]

### Payments
* `POST /api/v1/payments/upload-slip` - แจ้งชำระเงิน (`payment.php`)[cite: 1]
* `GET /api/v1/admin/payments/qrcode` - [Admin] ดูสลิป QR Code (`admin_qrcode.php`)[cite: 1]

---

## 🚀 5. วิธีการรันโปรเจกต์ด้วย Docker

1. สั่งรัน Container:
   ```bash
   docker compose up -d --build
   ```
2. เข้าใช้งานระบบ:
   * **Web & REST API:** `http://localhost:8080`
   * **phpMyAdmin (จัดการฐานข้อมูล):** `http://localhost:8081` *(User: `root`, Pass: `rootpassword`)*
