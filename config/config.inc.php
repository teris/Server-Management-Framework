<?php
// =============================================================================
// CONFIG CLASS
// =============================================================================
class Config {
    const DB_HOST 				= 'localhost';									//MySQL Host
    const DB_NAME 				= '';							//MySQL DB Name
    const DB_USER 				= '';										//MySQL User
    const DB_PASS 				= '';									//MySQL Password
    
    const PROXMOX_HOST 			= 'https://your-server.com:8006';			//ProxmoxServer
    const PROXMOX_USER 			= 'youruser@pve';								//Proxmox User (@pam or @pve)
    const PROXMOX_PASSWORD 		= '';								//Proxmox Password
    
    const ISPCONFIG_HOST 		= 'https://your-server.com:8080';			//ISPConfig 3 Server
    const ISPCONFIG_USER 		= '';										//ISPConfig 3 User
    const ISPCONFIG_PASSWORD 	= '';									//ISPConfig 3 Password
    
    const OVH_APPLICATION_KEY 	= '';							//OVH Application Key
    const OVH_APPLICATION_SECRET = '';			//OVH Application Secret
    const OVH_CONSUMER_KEY 		= '';			//OVH Costumer key
    const OVH_ENDPOINT 			= 'ovh-eu';										//OVH API Server (ovh-eu, ovh-us, ovh-ca)
}