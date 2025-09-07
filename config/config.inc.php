<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
// =============================================================================
// CONFIG CLASS
// =============================================================================
class Config {
    // =============================================================================
    // DATABASE CONFIGURATION
    // =============================================================================
    const DB_TYPE = 'mysql';  // mysql, pgsql, sqlite, mongodb, mariadb
    
    // MySQL/MariaDB Configuration
    const DB_HOST 				= 'localhost';									//MySQL Host
    const DB_NAME 				= 'server_management';							//MySQL DB Name
    const DB_USER 				= 'mysql';										//MySQL User
    const DB_PASS 				= 'password';									//MySQL Password
    const DB_PORT               = 3306;                                         //MySQL Port
    const DB_CHARSET            = 'utf8mb4';                                    //MySQL Charset
    const DB_USEING 			= True;											//MySQL Useing

    // PostgreSQL Configuration
    const DB_PGSQL_HOST         = 'localhost';                                  //PostgreSQL Host
    const DB_PGSQL_NAME         = 'server_management';                          //PostgreSQL DB Name
    const DB_PGSQL_USER         = 'postgres';                                   //PostgreSQL User
    const DB_PGSQL_PASS         = 'password';                                   //PostgreSQL Password
    const DB_PGSQL_PORT         = 5432;                                         //PostgreSQL Port
    const DB_PGSQL_USEING       = false;                                        //PostgreSQL Useing

    // SQLite Configuration
    const DB_SQLITE_PATH        = 'database/server_management.db';              //SQLite Database Path
    const DB_SQLITE_USEING      = false;                                        //SQLite Useing

    // MongoDB Configuration
    const DB_MONGO_HOST         = 'localhost';                                  //MongoDB Host
    const DB_MONGO_PORT         = 27017;                                        //MongoDB Port
    const DB_MONGO_NAME         = 'server_management';                          //MongoDB Database Name
    const DB_MONGO_USER         = 'mongodb';                                    //MongoDB Username (if auth enabled)
    const DB_MONGO_PASS         = 'password';                                   //MongoDB Password (if auth enabled)
    const DB_MONGO_USEING       = false;                                        //MongoDB Useing

    // =============================================================================
    // EXTERNAL SERVICES CONFIGURATION
    // =============================================================================
    const PROXMOX_HOST 			= 'https://yout-server.com:8006';    			//ProxmoxServer
    const PROXMOX_USER 			= 'user@pve';								    //Proxmox User (@pam or @pve)
    const PROXMOX_PASSWORD 		= 'password';								    //Proxmox Password
    const PROXMOX_USEING 		= true;											//Proxmox Useing
    
    const ISPCONFIG_HOST 		= 'https://your-server.com:8080';			    //ISPConfig 3 Server
    const ISPCONFIG_USER 		= 'user';										//ISPConfig 3 User
    const ISPCONFIG_PASSWORD 	= 'password';									//ISPConfig 3 Password
    const ISPCONFIG_USEING 		= true;											//ISPConfig Useing
    
    const OVH_APPLICATION_KEY 	= '';											//OVH Application Key
    const OVH_APPLICATION_SECRET = '';											//OVH Application Secret
    const OVH_CONSUMER_KEY 		= '';											//OVH Costumer key
    const OVH_ENDPOINT 			= 'ovh-eu';										//OVH API Server (ovh-eu, ovh-us, ovh-ca)
    const OVH_USEING 			= true;											//OVH Useing
    
    const OGP_HOST 				= 'https://your-server.com';					//OGP Server URL
    const OGP_USER 				= 'user';										//OGP Panel User
    const OGP_PASSWORD 			= 'password';									//OGP Panel Password
    const OGP_TOKEN 			= '';											//OGP Panel Token
    const OGP_USEING 			= true;											//OGP Useing
    
    // =============================================================================
    // FRONTPANEL E-MAIL CONFIGURATION
    // =============================================================================
    const FRONTPANEL_SUPPORT_EMAIL = 'support@your-server.com';					//Support E-Mail für Kunden
    const FRONTPANEL_SYSTEM_EMAIL = 'system@your-server.com';					//System E-Mail für Automatische Nachrichten
    const FRONTPANEL_ADMIN_EMAIL = 'admin@your-server.com';						//Admin E-Mail für Benachrichtigungen
    const FRONTPANEL_SITE_NAME = 'Server Management System';					//Name der Website für E-Mail-Templates
    const FRONTPANEL_SITE_URL = 'https://your-server.com';						//URL der Website

    // =============================================================================
    // Database Configuration (Legacy - wird durch DB_TYPE ersetzt)
    // =============================================================================
    const TYPE = 'MariaDB';   //MariaDB, MongoDB, DynamoDB,	SQLite, PostgreSQL			//Support E-Mail für Kunden
    }
