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
                            <a class="nav-link <?= $_GET['mod'] == $plugin_key ? 'active' : '' ?>" 
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
                            include('module/'.$_GET['mod'].'/templates/main.php'); 
                        } else {
                            echo '<div class="alert alert-info text-center">';
                            echo t('select_module_from_tabs_above');
                            echo '</div>';
                        } ;
                        ?>
                </div>
            </div>
        </div>
    </div>
</div>