# 🛒 E-Commerce System Integration & REST API

โปรเจกต์ระบบร้านค้าออนไลน์ (E-Commerce Web Application) พร้อมสถาปัตยกรรม Microservices และ Containerization ด้วย Docker

---

## 📊 1. การประเมินผลงานตนเอง (Self-Assessment)
* **ความคืบหน้าภาพรวม:** `90%` (จากทั้งหมด 100%)
* **สถานะปัจจุบัน:** พัฒนาระบบงานหลักทั้งหมดเรียบร้อยแล้ว ติดตั้ง Container Environment และตั้งค่าฐานข้อมูลเรียบร้อย พร้อมสำหรับการทดสอบและใช้งาน

---

## 📌 2. รายงานสรุปสถานะการทำงาน (Progress Report)

### ✅ ส่วนที่เสร็จสิ้น (Completed)
* **ระบบสิทธิ์และผู้ใช้:** สมัครสมาชิก, ล็อกอิน, ล็อกเอาต์ (`auth.php`, `logout.php`)
* **ระบบสินค้า:** หน้ารายการสินค้า, เพิ่ม/แก้ไขสินค้า (`index.php`, `add_product.php`, `edit_product.php`)
* **ระบบสั่งซื้อ:** ตะกร้าสินค้า, สั่งซื้อสินค้า, แจ้งชำระเงิน (`cart.php`, `checkout.php`, `payment.php`)
* **ระบบผู้ขาย:** ลงทะเบียนผู้ขาย, แดชบอร์ดผู้ขาย, ศูนย์จัดการร้านค้า (`apply_seller.php`, `seller_center.php`, `seller_dashboard.php`)
* **Docker Setup:** กำหนดไฟล์ `Dockerfile` และ `docker-compose.yml` สำหรับรัน Web API, MySQL และ phpMyAdmin

### ⏳ ส่วนที่ยังไม่เสร็จ (Pending)
* **ระบบแอดมิน:** ตรวจสอบอนุมัติสลิปชำระเงิน (`admin_qrcode.php`)
* **Security & Validation:** ปรับปรุงมาตรการตรวจสอบข้อมูลเข้า (Input Validation) และความปลอดภัยของ API Endpoint เพิ่มเติม

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

### 3.2 Technology Stack Diagram (Integrated with Services Architecture)
```mermaid
graph TB
    subgraph ClientLayer ["💻 1. Client & Presentation Layer"]
        UI["HTML5 / CSS3 / JavaScript"]
        Assets["Static Assets (/assets)"]
    end

    subgraph WebGatewayLayer ["🌐 2. Web Server & Gateway Layer"]
        Apache["Apache 2.4 Web Server"]
        Htaccess["Rewrite Rules (.htaccess / Router)"]
    end

    subgraph AppServiceLayer ["⚙️ 3. PHP Runtime & Microservice Handlers"]
        PHPRuntime["PHP 8.x Engine (PDO MySQL)"]
        subgraph Handlers ["Service Modules"]
            AuthModule["Auth Handler"]
            ProductModule["Product Handler"]
            OrderModule["Cart & Order Handler"]
            PaymentModule["Payment Handler"]
            SellerModule["Seller Handler"]
        end
    end

    subgraph DataStorageLayer ["🗄️ 4. Data & Persistence Layer"]
        MySQL[("MySQL 8.0 Engine")]
        DatabaseSchema["Schema: s67160214"]
        UploadStorage["File Storage (/uploads)"]
    end

    subgraph ContainerInfra ["🐳 5. Infrastructure & Orchestration"]
        DockerEngine["Docker Engine"]
        Compose["Docker Compose Orchestrator"]
        PMA["phpMyAdmin (Database Tool)"]
    end

    ClientLayer --> WebGatewayLayer
    WebGatewayLayer --> AppServiceLayer
    PHPRuntime --> Handlers
    Handlers --> DataStorageLayer
    MySQL --- DatabaseSchema
    PaymentModule & ProductModule --> UploadStorage

    ContainerInfra -.- WebGatewayLayer
    ContainerInfra -.- AppServiceLayer
    ContainerInfra -.- DataStorageLayer
```

---

## 📡 4. REST API Specification

### Authentication & User Management
* `POST /api/v1/auth/register` - สมัครสมาชิก
* `POST /api/v1/auth/login` - เข้าสู่ระบบ (`auth.php`)
* `POST /api/v1/auth/logout` - ออกจากระบบ (`logout.php`)
* `GET /api/v1/auth/me` - ดึงข้อมูลผู้ใช้ปัจจุบัน

### Product Management
* `GET /api/v1/products` - ดึงรายการสินค้าทั้งหมด (`index.php`)
* `GET /api/v1/products/{id}` - ดึงรายละเอียดสินค้า
* `POST /api/v1/products` - เพิ่มสินค้าใหม่ (`add_product.php`)
* `PUT /api/v1/products/{id}` - แก้ไขสินค้า (`edit_product.php`)
* `DELETE /api/v1/products/{id}` - ลบสินค้า

### Cart & Orders
* `GET /api/v1/cart` - ดึงรายการในตะกร้า (`cart.php`)
* `POST /api/v1/cart/items` - เพิ่มสินค้าลงตะกร้า
* `POST /api/v1/orders/checkout` - เช็กเอาต์สั่งซื้อ (`checkout.php`)

### Seller System
* `POST /api/v1/sellers/apply` - สมัครเป็นผู้ขาย (`apply_seller.php`)
* `GET /api/v1/sellers/dashboard` - ดูแดชบอร์ดร้านค้า (`seller_dashboard.php`)
* `GET /api/v1/sellers/products` - รายการสินค้าในร้าน (`seller_center.php`)

### Payments
* `POST /api/v1/payments/upload-slip` - แจ้งชำระเงิน (`payment.php`)
* `GET /api/v1/admin/payments/qrcode` - [Admin] ดูสลิป QR Code (`admin_qrcode.php`)

---

## 🚀 5. วิธีการรันโปรเจกต์ด้วย Docker

1. **สั่งรัน Container ทั้งหมด:**
   ```bash
   docker compose up -d --build
   ```

2. **หากต้องการล้างข้อมูลฐานข้อมูลและรันระบบใหม่ทั้งหมด:**
   ```bash
   docker compose down -v
   docker compose up -d --build
   ```

3. **ช่องทางการเข้าใช้งาน:**
   * **Web Application / REST API:** [http://localhost:8080](http://localhost:8080)
   * **phpMyAdmin:** [http://localhost:8081](http://localhost:8081)

4. **ข้อมูลการเชื่อมต่อฐานข้อมูล (Database Connection Credentials):**
   * **Host:** `db`
   * **Database Name:** `s67160214`
   * **Username:** `s67160214`
   * **Password:** `W5xPdkn9`
   * **Root Password:** `rootpassword`
   * **MySQL Internal Port:** `3306`
