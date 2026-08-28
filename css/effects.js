document.addEventListener('DOMContentLoaded', function() {
    const leafContainer = document.querySelector('.leaf-container');
    if (!leafContainer) return;

    const numberOfLeaves = 25; // จำนวนใบไม้ที่ต้องการ
    const leafTypes = ['🌿', '🍃', '🍂', '🌱']; // รูปแบบใบไม้

    function createLeaf() {
        const leaf = document.createElement('div');
        leaf.classList.add('leaf');
        
        // สุ่มคุณสมบัติต่างๆ ของใบไม้
        leaf.textContent = leafTypes[Math.floor(Math.random() * leafTypes.length)];
        leaf.style.left = Math.random() * 100 + 'vw';
        leaf.style.animationDuration = (Math.random() * 8 + 7) + 's'; // ระยะเวลา 7-15 วินาที
        leaf.style.animationDelay = Math.random() * 5 + 's';
        leaf.style.fontSize = (Math.random() * 15 + 10) + 'px'; // ขนาด 10px - 25px

        leafContainer.appendChild(leaf);

        // เมื่อใบไม้ร่วงจนสุด ให้ลบออกเพื่อไม่ให้เปลืองทรัพยากร
        setTimeout(() => {
            leaf.remove();
        }, 15000); // ลบหลังจาก 15 วินาที
    }

    // สร้างใบไม้ตามจำนวนที่กำหนด ทุกๆ 500ms
    for (let i = 0; i < numberOfLeaves; i++) {
        setTimeout(createLeaf, i * 500);
    }
});