<?php
// =============================================================================
// CONFIG CLASS
// =============================================================================
class Config {
    const DB_HOST 				= 'localhost';									//MySQL Host
    const DB_NAME 				= 'server_management';							//MySQL DB Name
    const DB_USER 				= '';										//MySQL User
    const DB_PASS 				= '';									//MySQL Password
    const DB_USEING 			= True;											//MySQL Useing

    const PROXMOX_HOST 			= 'https://your-server.com:8006';			//ProxmoxServer
    const PROXMOX_USER 			= '';								//Proxmox User (@pam or @pve)
    const PROXMOX_PASSWORD 		= '';								//Proxmox Password
    const PROXMOX_USEING 		= false;											//Proxmox Useing
    
    const ISPCONFIG_HOST 		= 'https://your-server.com:8080';			//ISPConfig 3 Server
    const ISPCONFIG_USER 		= '';										//ISPConfig 3 User
    const ISPCONFIG_PASSWORD 	= '';									//ISPConfig 3 Password
    const ISPCONFIG_USEING 		= false;											//ISPConfig Useing
    
    const OVH_APPLICATION_KEY 	= '';							//OVH Application Key
    const OVH_APPLICATION_SECRET = '';			//OVH Application Secret
    const OVH_CONSUMER_KEY 		= '';			//OVH Costumer key
    const OVH_ENDPOINT 			= 'ovh-eu';										//OVH API Server (ovh-eu, ovh-us, ovh-ca)
    const OVH_USEING 			= false;											//OVH Useing
    
    const OGP_HOST 				= 'https://your-server.com';			//OGP Server URL
    const OGP_USER 				= '';										//OGP Panel User
    const OGP_PASSWORD 			= '';									//OGP Panel Password
    const OGP_TOKEN 			= '';								//OGP Panel Token
    const OGP_USEING 			= false;											//OGP Useing
}
