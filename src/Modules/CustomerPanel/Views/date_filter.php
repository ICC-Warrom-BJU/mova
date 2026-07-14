<form method="get" class="date-filter-form">
    <div class="date-filter-group">
        <label for="date_start">Dari</label>
        <input type="date" name="date_start" id="date_start" class="form-control form-control-sm" value="<?= e($_GET['date_start'] ?? date('Y-m-d')) ?>">
    </div>
    <div class="date-filter-group">
        <label for="date_end">Sampai</label>
        <input type="date" name="date_end" id="date_end" class="form-control form-control-sm" value="<?= e($_GET['date_end'] ?? date('Y-m-d')) ?>">
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= e(strtok($_SERVER['REQUEST_URI'], '?')) ?>" class="btn btn-outline btn-sm">Reset</a>
</form>