BEGIN;
DELETE FROM FileImport;

INSERT INTO FileImport (fileImportId, originalFilename, fileHash, fileContents, source, status, errorMessage, activityId, importedOn) VALUES
-- successes (17)
('fileImport-8f2b1c74-3d19-4a6e-9c02-5b71ad0e93f1','2026-07-13-065338-ELEMNT-BOLT-4F2A-0.fit','3b8a1f5c6d2e4907ab31c5f8e0d47a29b6c1358fe920d4a7c83b15f6d20e94ac',NULL,'fitFile','success',NULL,'activity-19298870749','2026-07-13 07:40:12'),
('fileImport-1a6d40e9-7c85-42b3-9f10-c6e8b2d05473','2026-07-11-085225-ELEMNT-BOLT-4F2A-0.fit','7c41e93a5b0d284f6e1c9a37d5b820fe4a6390c17d2fb548e93a05c6b1d7e482',NULL,'fitFile','success',NULL,'activity-19269212748','2026-07-11 14:05:47'),
('fileImport-c94f7b21-05ea-4d68-8b3c-27f19ad6e850','Zwift-Zone-2-in-Watopia-2026-07-09.fit','e05b7d3196af24c8b6103e7f5a92d48c07be31f9a45d6208cf1935b7e6a02d41',NULL,'fitFile','success',NULL,'activity-19245547169','2026-07-09 18:20:03'),
('fileImport-3e70dca5-9f43-41b7-a2d8-64b0951ce7f2','2026-07-09-065300-ELEMNT-BOLT-4F2A-0.fit','9d2c68e0b437f15a8e37c94d1b60af528d3719ec6a04b8f251c7de3906ba48f7',NULL,'fitFile','success',NULL,'activity-19245698372','2026-07-09 07:35:29'),
('fileImport-5b18e2f6-4a07-49cd-b135-8ce0d7962a34','activity_19231806055.fit','48f0a91d5e7c3b26049fd8137be5ca02936d1478af62e0b3d59c8741ea360bd9',NULL,'fitFile','success',NULL,'activity-19231806055','2026-07-08 16:31:55'),
('fileImport-a2c5904d-8e61-4f37-bd09-13f7e6c8a205','Zwift-Zone-2-in-Watopia-2026-07-08-1103.fit','16b7de490a3c825f7d1e0b64af9358c2e0417db95f38a6c204e1b7935dc8f06a',NULL,'fitFile','success',NULL,'activity-19225950705','2026-07-08 12:12:41'),
('fileImport-df3016b8-72a9-4c05-9e6d-58a1420cb7e3','Zwift-Zone-2-in-Watopia-2026-07-08-1017.fit','b5309ce87f142a6d0b93e7154fc8a2609d7be4310c85f29a7346db01e5f8c2b7',NULL,'fitFile','success',NULL,'activity-19225642533','2026-07-08 12:12:44'),
('fileImport-6c8a71d3-1b52-4e90-84f7-0a9d63e5f218','Zwift-Zone-2-in-France-2026-07-07-1421.fit','c7160b4a9e83d520f61ca7d3948b05e2f3790d16ba4c8e750392fd6b18a04c53',NULL,'fitFile','success',NULL,'activity-19214248178','2026-07-07 15:44:09'),
('fileImport-9e04b6c1-3f78-4a25-b8d0-71c5920ea6f4','Zwift-Zone-2-in-France-2026-07-07-1333.fit','2f95e08c17b3da64095c7fe218b0d3a64719cf5b830e2a4d61b8079fc35ea1d2',NULL,'fitFile','success',NULL,'activity-19213816967','2026-07-07 15:44:12'),
('fileImport-47b2ed80-6c19-4358-9af1-2e0d735c8916','2026-07-06-065621-ELEMNT-BOLT-4F2A-0.fit','83c1470de925f6b8a03e17c95d24bf60719ae3c852b0d4f639ac715e08b2d64f',NULL,'fitFile','success',NULL,'activity-19203736421','2026-07-06 07:31:18'),
('fileImport-b60f39a7-24d8-4e17-85c2-9f1a06e4b7d5','morning_ride_2026-07-05.gpx','5a0e83c7b19d426f0c85a37e1fb69d420c73951ae86bd0f273c149b8e502da67',NULL,'gpxFile','success',NULL,'activity-19193241161','2026-07-05 12:14:33'),
('fileImport-e81c4520-97a3-4b6d-a0f9-3c6d15b8e740','morning_ride_2026-07-04.gpx','df41b9207ce6853a0f27d1b94e05c68a37f0be1d5942ca7803be6f15d27c904b',NULL,'gpxFile','success',NULL,'activity-19173516021','2026-07-04 10:02:51'),
('fileImport-2d759ac3-b0e4-4162-97f8-5e10c3d84b69','Zwift-Cooldown-2026-07-03.fit','604fb28e15c9a730df5b1e04c92a86d3719b0f4e5ca27d18b306ef95a4c1d827',NULL,'fitFile','success',NULL,'activity-19161543403','2026-07-03 13:20:07'),
('fileImport-70a3e18f-c542-49b0-8d16-b9e027f534ca','Zwift-Race-Level-Up-Racing-Stage-5-2026-07-03.fit','a71d0b3e69f4c82750da1c6b95e307f24b8d05a9c6371ef204b95d7c8a0e6132',NULL,'fitFile','success',NULL,'activity-19161351608','2026-07-03 13:20:11'),
('fileImport-fa6820d4-5e37-41c9-b072-4a8c19d3e60b','Zwift-Race-Warmup-2026-07-03.fit','19e4bc07a538d1f620973ce8b04a5d167f2b9038ec541a7d0629bf158a3ed704',NULL,'fitFile','success',NULL,'activity-19160918810','2026-07-03 13:20:15'),
('fileImport-31c7d9e5-8046-4b73-95a1-6f2e08c4b7d0','Zwift-Zone-2-in-Watopia-2026-07-02-1912.fit','8d05719ea6c34bf0912d7e08b53ca641f0937be2d18a45c76e02931fbd5c7a04',NULL,'fitFile','success',NULL,'activity-19153934852','2026-07-02 20:15:38'),
('fileImport-05e6f172-b9c8-4d30-a417-7c0b285e9346','2026-07-02-073542-ELEMNT-BOLT-4F2A-0.fit','4b19e07c6d3a58f210be9c47a05d3f62871c0e94b53df6a2087cd15be934a06f',NULL,'fitFile','success',NULL,'activity-19159418509','2026-07-02 08:47:20'),
-- skipped (2)
('fileImport-c1740ba9-3d68-4e52-90f7-1b8ea06c5d37','Zwift-Zone-2-in-Watopia-2026-07-02-1912-copy.fit',NULL,NULL,'fitFile','skipped','Skipped, activity was already imported',NULL,'2026-07-02 20:16:02'),
('fileImport-96d3e074-5a1b-42fc-8e69-03b7c5d18ae2','activity_19136561964.tcx',NULL,NULL,'tcxFile','skipped','Skipped, activity was already imported',NULL,'2026-07-01 14:03:19'),
-- failed (1)
('fileImport-7f5b60ea-2c93-4187-b0d4-e91a37c8065b','2026-06-30-121222-ELEMNT-BOLT-4F2A-0.fit','ec37b5091da84c62f70b3e15a9d420c86b1f7038e94a5cd2601b7f3e58ad9c14',NULL,'fitFile','failed','Could not parse "2026-06-30-121222-ELEMNT-BOLT-4F2A-0.fit": the file does not contain any GPS or timestamp records, it might be corrupt or incomplete',NULL,'2026-06-30 13:11:44');

COMMIT;
