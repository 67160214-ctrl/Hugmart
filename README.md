# Web Application E-Commerce & Microservices Integration

โปรเจกต์ระบบร้านค้าออนไลน์ (E-Commerce Web Application) สำหรับการเรียนรู้การรวมระบบ (System Integration) และสถาปัตยกรรมระบบ

---

## 📊 การประเมินผลงานตนเอง (Self-Assessment)
* **ความคืบหน้าภาพรวมของระบบ:** `85%` *(สามารถปรับเปลี่ยนตัวเลข % ได้ตามจริง)*

---

## 📌 สรุปสถานะการดำเนินงาน (Progress Report)

### ✅ ส่วนที่ดำเนินการเสร็จสิ้น (Completed)
* **ระบบยืนยันตัวตนและการเข้าสู่ระบบ (Authentication & User Management)**
  * ระบบสมัครสมาชิก, เข้าสู่ระบบ และจัดการ Session (`auth.php`, `logout.php`)
* **ระบบสำหรับผู้ซื้อ (Buyer Features)**
  * หน้ารายการสินค้าหลัก (`index.php`)[cite: 1]
  * ระบบจัดการตะกร้าสินค้า (`cart.php`)[cite: 1]
  * ระบบสั่งซื้อสินค้า (`checkout.php`)[cite: 1]
  * ระบบชำระเงิน (`payment.php`)[cite: 1]
* **ระบบสำหรับผู้ขาย (Seller Center & Dashboard)**
  * ระบบยื่นเรื่องสมัครเป็นผู้ขาย (`apply_seller.php`)[cite: 1]
  * ศูนย์จัดการข้อมูลผู้ขาย และ แดชบอร์ดสรุปยอด (`seller_center.php`, `seller_dashboard.php`)[cite: 1]
  * ระบบจัดการเพิ่มและแก้ไขรายการสินค้า (`add_product.php`, `edit_product.php`, `seller_add_product.php`, `seller_edit_product.php`)[cite: 1]

### ⏳ ส่วนที่ยังไม่เสร็จสิ้น / อยู่ระหว่างพัฒนา (Pending / In Progress)
* **ระบบตรวจสอบการชำระเงินฝั่งผู้ดูแลระบบ:** ระบบตรวจสอบและอนุมัติ QR Code สลิปโอนเงิน (`admin_qrcode.php`)[cite: 1]
* **Microservices Decoupling:** การปรับปรุงจุดเชื่อมต่อ API ระหว่าง Microservices ให้ทำงานเป็นอิสระต่อกันสมบูรณ์ยิ่งขึ้น
* **Data Validation & Security:** เพิ่มเติมการตรวจสอบข้อมูลฝั่ง Server-side เพื่อความปลอดภัยสูงสุด

---

## 🏗️ สถาปัตยกรรมระบบและเทคโนโลยี (Architecture & Tech Stack)

### 1. Microservices Architecture Diagram
![Microservices Architecture](docs/microservices-architecture.png)
> *หมายเหตุ: โปรดนำไฟล์รูปภาพ Diagram สถาปัตยกรรมไปวางไว้ในโฟลเดอร์ `docs/microservices-architecture.png`*

### 2. Technology Stack Diagram
![Technology Stack Diagram](docs/tech-stack.png)
> *หมายเหตุ: โปรดนำไฟล์รูปภาพ Diagram แสดง Tech Stack ไปวางไว้ในโฟลเดอร์ `docs/tech-stack.png`*

#### รายการเทคโนโลยีที่เลือกใช้ (Technology Stack Overview):
* **Backend Language:** PHP Source Files
* **Frontend:** HTML5, CSS3, JavaScript
* **Database:** MySQL
* **Web Server:** Apache / Nginx
* **Version Control:** Git & GitHub / GitLab

---

## 📁 โครงสร้างโปรเจกต์ (Project Structure)

```text
webapp/
├── assets/                  # ไฟล์สไตล์ (CSS), สคริปต์ (JS) และรูปภาพประกอบเว็บ[cite: 1]
├── config/                  # ไฟล์การเชื่อมต่อฐานข้อมูลและการตั้งค่าระบบ[cite: 1]
├── includes/                # ไฟล์ Header/Footer และฟังก์ชันส่วนกลาง[cite: 1]
├── uploads/                 # โฟลเดอร์เก็บไฟล์รูปภาพสินค้าที่อัปโหลด[cite: 1]
├── add_product.php          # หน้าจัดการเพิ่มสินค้า[cite: 1]
├── admin_qrcode.php         # ระบบจัดการ QR Code ฝั่งแอดมิน[cite: 1]
├── apply_seller.php         # หน้าลงทะเบียนผู้ขาย[cite: 1]
├── auth.php                 # ระบบล็อกอิน/ลงทะเบียน[cite: 1]
├── cart.php                 # หน้าตะกร้าสินค้า[cite: 1]
├── checkout.php             # หน้าชำระเงินและยืนยันคำสั่งซื้อ[cite: 1]
├── edit_product.php         # หน้าจัดการแก้ไขสินค้า[cite: 1]
├── index.php                # หน้าแรกของเว็บไซต์[cite: 1]
├── logout.php               # ออกจากระบบ[cite: 1]
├── payment.php              # หน้าแจ้งชำระเงิน[cite: 1]
├── seller_add_product.php   # หน้าเพิ่มสินค้าของผู้ขาย[cite: 1]
├── seller_center.php        # ศูนย์จัดการร้านค้า[cite: 1]
├── seller_dashboard.php     # สรุปผลการขายของผู้ขาย[cite: 1]
└── seller_edit_product.php  # หน้าแก้ไขสินค้าของผู้ขาย[cite: 1]
