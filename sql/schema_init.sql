-- MySQL dump 10.11
--
-- Host: localhost    Database: xbm
-- ------------------------------------------------------
-- Server version	5.0.51a-build1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `backup_snapshots`
--

DROP TABLE IF EXISTS `backup_snapshots`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `backup_snapshots` (
  `backup_snapshot_id` int(10) unsigned NOT NULL auto_increment,
  `type` varchar(64) NOT NULL default '',
  `snapshot_time` datetime default NULL,
  `creation_time` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `status` varchar(64) NOT NULL default 'INITIALIZING',
  `parent_snapshot_id` int(10) unsigned default NULL,
  `scheduled_backup_id` int(10) unsigned NOT NULL,
  `creation_method` varchar(64) default NULL,
  `snapshot_group_num` int(11) default '1',
  PRIMARY KEY  (`backup_snapshot_id`),
  KEY `i_scheduled_backup` (`scheduled_backup_id`),
  KEY `i_parent_snapshot` (`parent_snapshot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `backup_strategies`
--

DROP TABLE IF EXISTS `backup_strategies`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `backup_strategies` (
  `backup_strategy_id` int(10) unsigned NOT NULL default '0',
  `strategy_code` varchar(64) NOT NULL default '',
  `strategy_name` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`backup_strategy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `backup_strategies`
--

LOCK TABLES `backup_strategies` WRITE;
/*!40000 ALTER TABLE `backup_strategies` DISABLE KEYS */;
INSERT INTO `backup_strategies` VALUES (1,'FULLONLY','Full Backup Only'),(2,'CONTINC','Continuous Incremental Backup'),(3,'ROTATING','Rotating sets of Incremental Backups');
/*!40000 ALTER TABLE `backup_strategies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_strategy_params`
--

DROP TABLE IF EXISTS `backup_strategy_params`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `backup_strategy_params` (
  `backup_strategy_param_id` int(10) unsigned NOT NULL auto_increment,
  `backup_strategy_id` int(10) unsigned NOT NULL,
  `param_name` varchar(128) NOT NULL default '',
  `default_value` varchar(64),
  PRIMARY KEY  (`backup_strategy_param_id`),
  KEY `i_backup_strategy_id` (`backup_strategy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `backup_strategy_params`
--

LOCK TABLES `backup_strategy_params` WRITE;
/*!40000 ALTER TABLE `backup_strategy_params` DISABLE KEYS */;
INSERT INTO `backup_strategy_params` VALUES (1,1,'max_snapshots',7),(2,2,'max_snapshots',7),(3,2,'maintain_materialized_copy',1),(4,3,'rotate_method','DAY_OF_WEEK'),(5,3,'rotate_day_of_week',0),(6,3,'max_snapshots_per_group',7),(7,3,'backup_skip_fatal',1),(8,3,'rotate_snapshot_no',7),(9,3,'max_snapshot_groups',2),(10,3,'maintain_materialized_copy',1);
/*!40000 ALTER TABLE `backup_strategy_params` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_volumes`
--

DROP TABLE IF EXISTS `backup_volumes`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `backup_volumes` (
  `backup_volume_id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(128) NOT NULL default '',
  `path` varchar(1024) NOT NULL default '',
  PRIMARY KEY  (`backup_volume_id`),
  UNIQUE KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `hosts`
--

DROP TABLE IF EXISTS `hosts`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `hosts` (
  `host_id` int(10) unsigned NOT NULL auto_increment,
  `hostname` varchar(255) default NULL,
  `description` varchar(256) character set utf8 NOT NULL default '',
  `active` enum('Y','N') default 'Y',
  `staging_path` varchar(1024) character set utf8 NOT NULL default '/tmp',
  `ssh_port` smallint unsigned not null default 22,
  PRIMARY KEY  (`host_id`),
  UNIQUE KEY `i_hostname` (`hostname`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `mysql_types`
--

DROP TABLE IF EXISTS `mysql_types`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `mysql_types` (
  `mysql_type_id` int(10) unsigned NOT NULL auto_increment,
  `type_name` varchar(256) NOT NULL default '',
  `xtrabackup_binary` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`mysql_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `mysql_types`
--

LOCK TABLES `mysql_types` WRITE;
/*!40000 ALTER TABLE `mysql_types` DISABLE KEYS */;
INSERT INTO `mysql_types` VALUES (1,'Percona Server 5.1 w/ InnoDB Plugin','xtrabackup'),(2,'MySQL 5.1 w/ InnoDB Plugin','xtrabackup'),(3,'Percona Server 5.0 w/ built-in InnoDB','xtrabackup_51'),(4,'MySQL 5.0 w/ built-in InnoDB','xtrabackup_51'),(5,'MySQL 5.1 w/ built-in InnoDB','xtrabackup_51'),(6,'Percona Server 5.5','xtrabackup_55'),(7,'MySQL 5.5','xtrabackup_55');
/*!40000 ALTER TABLE `mysql_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `running_backups`
--

DROP TABLE IF EXISTS `running_backups`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `running_backups` (
  `running_backup_id` int(10) unsigned NOT NULL auto_increment,
  `host_id` int(10) unsigned default NULL,
  `scheduled_backup_id` int(10) unsigned default NULL,
  `port` int(10) unsigned default NULL,
  `started` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `staging_tmpdir` varchar(1024) default NULL,
  `pid` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`running_backup_id`),
  UNIQUE KEY `i_port` (`port`),
  UNIQUE KEY `i_scheduled_backup` (`scheduled_backup_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `scheduled_backup_params`
--

DROP TABLE IF EXISTS `scheduled_backup_params`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `scheduled_backup_params` (
  `scheduled_backup_id` int(10) unsigned NOT NULL,
  `backup_strategy_param_id` int(10) unsigned NOT NULL,
  `param_value` varchar(128) default NULL,
  PRIMARY KEY  (`scheduled_backup_id`,`backup_strategy_param_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `scheduled_backups`
--

DROP TABLE IF EXISTS `scheduled_backups`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `scheduled_backups` (
  `scheduled_backup_id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(128) NOT NULL,
  `cron_expression` varchar(128) default NULL,
  `backup_user` varchar(256) NOT NULL default 'mysql',
  `datadir_path` varchar(1024) NOT NULL default '',
  `mysql_user` char(16) NOT NULL,
  `mysql_password` varchar(256) NOT NULL default '',
  `lock_tables` enum('Y','N') default 'N',
  `host_id` int(10) unsigned NOT NULL,
  `active` enum('Y','N') default 'Y',
  `backup_volume_id` int(10) unsigned default NULL,
  `mysql_type_id` int(10) unsigned default NULL,
  `backup_strategy_id` int(10) unsigned NOT NULL default '1',
  `throttle` int(10) unsigned NOT NULL default 0,
  PRIMARY KEY  (`scheduled_backup_id`),
  UNIQUE KEY `i_host_name` (`name`,`host_id`),
  KEY `i_host` (`host_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `queue_tickets`
--

DROP TABLE IF EXISTS `queue_tickets`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `queue_tickets` (
  `queue_ticket_id` int(10) unsigned NOT NULL auto_increment,
  `queue_name` varchar(64) default NULL,
  `pid` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`queue_ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `materialized_snapshots`
--

DROP TABLE IF EXISTS `materialized_snapshots`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `materialized_snapshots` (
  `materialized_snapshot_id` int(11) NOT NULL auto_increment,
  `status` varchar(64) default 'INITIALIZING',
  `creation_time` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `backup_snapshot_id` int(11) NOT NULL,
  `scheduled_backup_id` int(11) NOT NULL,
  PRIMARY KEY  (`materialized_snapshot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;


--
-- Table structure for table `schema_version`
--

DROP TABLE IF EXISTS `schema_version`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `schema_version` (
  `version` int(10) unsigned NOT NULL default '1000'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `schema_version`
--

LOCK TABLES `schema_version` WRITE;
/*!40000 ALTER TABLE `schema_version` DISABLE KEYS */;
INSERT INTO `schema_version` VALUES (1006);
/*!40000 ALTER TABLE `schema_version` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `backup_jobs`
--

DROP TABLE IF EXISTS `backup_jobs`;
CREATE TABLE `backup_jobs` (
    backup_job_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    scheduled_backup_id INT UNSIGNED NOT NULL,
    start_time datetime NOT NULL,
    running_time datetime NULL,
    end_time datetime NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'Initializing',
    pid INT UNSIGNED NOT NULL,
    killed TINYINT UNSIGNED DEFAULT 0,
    PRIMARY KEY (backup_job_id),
    INDEX i_status (status)
) ENGINE=InnoDB CHARSET=utf8;


/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

