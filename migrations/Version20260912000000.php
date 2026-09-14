<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE Activity (activityType VARCHAR(255) DEFAULT NULL, data CLOB DEFAULT NULL, streamsAreImported BOOLEAN DEFAULT NULL, markedForDeletion BOOLEAN DEFAULT NULL, activityId VARCHAR(255) NOT NULL, startDateTime DATETIME NOT NULL, sportType VARCHAR(255) NOT NULL, worldType VARCHAR(255) DEFAULT NULL, importSource VARCHAR(255) DEFAULT \'stravaApi\' NOT NULL, externalReferenceId VARCHAR(255) DEFAULT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, distance INTEGER NOT NULL, elevation INTEGER NOT NULL, calories INTEGER DEFAULT NULL, kilojoules INTEGER DEFAULT NULL, averagePower INTEGER DEFAULT NULL, maxPower INTEGER DEFAULT NULL, averageSpeed DOUBLE PRECISION NOT NULL, maxSpeed DOUBLE PRECISION NOT NULL, averageHeartRate INTEGER DEFAULT NULL, maxHeartRate INTEGER DEFAULT NULL, averageCadence INTEGER DEFAULT NULL, movingTimeInSeconds INTEGER NOT NULL, elapsedTimeInSeconds INTEGER DEFAULT NULL, deviceName VARCHAR(255) DEFAULT NULL, connectedSensors CLOB DEFAULT NULL, totalImageCount INTEGER NOT NULL, localImagePaths CLOB DEFAULT NULL, polyline CLOB DEFAULT NULL, routeGeography CLOB DEFAULT NULL, weather CLOB DEFAULT NULL, gearId VARCHAR(255) DEFAULT NULL, isCommute BOOLEAN DEFAULT NULL, isGroupActivity BOOLEAN DEFAULT NULL, workoutType VARCHAR(255) DEFAULT NULL, startingCoordinateLatitude DOUBLE PRECISION DEFAULT NULL, startingCoordinateLongitude DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY (activityId))');
        $this->addSql('CREATE INDEX Activity_startDateTimeIndex ON Activity (startDateTime)');
        $this->addSql('CREATE INDEX Activity_sportType ON Activity (sportType)');
        $this->addSql('CREATE INDEX Activity_gearId ON Activity (gearId)');
        $this->addSql('CREATE INDEX Activity_gearIdStartDateTime ON Activity (gearId, startDateTime)');
        $this->addSql('CREATE INDEX Activity_markedForDeletion ON Activity (markedForDeletion)');
        $this->addSql('CREATE INDEX Activity_streamsAreImported ON Activity (streamsAreImported)');
        $this->addSql('CREATE INDEX Activity_importSource ON Activity (importSource)');
        $this->addSql('CREATE TABLE ActivityBestEffort (activityId VARCHAR(255) NOT NULL, distanceInMeter INTEGER NOT NULL, sportType VARCHAR(255) NOT NULL, timeInSeconds INTEGER NOT NULL, PRIMARY KEY (activityId, distanceInMeter))');
        $this->addSql('CREATE INDEX ActivityBestEffort_sportTypeIndex ON ActivityBestEffort (sportType)');
        $this->addSql('CREATE TABLE ActivityDrivetrainUsage (activityId VARCHAR(255) NOT NULL, position VARCHAR(255) NOT NULL, gearNumber INTEGER NOT NULL, teeth INTEGER NOT NULL, timeInSeconds INTEGER NOT NULL, shiftCount INTEGER NOT NULL, PRIMARY KEY (activityId, position, gearNumber))');
        $this->addSql('CREATE INDEX ActivityDrivetrainUsage_positionTeeth ON ActivityDrivetrainUsage (position, teeth)');
        $this->addSql('CREATE TABLE ActivityLap (lapId VARCHAR(255) NOT NULL, activityId VARCHAR(255) NOT NULL, lapNumber INTEGER NOT NULL, name VARCHAR(255) NOT NULL, elapsedTimeInSeconds INTEGER NOT NULL, movingTimeInSeconds INTEGER NOT NULL, distance INTEGER NOT NULL, averageSpeed DOUBLE PRECISION NOT NULL, minAverageSpeed DOUBLE PRECISION NOT NULL, maxAverageSpeed DOUBLE PRECISION NOT NULL, maxSpeed DOUBLE PRECISION NOT NULL, elevationDifference INTEGER NOT NULL, averageHeartRate INTEGER DEFAULT NULL, PRIMARY KEY (lapId))');
        $this->addSql('CREATE INDEX ActivitySplit_activityId ON ActivityLap (activityId)');
        $this->addSql('CREATE TABLE ActivityRouteSignature (activityId VARCHAR(255) NOT NULL, polylineChecksum VARCHAR(8) NOT NULL, cellCount INTEGER NOT NULL, cells BLOB NOT NULL, waypoints BLOB NOT NULL, PRIMARY KEY (activityId))');
        $this->addSql('CREATE TABLE ActivitySplit (activityId VARCHAR(255) NOT NULL, unitSystem VARCHAR(255) NOT NULL, splitNumber INTEGER NOT NULL, distance INTEGER NOT NULL, elapsedTimeInSeconds INTEGER NOT NULL, movingTimeInSeconds INTEGER NOT NULL, elevationDifference INTEGER NOT NULL, averageSpeed DOUBLE PRECISION NOT NULL, minAverageSpeed DOUBLE PRECISION NOT NULL, maxAverageSpeed INTEGER NOT NULL, paceZone INTEGER NOT NULL, gapPaceInSecondsPerKm DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY (activityId, unitSystem, splitNumber))');
        $this->addSql('CREATE INDEX ActivitySplit_activityIdUnitSystemIndex ON ActivitySplit (activityId, unitSystem)');
        $this->addSql('CREATE TABLE ActivityStream (dataSize INTEGER DEFAULT 0 NOT NULL, activityId VARCHAR(255) NOT NULL, streamType VARCHAR(255) NOT NULL, createdOn DATETIME NOT NULL, data BLOB DEFAULT NULL, PRIMARY KEY (activityId, streamType))');
        $this->addSql('CREATE INDEX ActivityStream_activityIndex ON ActivityStream (activityId)');
        $this->addSql('CREATE INDEX ActivityStream_streamTypeIndex ON ActivityStream (streamType)');
        $this->addSql('CREATE TABLE ActivityStreamMetric (activityId VARCHAR(255) NOT NULL, streamType VARCHAR(255) NOT NULL, metricType VARCHAR(255) NOT NULL, data BLOB NOT NULL, PRIMARY KEY (activityId, streamType, metricType))');
        $this->addSql('CREATE INDEX ActivityStreamMetric_activityIndex ON ActivityStreamMetric (activityId)');
        $this->addSql('CREATE INDEX ActivityStreamMetric_streamTypeIndex ON ActivityStreamMetric (streamType)');
        $this->addSql('CREATE INDEX ActivityStreamMetric_metricTypeIndex ON ActivityStreamMetric (metricType)');
        $this->addSql('CREATE INDEX ActivityStreamMetric_streamTypeMetricType ON ActivityStreamMetric (streamType, metricType)');
        $this->addSql('CREATE TABLE AutomationRule (automationRuleId VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, isEnabled BOOLEAN NOT NULL, stopProcessing BOOLEAN NOT NULL, sortOrder INTEGER NOT NULL, conditions CLOB NOT NULL, actions CLOB NOT NULL, createdOn DATETIME NOT NULL, PRIMARY KEY (automationRuleId))');
        $this->addSql('CREATE TABLE Challenge (challengeId VARCHAR(255) NOT NULL, createdOn DATETIME NOT NULL, name VARCHAR(255) NOT NULL, logoUrl VARCHAR(255) DEFAULT NULL, localLogoUrl VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, PRIMARY KEY (challengeId))');
        $this->addSql('CREATE INDEX Challenge_createdOnIndex ON Challenge (createdOn)');
        $this->addSql('CREATE TABLE ChatMessage (messageId VARCHAR(255) NOT NULL, message CLOB NOT NULL, messageRole VARCHAR(255) NOT NULL, "on" DATETIME NOT NULL, PRIMARY KEY (messageId))');
        $this->addSql('CREATE INDEX ChatMessage_on ON ChatMessage ("on")');
        $this->addSql('CREATE TABLE CombinedActivityStream (activityId VARCHAR(255) NOT NULL, unitSystem VARCHAR(255) NOT NULL, streamTypes VARCHAR(255) NOT NULL, data BLOB NOT NULL, maxYAxisValue INTEGER NOT NULL, PRIMARY KEY (activityId, unitSystem))');
        $this->addSql('CREATE TABLE FileImport (fileImportId VARCHAR(255) NOT NULL, originalFilename VARCHAR(255) NOT NULL, fileContents BLOB DEFAULT NULL, source VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, errorMessage CLOB DEFAULT NULL, activityId VARCHAR(255) DEFAULT NULL, importedOn DATETIME NOT NULL, PRIMARY KEY (fileImportId))');
        $this->addSql('CREATE TABLE Gear (gearId VARCHAR(255) NOT NULL, createdOn DATETIME NOT NULL, name VARCHAR(255) NOT NULL, isRetired BOOLEAN NOT NULL, type VARCHAR(255) DEFAULT \'imported\' NOT NULL, localImagePath VARCHAR(255) DEFAULT NULL, purchasePriceAmount BIGINT DEFAULT NULL, purchasePriceCurrency VARCHAR(3) DEFAULT NULL, PRIMARY KEY (gearId))');
        $this->addSql('CREATE INDEX Gear_type ON Gear (type)');
        $this->addSql('CREATE TABLE GearMaintenanceLog (gearMaintenanceLogId VARCHAR(255) NOT NULL, gearId VARCHAR(255) NOT NULL, maintenanceTaskId VARCHAR(255) NOT NULL, performedOn DATETIME NOT NULL, PRIMARY KEY (gearMaintenanceLogId))');
        $this->addSql('CREATE INDEX GearMaintenanceLog_gearIndex ON GearMaintenanceLog (gearId)');
        $this->addSql('CREATE INDEX GearMaintenanceLog_maintenanceTask ON GearMaintenanceLog (maintenanceTaskId, performedOn)');
        $this->addSql('CREATE TABLE KeyValue ("key" VARCHAR(255) NOT NULL, value CLOB NOT NULL, PRIMARY KEY ("key"))');
        $this->addSql('CREATE TABLE RecordingDevice (id VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, purchasePriceAmount BIGINT DEFAULT NULL, purchasePriceCurrency VARCHAR(3) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE Segment (segmentId VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, sportType VARCHAR(255) NOT NULL, distance INTEGER NOT NULL, maxGradient DOUBLE PRECISION NOT NULL, isFavourite BOOLEAN NOT NULL, climbCategory INTEGER DEFAULT NULL, deviceName VARCHAR(255) DEFAULT NULL, countryCode VARCHAR(255) DEFAULT NULL, detailsHaveBeenImported BOOLEAN DEFAULT NULL, polyline CLOB DEFAULT NULL, averageGradient DOUBLE PRECISION DEFAULT NULL, startingCoordinateLatitude DOUBLE PRECISION DEFAULT NULL, startingCoordinateLongitude DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY (segmentId))');
        $this->addSql('CREATE INDEX Segment_detailsHaveBeenImported ON Segment (detailsHaveBeenImported)');
        $this->addSql('CREATE TABLE SegmentEffort (segmentEffortId VARCHAR(255) NOT NULL, segmentId VARCHAR(255) NOT NULL, activityId VARCHAR(255) NOT NULL, startDateTime DATETIME NOT NULL, name VARCHAR(255) NOT NULL, elapsedTimeInSeconds DOUBLE PRECISION NOT NULL, distance INTEGER NOT NULL, averageWatts DOUBLE PRECISION DEFAULT NULL, averageHeartRate INTEGER DEFAULT NULL, maxHeartRate INTEGER DEFAULT NULL, PRIMARY KEY (segmentEffortId))');
        $this->addSql('CREATE INDEX SegmentEffort_segmentIndex ON SegmentEffort (segmentId)');
        $this->addSql('CREATE INDEX SegmentEffort_activityIndex ON SegmentEffort (activityId)');
        $this->addSql('CREATE INDEX SegmentEffort_segmentElapsedTime ON SegmentEffort (segmentId, elapsedTimeInSeconds)');
        $this->addSql('CREATE INDEX SegmentEffort_segmentStartDateTime ON SegmentEffort (segmentId, startDateTime)');
        $this->addSql('CREATE TABLE Setting (settingsGroup VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, value CLOB NOT NULL, PRIMARY KEY (settingsGroup, name))');
        $this->addSql('CREATE TABLE WebhookEvent (objectId VARCHAR(255) NOT NULL, objectType VARCHAR(255) NOT NULL, aspectType VARCHAR(255) NOT NULL, payload CLOB NOT NULL, PRIMARY KEY (objectId))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE Activity');
        $this->addSql('DROP TABLE ActivityBestEffort');
        $this->addSql('DROP TABLE ActivityDrivetrainUsage');
        $this->addSql('DROP TABLE ActivityLap');
        $this->addSql('DROP TABLE ActivityRouteSignature');
        $this->addSql('DROP TABLE ActivitySplit');
        $this->addSql('DROP TABLE ActivityStream');
        $this->addSql('DROP TABLE ActivityStreamMetric');
        $this->addSql('DROP TABLE AutomationRule');
        $this->addSql('DROP TABLE Challenge');
        $this->addSql('DROP TABLE ChatMessage');
        $this->addSql('DROP TABLE CombinedActivityStream');
        $this->addSql('DROP TABLE FileImport');
        $this->addSql('DROP TABLE Gear');
        $this->addSql('DROP TABLE GearMaintenanceLog');
        $this->addSql('DROP TABLE KeyValue');
        $this->addSql('DROP TABLE RecordingDevice');
        $this->addSql('DROP TABLE Segment');
        $this->addSql('DROP TABLE SegmentEffort');
        $this->addSql('DROP TABLE Setting');
        $this->addSql('DROP TABLE WebhookEvent');
    }
}
