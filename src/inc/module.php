<div id="plugin-area">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="pluginTabs" role="tablist">
                        <?php 
                        foreach ($pluginManager->getEnabledPlugins() as $plugin_key => $plugin_info): 
                        ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= (isset($_GET['mod']) && $_GET['mod'] == $plugin_key) ? 'active' : '' ?>" 
                               href="?option=modules&mod=<?= $plugin_key ?>"
                               role="tab">
                                <?= htmlspecialchars($plugin_info['name'] ?? $plugin_key) ?>
                            </a>
                        </li>
                        <?php 
                        endforeach; 
                        ?>
                    </ul>
                </div>
                <div class="card-body">
                    
                        <?php 
                        if(isset($_GET['mod'])){    
                            $mod_key = $_GET['mod'];
                            $module_file = __DIR__ . '/../module/'.$mod_key.'/templates/main.php';
                            
                            if (file_exists($module_file)) {
                                include($module_file);
                            } else {
                                echo '<div class="alert alert-danger text-center">';
                                echo 'Module template not found: ' . htmlspecialchars($module_file);
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="alert alert-info text-center">';
                            echo function_exists('t') ? t('select_module_from_tabs_above') : 'Bitte wählen Sie ein Modul aus den Tabs oben aus.';
                            echo '</div>';
                        }
                        ?>
                </div>
            </div>
        </div>
    </div>
</div>