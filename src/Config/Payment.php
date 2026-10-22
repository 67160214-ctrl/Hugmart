<?php
// src/Config/Payment.php

function paymentConfig() {
    return [
        // บัญชีกลาง Hugmart (SCB)
        'bank_name' => 'ไทยพาณิชย์ (SCB)',
        'bank_account_number' => '433-190275-9',
        'bank_account_name' => 'Hugmart Marketplace', // หรือชื่อบัญชีของคุณ
        
        // พร้อมเพย์ (ถ้ามีเบอร์หรือ PromptPay ID สามารถใส่ตรงนี้ได้ หรือเว้นว่างถ้าใช้ QR จากเลขบัญชี)
        'promptpay_id' => '4331902759', 
    ];
}