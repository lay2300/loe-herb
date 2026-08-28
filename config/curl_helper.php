<?php
/**
 * ฟังก์ชันสำหรับเรียก API ผ่าน cURL
 * @param string $url URL ที่ต้องการเรียก
 * @param array|null $postData ข้อมูลที่จะส่งแบบ POST (ถ้าเป็น null จะเป็น GET)
 * @return array|null ข้อมูลที่ได้จากการถอดรหัส JSON
 */
function call_curl_api($url, $postData = null) {
    $ch = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // สำหรับ localhost
    ];
    if ($postData !== null) {
        $options[CURLOPT_POST] = 1;
        $options[CURLOPT_POSTFIELDS] = http_build_query($postData);
    }
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

/**
 * ฟังก์ชันสำหรับดาวน์โหลดไฟล์ด้วย cURL
 * @param string $url URL ของไฟล์ที่ต้องการดาวน์โหลด
 * @return array ผลลัพธ์ประกอบด้วย 'data' (เนื้อหาไฟล์) และ 'http_code'
 */
function download_file_curl($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // สำหรับ localhost
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['data' => $data, 'http_code' => $http_code];
}
?>