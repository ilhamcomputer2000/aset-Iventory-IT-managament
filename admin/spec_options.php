<?php
/**
 * spec_options.php - Shared specification field options for all device categories
 * Included by both create.php and update.php
 * Each category group is shown/hidden dynamically via JavaScript based on Nama_Barang selection
 */
?>

<!-- ========== GRUP: LAPTOP / PC / KOMPUTER ========== -->
<div class="spec-group" data-category="laptop_pc" style="display:none;">
    <!-- Processor -->
    <div class="form-group">
        <label for="spec_processor" class="block text-gray-700 mb-2">
            <i class="fas fa-microchip mr-2 text-blue-500"></i>Processor
        </label>
        <select id="spec_processor" class="spec-select2-field w-full" data-spec-label="Processor" data-placeholder="Pilih atau ketik Processor...">
            <option value=""></option>
            <optgroup label="Intel Core Ultra (2024+)">
                <option value="Intel Core Ultra 9 285K">Intel Core Ultra 9 285K</option>
                <option value="Intel Core Ultra 7 265K">Intel Core Ultra 7 265K</option>
                <option value="Intel Core Ultra 5 245K">Intel Core Ultra 5 245K</option>
                <option value="Intel Core Ultra 7 155H">Intel Core Ultra 7 155H</option>
                <option value="Intel Core Ultra 5 125H">Intel Core Ultra 5 125H</option>
            </optgroup>
            <optgroup label="Intel Core i9">
                <option value="Intel Core i9-14900K">Intel Core i9-14900K</option>
                <option value="Intel Core i9-14900KF">Intel Core i9-14900KF</option>
                <option value="Intel Core i9-13900K">Intel Core i9-13900K</option>
                <option value="Intel Core i9-13900H">Intel Core i9-13900H</option>
                <option value="Intel Core i9-12900K">Intel Core i9-12900K</option>
                <option value="Intel Core i9-11900K">Intel Core i9-11900K</option>
                <option value="Intel Core i9-10900K">Intel Core i9-10900K</option>
            </optgroup>
            <optgroup label="Intel Core i7">
                <option value="Intel Core i7-14700K">Intel Core i7-14700K</option>
                <option value="Intel Core i7-14700KF">Intel Core i7-14700KF</option>
                <option value="Intel Core i7-13700K">Intel Core i7-13700K</option>
                <option value="Intel Core i7-13700H">Intel Core i7-13700H</option>
                <option value="Intel Core i7-12700K">Intel Core i7-12700K</option>
                <option value="Intel Core i7-12700H">Intel Core i7-12700H</option>
                <option value="Intel Core i7-11700K">Intel Core i7-11700K</option>
                <option value="Intel Core i7-11800H">Intel Core i7-11800H</option>
                <option value="Intel Core i7-10700K">Intel Core i7-10700K</option>
                <option value="Intel Core i7-10750H">Intel Core i7-10750H</option>
                <option value="Intel Core i7-9700K">Intel Core i7-9700K</option>
                <option value="Intel Core i7-8700K">Intel Core i7-8700K</option>
                <option value="Intel Core i7-8550U">Intel Core i7-8550U</option>
                <option value="Intel Core i7-7700K">Intel Core i7-7700K</option>
                <option value="Intel Core i7-7500U">Intel Core i7-7500U</option>
                <option value="Intel Core i7-6700K">Intel Core i7-6700K</option>
                <option value="Intel Core i7-6500U">Intel Core i7-6500U</option>
                <option value="Intel Core i7-4790K">Intel Core i7-4790K</option>
            </optgroup>
            <optgroup label="Intel Core i5">
                <option value="Intel Core i5-14600K">Intel Core i5-14600K</option>
                <option value="Intel Core i5-14600KF">Intel Core i5-14600KF</option>
                <option value="Intel Core i5-13600K">Intel Core i5-13600K</option>
                <option value="Intel Core i5-13500H">Intel Core i5-13500H</option>
                <option value="Intel Core i5-12600K">Intel Core i5-12600K</option>
                <option value="Intel Core i5-12500H">Intel Core i5-12500H</option>
                <option value="Intel Core i5-12400">Intel Core i5-12400</option>
                <option value="Intel Core i5-11600K">Intel Core i5-11600K</option>
                <option value="Intel Core i5-11400">Intel Core i5-11400</option>
                <option value="Intel Core i5-10600K">Intel Core i5-10600K</option>
                <option value="Intel Core i5-10400">Intel Core i5-10400</option>
                <option value="Intel Core i5-10300H">Intel Core i5-10300H</option>
                <option value="Intel Core i5-9400F">Intel Core i5-9400F</option>
                <option value="Intel Core i5-8400">Intel Core i5-8400</option>
                <option value="Intel Core i5-8250U">Intel Core i5-8250U</option>
                <option value="Intel Core i5-7400">Intel Core i5-7400</option>
                <option value="Intel Core i5-7200U">Intel Core i5-7200U</option>
                <option value="Intel Core i5-6500">Intel Core i5-6500</option>
                <option value="Intel Core i5-6200U">Intel Core i5-6200U</option>
                <option value="Intel Core i5-4590">Intel Core i5-4590</option>
            </optgroup>
            <optgroup label="Intel Core i3">
                <option value="Intel Core i3-14100">Intel Core i3-14100</option>
                <option value="Intel Core i3-13100">Intel Core i3-13100</option>
                <option value="Intel Core i3-12100">Intel Core i3-12100</option>
                <option value="Intel Core i3-10100">Intel Core i3-10100</option>
                <option value="Intel Core i3-10110U">Intel Core i3-10110U</option>
                <option value="Intel Core i3-8100">Intel Core i3-8100</option>
                <option value="Intel Core i3-7100">Intel Core i3-7100</option>
                <option value="Intel Core i3-6100">Intel Core i3-6100</option>
            </optgroup>
            <optgroup label="Intel Celeron / Pentium">
                <option value="Intel Celeron N5105">Intel Celeron N5105</option>
                <option value="Intel Celeron N4500">Intel Celeron N4500</option>
                <option value="Intel Celeron N4120">Intel Celeron N4120</option>
                <option value="Intel Celeron N4020">Intel Celeron N4020</option>
                <option value="Intel Celeron N4000">Intel Celeron N4000</option>
                <option value="Intel Celeron N3350">Intel Celeron N3350</option>
                <option value="Intel Pentium Silver N6000">Intel Pentium Silver N6000</option>
                <option value="Intel Pentium Gold G7400">Intel Pentium Gold G7400</option>
                <option value="Intel Pentium Gold G6400">Intel Pentium Gold G6400</option>
            </optgroup>
            <optgroup label="Intel Xeon">
                <option value="Intel Xeon W-2295">Intel Xeon W-2295</option>
                <option value="Intel Xeon E-2388G">Intel Xeon E-2388G</option>
                <option value="Intel Xeon E-2278G">Intel Xeon E-2278G</option>
                <option value="Intel Xeon E-2176M">Intel Xeon E-2176M</option>
            </optgroup>
            <optgroup label="AMD Ryzen 9">
                <option value="AMD Ryzen 9 9950X">AMD Ryzen 9 9950X</option>
                <option value="AMD Ryzen 9 9900X">AMD Ryzen 9 9900X</option>
                <option value="AMD Ryzen 9 7950X">AMD Ryzen 9 7950X</option>
                <option value="AMD Ryzen 9 7945HX">AMD Ryzen 9 7945HX</option>
                <option value="AMD Ryzen 9 7900X">AMD Ryzen 9 7900X</option>
                <option value="AMD Ryzen 9 5950X">AMD Ryzen 9 5950X</option>
                <option value="AMD Ryzen 9 5900X">AMD Ryzen 9 5900X</option>
                <option value="AMD Ryzen 9 5900HX">AMD Ryzen 9 5900HX</option>
                <option value="AMD Ryzen 9 3950X">AMD Ryzen 9 3950X</option>
                <option value="AMD Ryzen 9 3900X">AMD Ryzen 9 3900X</option>
            </optgroup>
            <optgroup label="AMD Ryzen 7">
                <option value="AMD Ryzen 7 9700X">AMD Ryzen 7 9700X</option>
                <option value="AMD Ryzen 7 7800X3D">AMD Ryzen 7 7800X3D</option>
                <option value="AMD Ryzen 7 7700X">AMD Ryzen 7 7700X</option>
                <option value="AMD Ryzen 7 7735HS">AMD Ryzen 7 7735HS</option>
                <option value="AMD Ryzen 7 6800H">AMD Ryzen 7 6800H</option>
                <option value="AMD Ryzen 7 5800X">AMD Ryzen 7 5800X</option>
                <option value="AMD Ryzen 7 5800H">AMD Ryzen 7 5800H</option>
                <option value="AMD Ryzen 7 5700U">AMD Ryzen 7 5700U</option>
                <option value="AMD Ryzen 7 5700G">AMD Ryzen 7 5700G</option>
                <option value="AMD Ryzen 7 4800H">AMD Ryzen 7 4800H</option>
                <option value="AMD Ryzen 7 3700X">AMD Ryzen 7 3700X</option>
                <option value="AMD Ryzen 7 3700U">AMD Ryzen 7 3700U</option>
                <option value="AMD Ryzen 7 2700X">AMD Ryzen 7 2700X</option>
            </optgroup>
            <optgroup label="AMD Ryzen 5">
                <option value="AMD Ryzen 5 9600X">AMD Ryzen 5 9600X</option>
                <option value="AMD Ryzen 5 7600X">AMD Ryzen 5 7600X</option>
                <option value="AMD Ryzen 5 7535HS">AMD Ryzen 5 7535HS</option>
                <option value="AMD Ryzen 5 6600H">AMD Ryzen 5 6600H</option>
                <option value="AMD Ryzen 5 5600X">AMD Ryzen 5 5600X</option>
                <option value="AMD Ryzen 5 5600G">AMD Ryzen 5 5600G</option>
                <option value="AMD Ryzen 5 5600H">AMD Ryzen 5 5600H</option>
                <option value="AMD Ryzen 5 5500U">AMD Ryzen 5 5500U</option>
                <option value="AMD Ryzen 5 4600H">AMD Ryzen 5 4600H</option>
                <option value="AMD Ryzen 5 4500U">AMD Ryzen 5 4500U</option>
                <option value="AMD Ryzen 5 3600">AMD Ryzen 5 3600</option>
                <option value="AMD Ryzen 5 3500U">AMD Ryzen 5 3500U</option>
                <option value="AMD Ryzen 5 2600">AMD Ryzen 5 2600</option>
            </optgroup>
            <optgroup label="AMD Ryzen 3">
                <option value="AMD Ryzen 3 5300G">AMD Ryzen 3 5300G</option>
                <option value="AMD Ryzen 3 4300U">AMD Ryzen 3 4300U</option>
                <option value="AMD Ryzen 3 3300X">AMD Ryzen 3 3300X</option>
                <option value="AMD Ryzen 3 3250U">AMD Ryzen 3 3250U</option>
                <option value="AMD Ryzen 3 3200G">AMD Ryzen 3 3200G</option>
                <option value="AMD Ryzen 3 2200G">AMD Ryzen 3 2200G</option>
            </optgroup>
            <optgroup label="AMD Athlon / A-Series">
                <option value="AMD Athlon Silver 3050U">AMD Athlon Silver 3050U</option>
                <option value="AMD Athlon Gold 3150U">AMD Athlon Gold 3150U</option>
                <option value="AMD A10-9700">AMD A10-9700</option>
                <option value="AMD A8-9600">AMD A8-9600</option>
            </optgroup>
            <optgroup label="Apple Silicon">
                <option value="Apple M4 Pro">Apple M4 Pro</option>
                <option value="Apple M4">Apple M4</option>
                <option value="Apple M3 Pro">Apple M3 Pro</option>
                <option value="Apple M3">Apple M3</option>
                <option value="Apple M2 Pro">Apple M2 Pro</option>
                <option value="Apple M2">Apple M2</option>
                <option value="Apple M1 Pro">Apple M1 Pro</option>
                <option value="Apple M1">Apple M1</option>
            </optgroup>
            <optgroup label="Qualcomm Snapdragon">
                <option value="Qualcomm Snapdragon X Elite">Qualcomm Snapdragon X Elite</option>
                <option value="Qualcomm Snapdragon X Plus">Qualcomm Snapdragon X Plus</option>
                <option value="Qualcomm Snapdragon 8cx Gen 3">Qualcomm Snapdragon 8cx Gen 3</option>
            </optgroup>
        </select>
    </div>
    <!-- RAM -->
    <div class="form-group">
        <label for="spec_ram" class="block text-gray-700 mb-2">
            <i class="fas fa-memory mr-2 text-orange-500"></i>RAM
        </label>
        <select id="spec_ram" class="spec-select2-field w-full" data-spec-label="RAM" data-placeholder="Pilih atau ketik RAM...">
            <option value=""></option>
            <option value="2GB DDR3">2GB DDR3</option><option value="4GB DDR3">4GB DDR3</option>
            <option value="4GB DDR4">4GB DDR4</option><option value="4GB DDR5">4GB DDR5</option>
            <option value="8GB DDR3">8GB DDR3</option><option value="8GB DDR4">8GB DDR4</option>
            <option value="8GB DDR5">8GB DDR5</option><option value="12GB DDR4">12GB DDR4</option>
            <option value="12GB DDR5">12GB DDR5</option><option value="16GB DDR4">16GB DDR4</option>
            <option value="16GB DDR5">16GB DDR5</option><option value="24GB DDR5">24GB DDR5</option>
            <option value="32GB DDR4">32GB DDR4</option><option value="32GB DDR5">32GB DDR5</option>
            <option value="48GB DDR5">48GB DDR5</option><option value="64GB DDR4">64GB DDR4</option>
            <option value="64GB DDR5">64GB DDR5</option><option value="128GB DDR5">128GB DDR5</option>
        </select>
    </div>
    <!-- SSD/HDD -->
    <div class="form-group">
        <label for="spec_storage" class="block text-gray-700 mb-2">
            <i class="fas fa-hdd mr-2 text-blue-500"></i>SSD/HDD
        </label>
        <select id="spec_storage" class="spec-select2-field w-full" data-spec-label="SSD/HDD" data-placeholder="Pilih atau ketik SSD/HDD...">
            <option value=""></option>
            <optgroup label="SSD NVMe">
                <option value="128GB SSD NVMe">128GB SSD NVMe</option>
                <option value="256GB SSD NVMe">256GB SSD NVMe</option>
                <option value="512GB SSD NVMe">512GB SSD NVMe</option>
                <option value="1TB SSD NVMe">1TB SSD NVMe</option>
                <option value="2TB SSD NVMe">2TB SSD NVMe</option>
            </optgroup>
            <optgroup label="SSD SATA">
                <option value="120GB SSD SATA">120GB SSD SATA</option>
                <option value="128GB SSD SATA">128GB SSD SATA</option>
                <option value="240GB SSD SATA">240GB SSD SATA</option>
                <option value="256GB SSD SATA">256GB SSD SATA</option>
                <option value="480GB SSD SATA">480GB SSD SATA</option>
                <option value="500GB SSD SATA">500GB SSD SATA</option>
                <option value="512GB SSD SATA">512GB SSD SATA</option>
                <option value="1TB SSD SATA">1TB SSD SATA</option>
            </optgroup>
            <optgroup label="HDD">
                <option value="320GB HDD">320GB HDD</option>
                <option value="500GB HDD">500GB HDD</option>
                <option value="1TB HDD">1TB HDD</option>
                <option value="2TB HDD">2TB HDD</option>
                <option value="4TB HDD">4TB HDD</option>
            </optgroup>
            <optgroup label="Kombinasi">
                <option value="256GB SSD + 1TB HDD">256GB SSD + 1TB HDD</option>
                <option value="512GB SSD + 1TB HDD">512GB SSD + 1TB HDD</option>
                <option value="512GB SSD + 2TB HDD">512GB SSD + 2TB HDD</option>
                <option value="1TB SSD + 2TB HDD">1TB SSD + 2TB HDD</option>
            </optgroup>
        </select>
    </div>
    <!-- VGA -->
    <div class="form-group">
        <label for="spec_vga" class="block text-gray-700 mb-2">
            <i class="fas fa-tv mr-2 text-orange-500"></i>VGA
        </label>
        <select id="spec_vga" class="spec-select2-field w-full" data-spec-label="VGA" data-placeholder="Pilih atau ketik VGA...">
            <option value=""></option>
            <optgroup label="Intel Integrated">
                <option value="Intel UHD Graphics 770">Intel UHD Graphics 770</option>
                <option value="Intel UHD Graphics 730">Intel UHD Graphics 730</option>
                <option value="Intel UHD Graphics 630">Intel UHD Graphics 630</option>
                <option value="Intel UHD Graphics 620">Intel UHD Graphics 620</option>
                <option value="Intel Iris Xe Graphics">Intel Iris Xe Graphics</option>
                <option value="Intel Iris Plus Graphics">Intel Iris Plus Graphics</option>
                <option value="Intel HD Graphics 620">Intel HD Graphics 620</option>
                <option value="Intel Arc A770">Intel Arc A770</option>
                <option value="Intel Arc A750">Intel Arc A750</option>
            </optgroup>
            <optgroup label="NVIDIA GeForce RTX 40">
                <option value="NVIDIA GeForce RTX 4090">NVIDIA GeForce RTX 4090</option>
                <option value="NVIDIA GeForce RTX 4080">NVIDIA GeForce RTX 4080</option>
                <option value="NVIDIA GeForce RTX 4070 Ti">NVIDIA GeForce RTX 4070 Ti</option>
                <option value="NVIDIA GeForce RTX 4070">NVIDIA GeForce RTX 4070</option>
                <option value="NVIDIA GeForce RTX 4060 Ti">NVIDIA GeForce RTX 4060 Ti</option>
                <option value="NVIDIA GeForce RTX 4060">NVIDIA GeForce RTX 4060</option>
                <option value="NVIDIA GeForce RTX 4050 Laptop">NVIDIA GeForce RTX 4050 Laptop</option>
            </optgroup>
            <optgroup label="NVIDIA GeForce RTX 30">
                <option value="NVIDIA GeForce RTX 3090">NVIDIA GeForce RTX 3090</option>
                <option value="NVIDIA GeForce RTX 3080">NVIDIA GeForce RTX 3080</option>
                <option value="NVIDIA GeForce RTX 3070">NVIDIA GeForce RTX 3070</option>
                <option value="NVIDIA GeForce RTX 3060">NVIDIA GeForce RTX 3060</option>
                <option value="NVIDIA GeForce RTX 3050">NVIDIA GeForce RTX 3050</option>
            </optgroup>
            <optgroup label="NVIDIA GeForce GTX">
                <option value="NVIDIA GeForce GTX 1660 Ti">NVIDIA GeForce GTX 1660 Ti</option>
                <option value="NVIDIA GeForce GTX 1650">NVIDIA GeForce GTX 1650</option>
                <option value="NVIDIA GeForce GTX 1050 Ti">NVIDIA GeForce GTX 1050 Ti</option>
                <option value="NVIDIA GeForce MX550">NVIDIA GeForce MX550</option>
                <option value="NVIDIA GeForce MX450">NVIDIA GeForce MX450</option>
                <option value="NVIDIA GeForce MX350">NVIDIA GeForce MX350</option>
            </optgroup>
            <optgroup label="AMD Radeon">
                <option value="AMD Radeon RX 7900 XTX">AMD Radeon RX 7900 XTX</option>
                <option value="AMD Radeon RX 7800 XT">AMD Radeon RX 7800 XT</option>
                <option value="AMD Radeon RX 7600">AMD Radeon RX 7600</option>
                <option value="AMD Radeon RX 6700 XT">AMD Radeon RX 6700 XT</option>
                <option value="AMD Radeon RX 580">AMD Radeon RX 580</option>
                <option value="AMD Radeon Vega 8">AMD Radeon Vega 8</option>
                <option value="AMD Radeon Graphics (Integrated)">AMD Radeon Graphics (Integrated)</option>
            </optgroup>
            <optgroup label="Tidak Ada">
                <option value="Integrated Graphics">Integrated Graphics</option>
                <option value="Tidak Ada / Onboard">Tidak Ada / Onboard</option>
            </optgroup>
        </select>
    </div>
    <!-- Ukuran Layar -->
    <div class="form-group">
        <label for="spec_ukuran" class="block text-gray-700 mb-2">
            <i class="fas fa-ruler-combined mr-2 text-blue-500"></i>Ukuran Layar
        </label>
        <select id="spec_ukuran" class="spec-select2-field w-full" data-spec-label="Ukuran" data-placeholder="Pilih atau ketik Ukuran...">
            <option value=""></option>
            <option value="11 inch">11 inch</option><option value="12 inch">12 inch</option>
            <option value="13 inch">13 inch</option><option value="13.3 inch">13.3 inch</option>
            <option value="14 inch">14 inch</option><option value="15 inch">15 inch</option>
            <option value="15.6 inch">15.6 inch</option><option value="16 inch">16 inch</option>
            <option value="17 inch">17 inch</option><option value="17.3 inch">17.3 inch</option>
            <option value="Mini PC">Mini PC</option><option value="Mini Tower">Mini Tower</option>
            <option value="Mid Tower">Mid Tower</option><option value="Full Tower">Full Tower</option>
        </select>
    </div>
    <!-- Warna -->
    <div class="form-group">
        <label for="spec_warna" class="block text-gray-700 mb-2">
            <i class="fas fa-palette mr-2 text-orange-500"></i>Warna
        </label>
        <select id="spec_warna" class="spec-select2-field w-full" data-spec-label="Warna" data-placeholder="Pilih atau ketik Warna...">
            <option value=""></option>
            <option value="Hitam">Hitam</option><option value="Putih">Putih</option>
            <option value="Silver">Silver</option><option value="Abu-abu">Abu-abu</option>
            <option value="Space Gray">Space Gray</option><option value="Dark Gray">Dark Gray</option>
            <option value="Biru">Biru</option><option value="Biru Muda">Biru Muda</option>
            <option value="Navy Blue">Navy Blue</option><option value="Merah">Merah</option>
            <option value="Rose Gold">Rose Gold</option><option value="Gold">Gold</option>
            <option value="Champagne Gold">Champagne Gold</option><option value="Pink">Pink</option>
            <option value="Hijau">Hijau</option><option value="Ungu">Ungu</option>
        </select>
    </div>
</div>

<!-- ========== GRUP: MONITOR ========== -->
<div class="spec-group" data-category="monitor" style="display:none;">
    <div class="form-group">
        <label for="spec_panel" class="block text-gray-700 mb-2">
            <i class="fas fa-desktop mr-2 text-blue-500"></i>Tipe Panel
        </label>
        <select id="spec_panel" class="spec-select2-field w-full" data-spec-label="Panel" data-placeholder="Pilih atau ketik Tipe Panel...">
            <option value=""></option>
            <option value="IPS">IPS</option><option value="VA">VA</option>
            <option value="TN">TN</option><option value="OLED">OLED</option>
            <option value="Mini LED">Mini LED</option><option value="Nano IPS">Nano IPS</option>
            <option value="QLED">QLED</option><option value="PLS">PLS</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_resolusi" class="block text-gray-700 mb-2">
            <i class="fas fa-expand mr-2 text-orange-500"></i>Resolusi
        </label>
        <select id="spec_resolusi" class="spec-select2-field w-full" data-spec-label="Resolusi" data-placeholder="Pilih atau ketik Resolusi...">
            <option value=""></option>
            <option value="1366x768 (HD)">1366x768 (HD)</option>
            <option value="1600x900 (HD+)">1600x900 (HD+)</option>
            <option value="1920x1080 (Full HD)">1920x1080 (Full HD)</option>
            <option value="2560x1080 (UW-FHD)">2560x1080 (UW-FHD)</option>
            <option value="2560x1440 (QHD)">2560x1440 (QHD)</option>
            <option value="3440x1440 (UW-QHD)">3440x1440 (UW-QHD)</option>
            <option value="3840x2160 (4K UHD)">3840x2160 (4K UHD)</option>
            <option value="5120x2880 (5K)">5120x2880 (5K)</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_refresh" class="block text-gray-700 mb-2">
            <i class="fas fa-tachometer-alt mr-2 text-blue-500"></i>Refresh Rate
        </label>
        <select id="spec_refresh" class="spec-select2-field w-full" data-spec-label="Refresh Rate" data-placeholder="Pilih atau ketik Refresh Rate...">
            <option value=""></option>
            <option value="60Hz">60Hz</option><option value="75Hz">75Hz</option>
            <option value="100Hz">100Hz</option><option value="120Hz">120Hz</option>
            <option value="144Hz">144Hz</option><option value="165Hz">165Hz</option>
            <option value="240Hz">240Hz</option><option value="360Hz">360Hz</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_ukuran_monitor" class="block text-gray-700 mb-2">
            <i class="fas fa-ruler-combined mr-2 text-orange-500"></i>Ukuran
        </label>
        <select id="spec_ukuran_monitor" class="spec-select2-field w-full" data-spec-label="Ukuran" data-placeholder="Pilih atau ketik Ukuran...">
            <option value=""></option>
            <option value="19 inch">19 inch</option><option value="21.5 inch">21.5 inch</option>
            <option value="23.8 inch">23.8 inch</option><option value="24 inch">24 inch</option>
            <option value="27 inch">27 inch</option><option value="32 inch">32 inch</option>
            <option value="34 inch (Ultrawide)">34 inch (Ultrawide)</option>
            <option value="49 inch (Super Ultrawide)">49 inch (Super Ultrawide)</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_port_monitor" class="block text-gray-700 mb-2">
            <i class="fas fa-plug mr-2 text-blue-500"></i>Port
        </label>
        <select id="spec_port_monitor" class="spec-select2-field w-full" data-spec-label="Port" data-placeholder="Pilih atau ketik Port...">
            <option value=""></option>
            <option value="HDMI">HDMI</option><option value="DisplayPort">DisplayPort</option>
            <option value="VGA">VGA</option><option value="DVI">DVI</option>
            <option value="USB-C">USB-C</option><option value="Thunderbolt">Thunderbolt</option>
            <option value="HDMI + DisplayPort">HDMI + DisplayPort</option>
            <option value="HDMI + VGA">HDMI + VGA</option>
            <option value="HDMI + DisplayPort + USB-C">HDMI + DisplayPort + USB-C</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_warna_monitor" class="block text-gray-700 mb-2">
            <i class="fas fa-palette mr-2 text-orange-500"></i>Warna
        </label>
        <select id="spec_warna_monitor" class="spec-select2-field w-full" data-spec-label="Warna" data-placeholder="Pilih atau ketik Warna...">
            <option value=""></option>
            <option value="Hitam">Hitam</option><option value="Putih">Putih</option>
            <option value="Silver">Silver</option><option value="Abu-abu">Abu-abu</option>
            <option value="Space Gray">Space Gray</option>
        </select>
    </div>
</div>

<!-- ========== GRUP: PRINTER ========== -->
<div class="spec-group" data-category="printer" style="display:none;">
    <div class="form-group">
        <label for="spec_jenis_print" class="block text-gray-700 mb-2">
            <i class="fas fa-print mr-2 text-blue-500"></i>Jenis Printer
        </label>
        <select id="spec_jenis_print" class="spec-select2-field w-full" data-spec-label="Jenis" data-placeholder="Pilih atau ketik Jenis Printer...">
            <option value=""></option>
            <option value="Inkjet">Inkjet</option><option value="Laser">Laser</option>
            <option value="Thermal">Thermal</option><option value="Dot Matrix">Dot Matrix</option>
            <option value="Label Printer">Label Printer</option>
            <option value="Inkjet Multifungsi">Inkjet Multifungsi</option>
            <option value="Laser Multifungsi">Laser Multifungsi</option>
            <option value="Plotter">Plotter</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_fungsi_print" class="block text-gray-700 mb-2">
            <i class="fas fa-cogs mr-2 text-orange-500"></i>Fungsi
        </label>
        <select id="spec_fungsi_print" class="spec-select2-field w-full" data-spec-label="Fungsi" data-placeholder="Pilih atau ketik Fungsi...">
            <option value=""></option>
            <option value="Print Only">Print Only</option>
            <option value="Print + Scan">Print + Scan</option>
            <option value="Print + Scan + Copy">Print + Scan + Copy</option>
            <option value="Print + Scan + Copy + Fax">Print + Scan + Copy + Fax</option>
            <option value="Print + Scan + Copy + ADF">Print + Scan + Copy + ADF</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_koneksi_print" class="block text-gray-700 mb-2">
            <i class="fas fa-plug mr-2 text-blue-500"></i>Koneksi
        </label>
        <select id="spec_koneksi_print" class="spec-select2-field w-full" data-spec-label="Koneksi" data-placeholder="Pilih atau ketik Koneksi...">
            <option value=""></option>
            <option value="USB">USB</option><option value="WiFi">WiFi</option>
            <option value="LAN (Ethernet)">LAN (Ethernet)</option>
            <option value="USB + WiFi">USB + WiFi</option>
            <option value="USB + WiFi + LAN">USB + WiFi + LAN</option>
            <option value="Bluetooth">Bluetooth</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_warna_print" class="block text-gray-700 mb-2">
            <i class="fas fa-palette mr-2 text-orange-500"></i>Warna
        </label>
        <select id="spec_warna_print" class="spec-select2-field w-full" data-spec-label="Warna" data-placeholder="Pilih atau ketik Warna...">
            <option value=""></option>
            <option value="Hitam">Hitam</option><option value="Putih">Putih</option>
            <option value="Abu-abu">Abu-abu</option><option value="Hitam Putih">Hitam Putih</option>
        </select>
    </div>
</div>

<!-- ========== GRUP: HP / SMARTPHONE ========== -->
<div class="spec-group" data-category="smartphone" style="display:none;">
    <div class="form-group">
        <label for="spec_proc_hp" class="block text-gray-700 mb-2">
            <i class="fas fa-microchip mr-2 text-blue-500"></i>Processor
        </label>
        <select id="spec_proc_hp" class="spec-select2-field w-full" data-spec-label="Processor" data-placeholder="Pilih atau ketik Processor...">
            <option value=""></option>
            <optgroup label="Qualcomm Snapdragon">
                <option value="Snapdragon 8 Gen 3">Snapdragon 8 Gen 3</option>
                <option value="Snapdragon 8 Gen 2">Snapdragon 8 Gen 2</option>
                <option value="Snapdragon 8 Gen 1">Snapdragon 8 Gen 1</option>
                <option value="Snapdragon 7+ Gen 2">Snapdragon 7+ Gen 2</option>
                <option value="Snapdragon 7 Gen 1">Snapdragon 7 Gen 1</option>
                <option value="Snapdragon 6 Gen 1">Snapdragon 6 Gen 1</option>
                <option value="Snapdragon 695">Snapdragon 695</option>
                <option value="Snapdragon 680">Snapdragon 680</option>
                <option value="Snapdragon 480+">Snapdragon 480+</option>
            </optgroup>
            <optgroup label="MediaTek">
                <option value="MediaTek Dimensity 9300">MediaTek Dimensity 9300</option>
                <option value="MediaTek Dimensity 8300">MediaTek Dimensity 8300</option>
                <option value="MediaTek Dimensity 7200">MediaTek Dimensity 7200</option>
                <option value="MediaTek Helio G99">MediaTek Helio G99</option>
                <option value="MediaTek Helio G85">MediaTek Helio G85</option>
                <option value="MediaTek Helio P35">MediaTek Helio P35</option>
            </optgroup>
            <optgroup label="Samsung Exynos">
                <option value="Samsung Exynos 2400">Samsung Exynos 2400</option>
                <option value="Samsung Exynos 1380">Samsung Exynos 1380</option>
                <option value="Samsung Exynos 1280">Samsung Exynos 1280</option>
            </optgroup>
            <optgroup label="Apple">
                <option value="Apple A17 Pro">Apple A17 Pro</option>
                <option value="Apple A16 Bionic">Apple A16 Bionic</option>
                <option value="Apple A15 Bionic">Apple A15 Bionic</option>
            </optgroup>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_ram_hp" class="block text-gray-700 mb-2">
            <i class="fas fa-memory mr-2 text-orange-500"></i>RAM
        </label>
        <select id="spec_ram_hp" class="spec-select2-field w-full" data-spec-label="RAM" data-placeholder="Pilih atau ketik RAM...">
            <option value=""></option>
            <option value="2GB">2GB</option><option value="3GB">3GB</option>
            <option value="4GB">4GB</option><option value="6GB">6GB</option>
            <option value="8GB">8GB</option><option value="12GB">12GB</option>
            <option value="16GB">16GB</option><option value="24GB">24GB</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_storage_hp" class="block text-gray-700 mb-2">
            <i class="fas fa-hdd mr-2 text-blue-500"></i>Penyimpanan
        </label>
        <select id="spec_storage_hp" class="spec-select2-field w-full" data-spec-label="Storage" data-placeholder="Pilih atau ketik Storage...">
            <option value=""></option>
            <option value="32GB">32GB</option><option value="64GB">64GB</option>
            <option value="128GB">128GB</option><option value="256GB">256GB</option>
            <option value="512GB">512GB</option><option value="1TB">1TB</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_kamera_hp" class="block text-gray-700 mb-2">
            <i class="fas fa-camera mr-2 text-orange-500"></i>Kamera
        </label>
        <select id="spec_kamera_hp" class="spec-select2-field w-full" data-spec-label="Kamera" data-placeholder="Pilih atau ketik Kamera...">
            <option value=""></option>
            <option value="8MP">8MP</option><option value="12MP">12MP</option>
            <option value="13MP">13MP</option><option value="48MP">48MP</option>
            <option value="50MP">50MP</option><option value="64MP">64MP</option>
            <option value="108MP">108MP</option><option value="200MP">200MP</option>
            <option value="48MP + 12MP + 12MP">48MP + 12MP + 12MP</option>
            <option value="50MP + 12MP + 10MP">50MP + 12MP + 10MP</option>
            <option value="200MP + 12MP + 10MP">200MP + 12MP + 10MP</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_layar_hp" class="block text-gray-700 mb-2">
            <i class="fas fa-mobile-alt mr-2 text-blue-500"></i>Ukuran Layar
        </label>
        <select id="spec_layar_hp" class="spec-select2-field w-full" data-spec-label="Ukuran" data-placeholder="Pilih atau ketik Ukuran...">
            <option value=""></option>
            <option value="5.0 inch">5.0 inch</option><option value="5.5 inch">5.5 inch</option>
            <option value="6.1 inch">6.1 inch</option><option value="6.4 inch">6.4 inch</option>
            <option value="6.5 inch">6.5 inch</option><option value="6.7 inch">6.7 inch</option>
            <option value="6.8 inch">6.8 inch</option><option value="6.9 inch">6.9 inch</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_warna_hp" class="block text-gray-700 mb-2">
            <i class="fas fa-palette mr-2 text-orange-500"></i>Warna
        </label>
        <select id="spec_warna_hp" class="spec-select2-field w-full" data-spec-label="Warna" data-placeholder="Pilih atau ketik Warna...">
            <option value=""></option>
            <option value="Hitam">Hitam</option><option value="Putih">Putih</option>
            <option value="Silver">Silver</option><option value="Biru">Biru</option>
            <option value="Hijau">Hijau</option><option value="Ungu">Ungu</option>
            <option value="Gold">Gold</option><option value="Rose Gold">Rose Gold</option>
            <option value="Merah">Merah</option><option value="Cream">Cream</option>
        </select>
    </div>
</div>

<!-- ========== GRUP: NETWORKING (Router, Switch, DVR) ========== -->
<div class="spec-group" data-category="networking" style="display:none;">
    <div class="form-group">
        <label for="spec_tipe_net" class="block text-gray-700 mb-2">
            <i class="fas fa-network-wired mr-2 text-blue-500"></i>Tipe
        </label>
        <select id="spec_tipe_net" class="spec-select2-field w-full" data-spec-label="Tipe" data-placeholder="Pilih atau ketik Tipe...">
            <option value=""></option>
            <option value="Managed Switch">Managed Switch</option>
            <option value="Unmanaged Switch">Unmanaged Switch</option>
            <option value="PoE Switch">PoE Switch</option>
            <option value="Wireless Router">Wireless Router</option>
            <option value="Access Point">Access Point</option>
            <option value="Modem Router">Modem Router</option>
            <option value="Mesh Router">Mesh Router</option>
            <option value="DVR 4CH">DVR 4CH</option><option value="DVR 8CH">DVR 8CH</option>
            <option value="DVR 16CH">DVR 16CH</option><option value="NVR 4CH">NVR 4CH</option>
            <option value="NVR 8CH">NVR 8CH</option><option value="NVR 16CH">NVR 16CH</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_port_net" class="block text-gray-700 mb-2">
            <i class="fas fa-ethernet mr-2 text-orange-500"></i>Jumlah Port
        </label>
        <select id="spec_port_net" class="spec-select2-field w-full" data-spec-label="Jumlah Port" data-placeholder="Pilih atau ketik Jumlah Port...">
            <option value=""></option>
            <option value="4 Port">4 Port</option><option value="5 Port">5 Port</option>
            <option value="8 Port">8 Port</option><option value="16 Port">16 Port</option>
            <option value="24 Port">24 Port</option><option value="48 Port">48 Port</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_speed_net" class="block text-gray-700 mb-2">
            <i class="fas fa-tachometer-alt mr-2 text-blue-500"></i>Kecepatan
        </label>
        <select id="spec_speed_net" class="spec-select2-field w-full" data-spec-label="Kecepatan" data-placeholder="Pilih atau ketik Kecepatan...">
            <option value=""></option>
            <option value="10/100 Mbps">10/100 Mbps</option>
            <option value="10/100/1000 Mbps (Gigabit)">10/100/1000 Mbps (Gigabit)</option>
            <option value="2.5 Gbps">2.5 Gbps</option>
            <option value="10 Gbps">10 Gbps</option>
            <option value="WiFi 5 (AC)">WiFi 5 (AC)</option>
            <option value="WiFi 6 (AX)">WiFi 6 (AX)</option>
            <option value="WiFi 6E">WiFi 6E</option>
            <option value="WiFi 7 (BE)">WiFi 7 (BE)</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_warna_net" class="block text-gray-700 mb-2">
            <i class="fas fa-palette mr-2 text-orange-500"></i>Warna
        </label>
        <select id="spec_warna_net" class="spec-select2-field w-full" data-spec-label="Warna" data-placeholder="Pilih atau ketik Warna...">
            <option value=""></option>
            <option value="Hitam">Hitam</option><option value="Putih">Putih</option>
            <option value="Abu-abu">Abu-abu</option><option value="Biru">Biru</option>
        </select>
    </div>
</div>

<!-- ========== GRUP: CCTV ========== -->
<div class="spec-group" data-category="cctv" style="display:none;">
    <div class="form-group">
        <label for="spec_resolusi_cctv" class="block text-gray-700 mb-2">
            <i class="fas fa-video mr-2 text-blue-500"></i>Resolusi
        </label>
        <select id="spec_resolusi_cctv" class="spec-select2-field w-full" data-spec-label="Resolusi" data-placeholder="Pilih atau ketik Resolusi...">
            <option value=""></option>
            <option value="720p (1MP)">720p (1MP)</option>
            <option value="1080p (2MP)">1080p (2MP)</option>
            <option value="3MP">3MP</option><option value="4MP">4MP</option>
            <option value="5MP">5MP</option><option value="4K (8MP)">4K (8MP)</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_tipe_cctv" class="block text-gray-700 mb-2">
            <i class="fas fa-map-marker-alt mr-2 text-orange-500"></i>Tipe
        </label>
        <select id="spec_tipe_cctv" class="spec-select2-field w-full" data-spec-label="Tipe" data-placeholder="Pilih atau ketik Tipe...">
            <option value=""></option>
            <option value="Indoor Dome">Indoor Dome</option>
            <option value="Indoor Turret">Indoor Turret</option>
            <option value="Outdoor Bullet">Outdoor Bullet</option>
            <option value="Outdoor Dome">Outdoor Dome</option>
            <option value="PTZ (Pan-Tilt-Zoom)">PTZ (Pan-Tilt-Zoom)</option>
            <option value="Fisheye / 360">Fisheye / 360</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_night_cctv" class="block text-gray-700 mb-2">
            <i class="fas fa-moon mr-2 text-blue-500"></i>Night Vision
        </label>
        <select id="spec_night_cctv" class="spec-select2-field w-full" data-spec-label="Night Vision" data-placeholder="Pilih atau ketik Night Vision...">
            <option value=""></option>
            <option value="IR 20m">IR 20m</option><option value="IR 30m">IR 30m</option>
            <option value="IR 50m">IR 50m</option><option value="IR 80m">IR 80m</option>
            <option value="ColorVu (Full Color)">ColorVu (Full Color)</option>
            <option value="Tidak Ada">Tidak Ada</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_koneksi_cctv" class="block text-gray-700 mb-2">
            <i class="fas fa-plug mr-2 text-orange-500"></i>Koneksi
        </label>
        <select id="spec_koneksi_cctv" class="spec-select2-field w-full" data-spec-label="Koneksi" data-placeholder="Pilih atau ketik Koneksi...">
            <option value=""></option>
            <option value="Analog (BNC/Coaxial)">Analog (BNC/Coaxial)</option>
            <option value="IP (Ethernet/PoE)">IP (Ethernet/PoE)</option>
            <option value="WiFi">WiFi</option>
            <option value="WiFi + Ethernet">WiFi + Ethernet</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_warna_cctv" class="block text-gray-700 mb-2">
            <i class="fas fa-palette mr-2 text-blue-500"></i>Warna
        </label>
        <select id="spec_warna_cctv" class="spec-select2-field w-full" data-spec-label="Warna" data-placeholder="Pilih atau ketik Warna...">
            <option value=""></option>
            <option value="Putih">Putih</option><option value="Hitam">Hitam</option>
            <option value="Abu-abu">Abu-abu</option>
        </select>
    </div>
</div>

<!-- ========== GRUP: KOMPONEN (Harddisk, SSD, RAM, Mainboard, Baterai, Casing, Keyboard) ========== -->
<div class="spec-group" data-category="komponen" style="display:none;">
    <div class="form-group">
        <label for="spec_kapasitas" class="block text-gray-700 mb-2">
            <i class="fas fa-database mr-2 text-blue-500"></i>Kapasitas / Spesifikasi
        </label>
        <select id="spec_kapasitas" class="spec-select2-field w-full" data-spec-label="Kapasitas" data-placeholder="Pilih atau ketik Kapasitas...">
            <option value=""></option>
            <optgroup label="SSD / HDD">
                <option value="120GB">120GB</option><option value="128GB">128GB</option>
                <option value="240GB">240GB</option><option value="256GB">256GB</option>
                <option value="480GB">480GB</option><option value="500GB">500GB</option>
                <option value="512GB">512GB</option><option value="1TB">1TB</option>
                <option value="2TB">2TB</option><option value="4TB">4TB</option>
            </optgroup>
            <optgroup label="RAM">
                <option value="2GB DDR3">2GB DDR3</option><option value="4GB DDR3">4GB DDR3</option>
                <option value="4GB DDR4">4GB DDR4</option><option value="8GB DDR4">8GB DDR4</option>
                <option value="8GB DDR5">8GB DDR5</option><option value="16GB DDR4">16GB DDR4</option>
                <option value="16GB DDR5">16GB DDR5</option><option value="32GB DDR4">32GB DDR4</option>
                <option value="32GB DDR5">32GB DDR5</option>
            </optgroup>
            <optgroup label="Baterai">
                <option value="3 Cell">3 Cell</option><option value="4 Cell">4 Cell</option>
                <option value="6 Cell">6 Cell</option><option value="9 Cell">9 Cell</option>
            </optgroup>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_interface" class="block text-gray-700 mb-2">
            <i class="fas fa-plug mr-2 text-orange-500"></i>Interface
        </label>
        <select id="spec_interface" class="spec-select2-field w-full" data-spec-label="Interface" data-placeholder="Pilih atau ketik Interface...">
            <option value=""></option>
            <option value="NVMe M.2 Gen 3">NVMe M.2 Gen 3</option>
            <option value="NVMe M.2 Gen 4">NVMe M.2 Gen 4</option>
            <option value="NVMe M.2 Gen 5">NVMe M.2 Gen 5</option>
            <option value="SATA III 2.5 inch">SATA III 2.5 inch</option>
            <option value="SATA III 3.5 inch">SATA III 3.5 inch</option>
            <option value="DDR4 SODIMM">DDR4 SODIMM</option>
            <option value="DDR4 DIMM">DDR4 DIMM</option>
            <option value="DDR5 SODIMM">DDR5 SODIMM</option>
            <option value="DDR5 DIMM">DDR5 DIMM</option>
            <option value="USB 2.0">USB 2.0</option>
            <option value="USB 3.0">USB 3.0</option>
            <option value="USB-C">USB-C</option>
            <option value="Wireless 2.4GHz">Wireless 2.4GHz</option>
            <option value="Bluetooth">Bluetooth</option>
            <option value="LGA 1700">LGA 1700</option>
            <option value="LGA 1200">LGA 1200</option>
            <option value="AM5">AM5</option>
            <option value="AM4">AM4</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_compat" class="block text-gray-700 mb-2">
            <i class="fas fa-check-circle mr-2 text-blue-500"></i>Kompatibilitas
        </label>
        <select id="spec_compat" class="spec-select2-field w-full" data-spec-label="Kompatibilitas" data-placeholder="Pilih atau ketik Kompatibilitas...">
            <option value=""></option>
            <option value="Universal">Universal</option>
            <option value="Laptop Only">Laptop Only</option>
            <option value="Desktop Only">Desktop Only</option>
        </select>
    </div>
    <div class="form-group">
        <label for="spec_warna_komp" class="block text-gray-700 mb-2">
            <i class="fas fa-palette mr-2 text-orange-500"></i>Warna
        </label>
        <select id="spec_warna_komp" class="spec-select2-field w-full" data-spec-label="Warna" data-placeholder="Pilih atau ketik Warna...">
            <option value=""></option>
            <option value="Hitam">Hitam</option><option value="Putih">Putih</option>
            <option value="Silver">Silver</option><option value="Abu-abu">Abu-abu</option>
        </select>
    </div>
</div>

<!-- ========== GRUP: LAINNYA (Textarea bebas) ========== -->
<div class="spec-group" data-category="lainnya" style="display:none;">
    <div class="form-group">
        <label for="spec_freetext" class="block text-gray-700 mb-2">
            <i class="fas fa-clipboard-list mr-2 text-blue-500"></i>Spesifikasi
        </label>
        <textarea id="spec_freetext" class="textarea-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200" rows="4" placeholder="Masukkan spesifikasi detail..."></textarea>
    </div>
</div>
