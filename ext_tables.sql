CREATE TABLE tx_scheduler_task (
	tx_schedulerascode_identifier varchar(255) DEFAULT '' NOT NULL,
	tx_schedulerascode_hash varchar(64) DEFAULT '' NOT NULL,
	tx_schedulerascode_imported int(11) unsigned DEFAULT '0' NOT NULL,

	KEY tx_schedulerascode_identifier (tx_schedulerascode_identifier)
);
