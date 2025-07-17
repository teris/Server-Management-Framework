<div id="plugin-area">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="pluginTabs" role="tablist">
                        <?php 
                        $first = true;
                        foreach ($pluginManager->getEnabledPlugins() as $plugin_key => $plugin_info): 
                        ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $first ? 'active' : '' ?>" 
                                    id="<?= $plugin_key ?>-tab" 
                                    data-bs-toggle="tab" 
                                    data-bs-target="#<?= $plugin_key ?>-content" 
                                    type="button" 
                                    role="tab"
                                    onclick="loadPluginContent('<?= $plugin_key ?>')">
                                <?= htmlspecialchars($plugin_info['name'] ?? $plugin_key) ?>
                            </button>
                        </li>
                        <?php 
                        $first = false;
                        endforeach; 
                        ?>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="pluginTabContent">
                        <?php 
                        $first = true;
                        foreach ($pluginManager->getEnabledPlugins() as $plugin_key => $plugin_info): 
                        ?>
                        <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" 
                                id="<?= $plugin_key ?>-content" 
                                role="tabpanel">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden"><?= t('loading') ?></span>
                                </div>
                            </div>
                        </div>
                        <?php 
                        $first = false;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>