// Date Range Picker functionality
function initDateRangePicker() {
    const tanggalAwal = document.getElementById('tanggal_awal');
    const tanggalAkhir = document.getElementById('tanggal_akhir');
    
    if (tanggalAwal && tanggalAkhir) {
        // Set min/max dates
        const today = new Date().toISOString().split('T')[0];
        tanggalAwal.max = today;
        tanggalAkhir.max = today;
        
        // When start date changes, update end date min
        tanggalAwal.addEventListener('change', function() {
            tanggalAkhir.min = this.value;
            if (tanggalAkhir.value && tanggalAkhir.value < this.value) {
                tanggalAkhir.value = this.value;
            }
        });
        
        // When end date changes, update start date max
        tanggalAkhir.addEventListener('change', function() {
            tanggalAwal.max = this.value;
        });
        
        // Quick select buttons
        const quickSelectContainer = document.createElement('div');
        quickSelectContainer.className = 'flex flex-wrap gap-2 mt-2';
        quickSelectContainer.innerHTML = `
            <button type="button" class="quick-date bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs px-2 py-1 rounded" data-days="7">7 Hari</button>
            <button type="button" class="quick-date bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs px-2 py-1 rounded" data-days="30">30 Hari</button>
            <button type="button" class="quick-date bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs px-2 py-1 rounded" data-days="90">3 Bulan</button>
            <button type="button" class="quick-date bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs px-2 py-1 rounded" data-month="1">Bulan Ini</button>
        `;
        
        tanggalAwal.parentNode.appendChild(quickSelectContainer);
        
        // Quick select functionality
        document.querySelectorAll('.quick-date').forEach(button => {
            button.addEventListener('click', function() {
                const days = this.getAttribute('data-days');
                const month = this.getAttribute('data-month');
                const today = new Date();
                const endDate = today.toISOString().split('T')[0];
                
                let startDate = new Date();
                
                if (days) {
                    startDate.setDate(today.getDate() - parseInt(days));
                } else if (month) {
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                }
                
                const startDateStr = startDate.toISOString().split('T')[0];
                
                tanggalAwal.value = startDateStr;
                tanggalAkhir.value = endDate;
                
                // Trigger change events
                tanggalAwal.dispatchEvent(new Event('change'));
                tanggalAkhir.dispatchEvent(new Event('change'));
            });
        });
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initDateRangePicker();
});