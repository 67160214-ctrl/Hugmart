FROM php:8.2-apache

# ติดตั้ง Extension สำหรับเชื่อมฐานข้อมูล MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# เปิดใช้งาน Apache Rewrite สำหรับ REST API
RUN a2enmod rewrite

# คัดลอกซอร์สโค้ดเข้า Container
COPY . /var/www/html/

# กำหนด Permission โฟลเดอร์อัปโหลด
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/uploads

EXPOSE 80