<!-- Widget pencarian yang bisa digunakan di berbagai halaman -->
<div class="search-widget">
    <form method="GET" action="search.php" class="quick-search-form">
        <div class="search-input-group">
            <input type="text" 
                   name="location" 
                   placeholder="Cari lokasi kos..." 
                   value="<?php echo isset($_GET['location']) ? htmlspecialchars($_GET['location']) : ''; ?>"
                   class="search-input">
            <button type="submit" class="search-btn">
                🔍
            </button>
        </div>
        
        <!-- Quick Filters -->
        <div class="quick-filters">
            <label class="filter-chip">
                <input type="checkbox" name="facilities[]" value="WiFi">
                <span>WiFi</span>
            </label>
            <label class="filter-chip">
                <input type="checkbox" name="facilities[]" value="AC">
                <span>AC</span>
            </label>
            <label class="filter-chip">
                <input type="checkbox" name="facilities[]" value="Parkir Motor">
                <span>Parkir</span>
            </label>
            <a href="search.php" class="advanced-search-link">Pencarian Lanjutan →</a>
        </div>
    </form>
</div>

<style>
.search-widget {
    background: white;
    padding: 1.5rem;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    margin: 2rem 0;
}

.search-input-group {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.search-input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #00c851;
}

.search-btn {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #00c851, #ff69b4);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 1.2rem;
    transition: transform 0.3s ease;
}

.search-btn:hover {
    transform: translateY(-2px);
}

.quick-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.filter-chip {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.4rem 0.8rem;
    background: #f8f9fa;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.filter-chip:hover {
    background: rgba(0, 200, 81, 0.1);
}

.filter-chip input[type="checkbox"] {
    margin: 0;
}

.advanced-search-link {
    color: #ff69b4;
    text-decoration: none;
    font-weight: 600;
    margin-left: auto;
}

.advanced-search-link:hover {
    text-decoration: underline;
}
</style>
