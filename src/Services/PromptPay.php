<?php
/**
 * สร้างข้อความ PromptPay QR (EMVCo Merchant-Presented Mode)
 * รองรับ เบอร์มือถือ 10 หลัก / เลขบัตรประชาชนหรือเลขผู้เสียภาษี 13 หลัก / e-Wallet ID 15 หลัก
 */

function promptPayCrc16(string $data): string
{
    $crc = 0xFFFF; // CRC-16/CCITT-FALSE: poly 0x1021, init 0xFFFF
    $len = strlen($data);
    for ($i = 0; $i < $len; $i++) {
        $crc ^= (ord($data[$i]) << 8);
        for ($b = 0; $b < 8; $b++) {
            if ($crc & 0x8000) {
                $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
            } else {
                $crc = ($crc << 1) & 0xFFFF;
            }
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

function promptPayTlv(string $tag, string $value): string
{
    return $tag . str_pad((string)strlen($value), 2, '0', STR_PAD_LEFT) . $value;
}

/**
 * @return string|null ข้อความสำหรับสร้าง QR หรือ null ถ้ารูปแบบ PromptPay ID ไม่ถูกต้อง
 */
function promptPayPayload(string $id, ?float $amount = null): ?string
{
    $id = preg_replace('/\D/', '', $id);

    if (strlen($id) === 10 && $id[0] === '0') {
        $subTag = '01';
        $target = str_pad('66' . substr($id, 1), 13, '0', STR_PAD_LEFT); // 0066xxxxxxxxx
    } elseif (strlen($id) === 13) {
        $subTag = '02';
        $target = $id;
    } elseif (strlen($id) === 15) {
        $subTag = '03';
        $target = $id;
    } else {
        return null;
    }

    $hasAmount = $amount !== null && $amount > 0;

    $merchant = promptPayTlv('00', 'A000000677010111') . promptPayTlv($subTag, $target);

    $data  = promptPayTlv('00', '01');
    $data .= promptPayTlv('01', $hasAmount ? '12' : '11');
    $data .= promptPayTlv('29', $merchant);
    $data .= promptPayTlv('53', '764');
    if ($hasAmount) {
        $data .= promptPayTlv('54', number_format($amount, 2, '.', ''));
    }
    $data .= promptPayTlv('58', 'TH');
    $data .= '6304';

    return $data . promptPayCrc16($data);
}