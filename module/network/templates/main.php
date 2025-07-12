<div id="network" class="tab-content">
    <h2>🔧 VM Netzwerk Konfiguration</h2>
    <form onsubmit="updateVMNetwork(event)">
        <div class="form-row">
            <div class="form-group">
                <label for="net_vmid">VM ID</label>
                <input type="number" id="net_vmid" name="vmid" required placeholder="100" min="100">
            </div>
            <div class="form-group">
                <label for="net_mac">MAC Adresse</label>
                <input type="text" id="net_mac" name="mac" required placeholder="aa:bb:cc:dd:ee:ff" pattern="[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}">
            </div>
        </div>
        
        <div class="form-group">
            <label for="net_ip">IP Adresse</label>
            <input type="text" id="net_ip" name="ip" required placeholder="192.168.1.100">
        </div>
        
        <button type="submit" class="btn">
            <span class="loading hidden"></span>
            Netzwerk aktualisieren
        </button>
    </form>
    
    <hr>
    
    <div class="endpoint-section">
        <h3>📚 Hilfreiche Informationen</h3>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h4>MAC-Adressen Format</h4>
            <p>MAC-Adressen müssen im Format <code>aa:bb:cc:dd:ee:ff</code> angegeben werden.</p>
            <p>Beispiele:</p>
            <ul>
                <li>OVH Virtual MAC: <code>02:00:00:xx:xx:xx</code></li>
                <li>VMware: <code>00:50:56:xx:xx:xx</code></li>
                <li>KVM/QEMU: <code>52:54:00:xx:xx:xx</code></li>
            </ul>
            
            <h4>IP-Konfiguration</h4>
            <p>Die IP-Adresse wird in der VM-Konfiguration hinterlegt. Die tatsächliche Zuweisung erfolgt:</p>
            <ul>
                <li>Über DHCP mit MAC-IP Binding</li>
                <li>Über Cloud-Init bei unterstützten Images</li>
                <li>Manuell in der VM nach dem Start</li>
            </ul>
        </div>
    </div>
    
    <div class="endpoint-section">
        <h3>⚙️ Erweiterte Netzwerk-Optionen</h3>
        <p style="color: #666; font-style: italic;">
            Weitere Netzwerk-Konfigurationen wie VLANs, Bonding oder Bridge-Konfigurationen 
            können über das Admin-Dashboard oder direkt in Proxmox vorgenommen werden.
        </p>
    </div>
</div>

<script>
// Network Module JavaScript
window.networkModule = {
    init: function() {
        console.log('Network module initialized');
    }
};

// Form Handler
async function updateVMNetwork(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('network', 'update_vm_network', formData);
        
        if (result.success) {
            showNotification('VM Netzwerk wurde erfolgreich aktualisiert!', 'success');
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}
</script>