<!-- VM-Erstellung Formular -->
<style>
.collapsible-header {
    transition: all 0.3s ease;
    border-radius: 8px;
    padding: 10px 15px;
    margin: -10px -15px 10px -15px;
}

.collapsible-header:hover {
    background-color: rgba(0, 123, 255, 0.1);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.collapsible-header:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.collapsible-header .badge {
    font-size: 0.7em;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.collapsible-header i.fa-chevron-down,
.collapsible-header i.fa-chevron-up {
    transition: transform 0.3s ease;
}

.collapsible-header:hover i.fa-chevron-down,
.collapsible-header:hover i.fa-chevron-up {
    transform: scale(1.2);
}
</style>
<div class="card" style="display: none;" id="vm-creation-form">
    <div class="card-header">
        <h3 class="mb-0"><?= t('create_vm_proxmox') ?></h3>
    </div>
    <div class="card-body">
        <form id="create-vm-form" onsubmit="proxmoxModule.createVM(event)">
            <!-- Grundlegende VM-Konfiguration -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary collapsible-header" data-bs-toggle="collapse" data-bs-target="#basic-config-section" aria-expanded="true" style="cursor: pointer; user-select: none;" title="Zuklappen">
                        <i class="fas fa-server"></i> Grundlegende Konfiguration
                        <span class="badge bg-secondary ms-2">Zuklappen 🔼</span>
                        <i class="fas fa-chevron-down float-end"></i>
                    </h5>
                    <hr>
                </div>
            </div>
            <div class="collapse show" id="basic-config-section">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_name">VM Name *</label>
                        <input type="text" class="form-control" id="vm_name" name="name" required placeholder="web-server-01">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_id">VM ID *</label>
                        <input type="number" class="form-control" id="vm_id" name="vmid" required placeholder="100" min="100" max="999999999">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_node">Proxmox Node *</label>
                        <select class="form-control" id="vm_node" name="node" required>
                            <option value="">Node auswählen...</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_pool">Resource Pool (optional)</label>
                        <input type="text" class="form-control" id="vm_pool" name="pool" placeholder="production">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <label for="vm_description">Beschreibung</label>
                        <textarea class="form-control" id="vm_description" name="description" rows="2" placeholder="VM Beschreibung..."></textarea>
                    </div>
                </div>
            </div>
            </div>

            <!-- CPU und RAM Konfiguration -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary collapsible-header" data-bs-toggle="collapse" data-bs-target="#cpu-ram-section" aria-expanded="false" style="cursor: pointer; user-select: none;" title="Aufklappen">
                        <i class="fas fa-microchip"></i> CPU und RAM
                        <span class="badge bg-info ms-2">Aufklappen 🔽</span>
                        <i class="fas fa-chevron-down float-end"></i>
                    </h5>
                    <hr>
                </div>
            </div>
            <div class="collapse" id="cpu-ram-section">

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label for="vm_cores">CPU Kerne *</label>
                        <input type="number" class="form-control" id="vm_cores" name="cores" value="1" required min="1" max="32">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label for="vm_sockets">CPU Sockets *</label>
                        <input type="number" class="form-control" id="vm_sockets" name="sockets" value="1" required min="1" max="8">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label for="vm_cpu">CPU Typ</label>
                        <select class="form-control" id="vm_cpu" name="cpu">
                            <option value="host">host (Empfohlen)</option>
                            <optgroup label="Intel Prozessoren">
                                <option value="Nehalem">Nehalem (1. Gen Intel Core)</option>
                                <option value="Nehalem-IBRS">Nehalem-IBRS (Spectre v1 Schutz)</option>
                                <option value="Westmere">Westmere (1. Gen Intel Core Xeon E7)</option>
                                <option value="Westmere-IBRS">Westmere-IBRS (Spectre v1 Schutz)</option>
                                <option value="SandyBridge">SandyBridge (2. Gen Intel Core)</option>
                                <option value="SandyBridge-IBRS">SandyBridge-IBRS (Spectre v1 Schutz)</option>
                                <option value="IvyBridge">IvyBridge (3. Gen Intel Core)</option>
                                <option value="IvyBridge-IBRS">IvyBridge-IBRS (Spectre v1 Schutz)</option>
                                <option value="Haswell">Haswell (4. Gen Intel Core)</option>
                                <option value="Haswell-noTSX">Haswell-noTSX (TSX deaktiviert)</option>
                                <option value="Haswell-IBRS">Haswell-IBRS (TSX + Spectre v1 Schutz)</option>
                                <option value="Haswell-noTSX-IBRS">Haswell-noTSX-IBRS (TSX deaktiviert)</option>
                                <option value="Broadwell">Broadwell (5. Gen Intel Core)</option>
                                <option value="Skylake">Skylake (1. Gen Xeon Scalable)</option>
                                <option value="Skylake-IBRS">Skylake-IBRS (Spectre v1 Schutz)</option>
                                <option value="Skylake-noTSX-IBRS">Skylake-noTSX-IBRS (TSX deaktiviert)</option>
                                <option value="Skylake-v4">Skylake-v4 (EPT switching)</option>
                                <option value="Cascadelake">Cascadelake (2. Gen Xeon Scalable)</option>
                                <option value="Cascadelake-v2">Cascadelake-v2 (arch_capabilities msr)</option>
                                <option value="Cascadelake-v3">Cascadelake-v3 (TSX deaktiviert)</option>
                                <option value="Cascadelake-v4">Cascadelake-v4 (EPT switching)</option>
                                <option value="Cascadelake-v5">Cascadelake-v5 (XSAVES)</option>
                                <option value="Cooperlake">Cooperlake (3. Gen Xeon Scalable 4&8 Socket)</option>
                                <option value="Cooperlake-v2">Cooperlake-v2 (XSAVES)</option>
                                <option value="Icelake">Icelake (3. Gen Xeon Scalable)</option>
                                <option value="Icelake-v2">Icelake-v2 (TSX deaktiviert)</option>
                                <option value="Icelake-v3">Icelake-v3 (arch_capabilities msr)</option>
                                <option value="Icelake-v4">Icelake-v4 (erweiterte Flags)</option>
                                <option value="Icelake-v5">Icelake-v5 (XSAVES)</option>
                                <option value="Icelake-v6">Icelake-v6 (5-level EPT)</option>
                                <option value="SapphireRapids">SapphireRapids (4. Gen Xeon Scalable)</option>
                            </optgroup>
                            <optgroup label="AMD Prozessoren">
                                <option value="Opteron_G3">Opteron_G3 (K10)</option>
                                <option value="Opteron_G4">Opteron_G4 (Bulldozer)</option>
                                <option value="Opteron_G5">Opteron_G5 (Piledriver)</option>
                                <option value="EPYC">EPYC (1. Gen Zen)</option>
                                <option value="EPYC-IBPB">EPYC-IBPB (Spectre v1 Schutz)</option>
                                <option value="EPYC-v3">EPYC-v3 (erweiterte Flags)</option>
                                <option value="EPYC-Rome">EPYC-Rome (2. Gen Zen)</option>
                                <option value="EPYC-Rome-v2">EPYC-Rome-v2 (Spectre v2, v4 Schutz)</option>
                                <option value="EPYC-Milan">EPYC-Milan (3. Gen Zen)</option>
                                <option value="EPYC-Milan-v2">EPYC-Milan-v2 (erweiterte Flags)</option>
                            </optgroup>
                            <optgroup label="Allgemeine Typen">
                                <option value="kvm64">kvm64 (64-bit KVM)</option>
                                <option value="qemu64">qemu64 (64-bit QEMU)</option>
                                <option value="qemu32">qemu32 (32-bit QEMU)</option>
                            </optgroup>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_memory">RAM (MB) *</label>
                        <div class="d-flex align-items-center">
                            <input type="range" class="form-range me-3" id="vm_memory_slider" min="512" max="8192" value="2048" step="128">
                            <input type="number" class="form-control" id="vm_memory" name="memory" value="2048" required min="512" style="width: 120px;">
                            <span class="ms-2 text-muted">MB</span>
                        </div>
                        <div class="form-text">
                            <small>Min: 512 MB | Max: <span id="vm_memory_max">8192</span> MB</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="vm_manual_memory" name="manual_memory" value="1">
                            <label class="form-check-label" for="vm_manual_memory">
                                Manuell setzen
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row" id="vm_memory_advanced" style="display: none;">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_shares">Memory Shares</label>
                        <input type="number" class="form-control" id="vm_shares" name="shares" value="1000" min="0" max="50000">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_cpulimit">CPU Limit (0 = unbegrenzt)</label>
                        <input type="number" class="form-control" id="vm_cpulimit" name="cpulimit" value="0" min="0" max="128" step="0.1">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="vm_manual_cpu" name="manual_cpu" value="1">
                            <label class="form-check-label" for="vm_manual_cpu">
                                CPU Units manuell setzen
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row" id="vm_cpu_advanced" style="display: none;">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_cpuunits">CPU Units</label>
                        <input type="number" class="form-control" id="vm_cpuunits" name="cpuunits" value="1024" min="1" max="262144">
                    </div>
                </div>
            </div>
            </div>

            <!-- Speicher Konfiguration -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary collapsible-header" data-bs-toggle="collapse" data-bs-target="#storage-section" aria-expanded="false" style="cursor: pointer; user-select: none;" title="Aufklappen">
                        <i class="fas fa-hdd"></i> Speicher
                        <span class="badge bg-info ms-2">Aufklappen 🔽</span>
                        <i class="fas fa-chevron-down float-end"></i>
                    </h5>
                    <hr>
                </div>
            </div>
            <div class="collapse" id="storage-section">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_storage">Default Storage *</label>
                        <select class="form-control" id="vm_storage" name="storage" required>
                            <option value="">Storage auswählen...</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_disk_preset">SCSI Disk 0 Größe *</label>
                        <select class="form-control" id="vm_disk_preset">
                            <option value="8">8 GB</option>
                            <option value="16">16 GB</option>
                            <option value="32">32 GB</option>
                            <option value="manual">Manuelle Größe</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row" id="vm_disk_manual_row" style="display: none;">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_disk_manual">Manuelle Größe (GB, max. 1024)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="vm_disk_manual" min="1" max="1024" placeholder="z. B. 64">
                            <span class="input-group-text">GB</span>
                        </div>
                    </div>
                </div>
            </div>

            <input type="hidden" id="vm_scsi0_hidden" name="scsi0" value="">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_scsihw">SCSI Controller</label>
                        <select class="form-control" id="vm_scsihw" name="scsihw">
                            <option value="lsi">lsi</option>
                            <option value="lsi53c810">lsi53c810</option>
                            <option value="virtio-scsi-pci">virtio-scsi-pci</option>
                            <option value="virtio-scsi-single">virtio-scsi-single</option>
                            <option value="megasas">megasas</option>
                            <option value="pvscsi">pvscsi</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_bootdisk">Boot Disk</label>
                        <input type="text" class="form-control" id="vm_bootdisk" name="bootdisk" placeholder="scsi0">
                    </div>
                    </div>
                </div>
            </div>
            
            <!-- Netzwerk Konfiguration -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary collapsible-header" data-bs-toggle="collapse" data-bs-target="#network-section" aria-expanded="false" style="cursor: pointer; user-select: none;" title="Aufklappen">
                        <i class="fas fa-network-wired"></i> Netzwerk
                        <span class="badge bg-info ms-2">Aufklappen 🔽</span>
                        <i class="fas fa-chevron-down float-end"></i>
                    </h5>
                    <hr>
                </div>
            </div>
            <div class="collapse" id="network-section">

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="vm_net_model">Netzwerkkarte</label>
                        <select class="form-control" id="vm_net_model">
                            <option value="virtio">virtio (empfohlen)</option>
                            <option value="e1000">e1000</option>
                            <option value="e1000e">e1000e</option>
                            <option value="rtl8139">rtl8139</option>
                            <option value="vmxnet3">vmxnet3</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="vm_bridge">Bridge *</label>
                        <select class="form-control" id="vm_bridge" name="bridge" required>
                            <option value="">Bridge auswählen...</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
            <div class="form-group mb-3">
                        <label for="vm_mac">MAC-Adresse (optional)</label>
                <input type="text" class="form-control" id="vm_mac" name="mac" placeholder="aa:bb:cc:dd:ee:ff" pattern="[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}">
            </div>
                </div>
            </div>
            
            <!-- Hidden field für net0 wird dynamisch befüllt -->
            <input type="hidden" id="vm_net0_hidden" name="net0" value="">
            </div>

            <!-- Boot und OS Konfiguration -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary collapsible-header" data-bs-toggle="collapse" data-bs-target="#boot-os-section" aria-expanded="false" style="cursor: pointer; user-select: none;" title="Aufklappen">
                        <i class="fas fa-boot"></i> Boot und Betriebssystem
                        <span class="badge bg-info ms-2">Aufklappen 🔽</span>
                        <i class="fas fa-chevron-down float-end"></i>
                    </h5>
                    <hr>
                </div>
            </div>
            <div class="collapse" id="boot-os-section">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_ostype">OS Typ *</label>
                        <select class="form-control" id="vm_ostype" name="ostype" required>
                            <option value="l26">Linux 2.6/3.x/4.x/5.x/6.x (64-bit)</option>
                            <option value="l24">Linux 2.4 (64-bit)</option>
                            <option value="other">Other</option>
                            <option value="wxp">Microsoft Windows XP</option>
                            <option value="w2k">Microsoft Windows 2000</option>
                            <option value="w2k3">Microsoft Windows 2003</option>
                            <option value="w2k8">Microsoft Windows 2008</option>
                            <option value="wvista">Microsoft Windows Vista</option>
                            <option value="win7">Microsoft Windows 7</option>
                            <option value="win8">Microsoft Windows 8/2012/2012r2</option>
                            <option value="win10">Microsoft Windows 10/2016/2019</option>
                            <option value="win11">Microsoft Windows 11/2022/2025</option>
                            <option value="solaris">Solaris/OpenSolaris/OpenIndiania</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_bios">BIOS</label>
                        <select class="form-control" id="vm_bios" name="bios">
                            <option value="seabios">SeaBIOS</option>
                            <option value="ovmf">OVMF (UEFI)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_machine">Maschine</label>
                        <input type="text" class="form-control" id="vm_machine" name="machine" placeholder="pc" value="pc">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_boot">Boot Order</label>
                        <input type="text" class="form-control" id="vm_boot" name="boot" value="order=scsi0;ide2;net0" placeholder="order=scsi0;ide2;net0">
                    </div>
                </div>
            </div>

            <!-- CD/DVD und ISO -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_ide2">CD/DVD Laufwerk (ISO)</label>
                        <select class="form-control" id="vm_ide2" name="ide2">
                            <option value="">Kein ISO ausgewählt</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_cdrom">CD-ROM (Alias für ide2)</label>
                        <input type="text" class="form-control" id="vm_cdrom" name="cdrom" placeholder="local:iso/ubuntu-22.04-server-amd64.iso" readonly>
                    </div>
                </div>
            </div>
            </div>

            <!-- Erweiterte Optionen -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary collapsible-header" data-bs-toggle="collapse" data-bs-target="#advanced-section" aria-expanded="false" style="cursor: pointer; user-select: none;" title="Aufklappen">
                        <i class="fas fa-cogs"></i> Erweiterte Optionen
                        <span class="badge bg-info ms-2">Aufklappen 🔽</span>
                        <i class="fas fa-chevron-down float-end"></i>
                    </h5>
                    <hr>
                </div>
            </div>
            <div class="collapse" id="advanced-section">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_tags">Tags (kommagetrennt)</label>
                        <input type="text" class="form-control" id="vm_tags" name="tags" placeholder="production,web,linux">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_hookscript">Hook Script</label>
                        <input type="text" class="form-control" id="vm_hookscript" name="hookscript" placeholder="/var/lib/vz/snippets/hookscript.pl">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_onboot" name="onboot" value="1">
                        <label class="form-check-label" for="vm_onboot">
                            Beim Boot starten
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_agent" name="agent" value="1" checked>
                        <label class="form-check-label" for="vm_agent">
                            QEMU Agent aktivieren
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_kvm" name="kvm" value="1" checked>
                        <label class="form-check-label" for="vm_kvm">
                            KVM Hardware Virtualization
                        </label>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_acpi" name="acpi" value="1" checked>
                        <label class="form-check-label" for="vm_acpi">
                            ACPI aktivieren
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_localtime" name="localtime" value="1">
                        <label class="form-check-label" for="vm_localtime">
                            Lokale Zeit verwenden
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_tablet" name="tablet" value="1" checked>
                        <label class="form-check-label" for="vm_tablet">
                            Tablet Pointer aktivieren
                        </label>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_autostart" name="autostart" value="1">
                        <label class="form-check-label" for="vm_autostart">
                            Automatischer Neustart nach Crash
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_protection" name="protection" value="1">
                        <label class="form-check-label" for="vm_protection">
                            VM schützen (kein Löschen)
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_template" name="template" value="1">
                        <label class="form-check-label" for="vm_template">
                            Als Template markieren
                        </label>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_start" name="start" value="1">
                        <label class="form-check-label" for="vm_start">
                            VM nach Erstellung starten
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_reboot" name="reboot" value="1" checked>
                        <label class="form-check-label" for="vm_reboot">
                            Neustart erlauben
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="vm_unique" name="unique" value="1">
                        <label class="form-check-label" for="vm_unique">
                            Eindeutige MAC-Adresse
                        </label>
                    </div>
                </div>
            </div>
            </div>

            <!-- Cloud-Init Konfiguration -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary collapsible-header" data-bs-toggle="collapse" data-bs-target="#cloudinit-section" aria-expanded="false" style="cursor: pointer; user-select: none;" title="Aufklappen">
                        <i class="fas fa-cloud"></i> Cloud-Init (optional)
                        <span class="badge bg-info ms-2">Aufklappen 🔽</span>
                        <i class="fas fa-chevron-down float-end"></i>
                    </h5>
                    <hr>
                </div>
            </div>
            <div class="collapse" id="cloudinit-section">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_ciuser">Cloud-Init Benutzer</label>
                        <input type="text" class="form-control" id="vm_ciuser" name="ciuser" placeholder="root">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_cipassword">Cloud-Init Passwort</label>
                        <input type="password" class="form-control" id="vm_cipassword" name="cipassword" placeholder="Passwort">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_citype">Cloud-Init Typ</label>
                        <select class="form-control" id="vm_citype" name="citype">
                            <option value="nocloud">NoCloud</option>
                            <option value="configdrive2">ConfigDrive2</option>
                            <option value="opennebula">OpenNebula</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mb-3 d-flex align-items-end">
                        <input class="form-check-input" type="checkbox" id="vm_ciupgrade" name="ciupgrade" value="1" checked>
                        <label class="form-check-label" for="vm_ciupgrade">
                            Automatisches Paket-Update nach erstem Boot
                        </label>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_sshkeys">SSH Public Keys</label>
                        <textarea class="form-control" id="vm_sshkeys" name="sshkeys" rows="3" placeholder="ssh-rsa AAAAB3NzaC1yc2E..."></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_nameserver">DNS Server</label>
                        <input type="text" class="form-control" id="vm_nameserver" name="nameserver" placeholder="8.8.8.8">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_searchdomain">DNS Suchdomäne</label>
                        <input type="text" class="form-control" id="vm_searchdomain" name="searchdomain" placeholder="example.com">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_cicustom">Cloud-Init Custom Files</label>
                        <input type="text" class="form-control" id="vm_cicustom" name="cicustom" placeholder="meta=local:snippets/meta.yml">
                    </div>
                </div>
            </div>

            <!-- IP-Konfiguration -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_ipconfig0">IP-Konfiguration 0</label>
                        <input type="text" class="form-control" id="vm_ipconfig0" name="ipconfig0" placeholder="ip=dhcp">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_ipconfig1">IP-Konfiguration 1 (optional)</label>
                        <input type="text" class="form-control" id="vm_ipconfig1" name="ipconfig1" placeholder="ip=192.168.1.100/24,gw=192.168.1.1">
                    </div>
                </div>
            </div>
            </div>

            <!-- VGA und Display -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary collapsible-header" data-bs-toggle="collapse" data-bs-target="#vga-section" aria-expanded="false" style="cursor: pointer; user-select: none;" title="Aufklappen">
                        <i class="fas fa-desktop"></i> VGA und Display
                        <span class="badge bg-info ms-2">Aufklappen 🔽</span>
                        <i class="fas fa-chevron-down float-end"></i>
                    </h5>
                    <hr>
                </div>
            </div>
            <div class="collapse" id="vga-section">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_vga">VGA Typ</label>
                        <input type="text" class="form-control" id="vm_vga" name="vga" placeholder="std" value="std">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_keyboard">Tastatur Layout</label>
                        <select class="form-control" id="vm_keyboard" name="keyboard">
                            <option value="de">Deutsch</option>
                            <option value="en-us">English (US)</option>
                            <option value="en-gb">English (GB)</option>
                            <option value="fr">Français</option>
                            <option value="es">Español</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Serial und Parallel -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_serial0">Serial 0</label>
                        <input type="text" class="form-control" id="vm_serial0" name="serial0" placeholder="/dev/ttyS0">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_parallel0">Parallel 0</label>
                        <input type="text" class="form-control" id="vm_parallel0" name="parallel0" placeholder="/dev/parport0">
                    </div>
                </div>
            </div>

            <!-- USB Geräte -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_usb0">USB 0</label>
                        <input type="text" class="form-control" id="vm_usb0" name="usb0" placeholder="host=1234:5678">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_usb1">USB 1 (optional)</label>
                        <input type="text" class="form-control" id="vm_usb1" name="usb1" placeholder="spice">
                    </div>
                </div>
            </div>

            <!-- Watchdog -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_watchdog">Watchdog</label>
                        <input type="text" class="form-control" id="vm_watchdog" name="watchdog" placeholder="i6300esb,action=reset">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_rng0">Random Number Generator</label>
                        <input type="text" class="form-control" id="vm_rng0" name="rng0" placeholder="source=/dev/urandom">
                    </div>
                </div>
            </div>
            </div>

            <!-- Migration -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary collapsible-header" data-bs-toggle="collapse" data-bs-target="#migration-section" aria-expanded="false" style="cursor: pointer; user-select: none;" title="Aufklappen">
                        <i class="fas fa-exchange-alt"></i> Migration
                        <span class="badge bg-info ms-2">Aufklappen 🔽</span>
                        <i class="fas fa-chevron-down float-end"></i>
                    </h5>
                    <hr>
                </div>
            </div>
            <div class="collapse" id="migration-section">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_migrate_downtime">Max. Downtime (Sekunden)</label>
                        <input type="number" class="form-control" id="vm_migrate_downtime" name="migrate_downtime" value="0.1" min="0" step="0.1">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_migrate_speed">Max. Geschwindigkeit (MB/s)</label>
                        <input type="number" class="form-control" id="vm_migrate_speed" name="migrate_speed" value="0" min="0">
                    </div>
                </div>
            </div>

            <!-- Startup/Shutdown -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_startup">Startup Verhalten</label>
                        <input type="text" class="form-control" id="vm_startup" name="startup" placeholder="order=1,up=30,down=30">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_startdate">Start Datum</label>
                        <input type="text" class="form-control" id="vm_startdate" name="startdate" placeholder="now" value="now">
                    </div>
                </div>
            </div>
            </div>

            <!-- Expert Optionen -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="text-primary collapsible-header" data-bs-toggle="collapse" data-bs-target="#expert-section" aria-expanded="false" style="cursor: pointer; user-select: none;" title="Aufklappen">
                        <i class="fas fa-wrench"></i> Expert Optionen
                        <span class="badge bg-info ms-2">Aufklappen 🔽</span>
                        <i class="fas fa-chevron-down float-end"></i>
                    </h5>
                    <hr>
                </div>
            </div>
            <div class="collapse" id="expert-section">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_args">KVM Arguments</label>
                        <textarea class="form-control" id="vm_args" name="args" rows="2" placeholder="-no-reboot -smbios 'type=0,vendor=FOO'"></textarea>
                    </div>
                </div>
                <div class="col-md-6">
            <div class="form-group mb-3">
                        <label for="vm_affinity">CPU Affinity</label>
                        <input type="text" class="form-control" id="vm_affinity" name="affinity" placeholder="0,5,8-11">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_smbios1">SMBIOS Type 1</label>
                        <input type="text" class="form-control" id="vm_smbios1" name="smbios1" placeholder="uuid=12345678-1234-1234-1234-123456789abc">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_vmgenid">VM Generation ID</label>
                        <input type="text" class="form-control" id="vm_vmgenid" name="vmgenid" placeholder="12345678-1234-1234-1234-123456789abc">
                    </div>
    </div>
</div>
</div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="button" class="btn btn-secondary me-md-2" onclick="proxmoxModule.showServerList()">
                    <i class="fas fa-arrow-left"></i> Zurück zur Serverliste
                </button>
                <button type="button" class="btn btn-warning me-md-2" onclick="proxmoxModule.resetVMForm()">
                    <i class="fas fa-undo"></i> Formular zurücksetzen
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> VM erstellen
                </button>
            </div>
        </form>
    </div>
</div>