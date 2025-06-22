## 🛠️ Installation Guide

### 🔹 **Step 1: Clone the Repository**
```sh
git clone this git
cd to project path folder
```

### 🔹 **Step 2: Install Dependencies**
```sh
composer install
npm install
```

### 🔹 **Step 3: Environment Setup**
```sh
cp .env.example .env
php artisan key:generate
```
Update `.env` with database credentials.

### 🔹 **Step 4: Database Configuration**
```sh
Import `database/db_ecommerce_dummy.sql` into your database manually (if needed).
```

### 🔹 **Step 5: Setup Storage**
```sh
php artisan storage:link
```

### 🔹 **Step 6: Run the Application**
```sh
php artisan serve
```
🔗 Open `http://localhost:8000`

### **Admin Login Credentials:**
📧 **Email:** `admin@gmail.com`  
🔑 **Password:** `1111`

### **NOTE:**
`THIS PROJECT USE OSS WHATSAPP API from https://github.com/aldinokemal/go-whatsapp-web-multidevice/tree/main/docs`  