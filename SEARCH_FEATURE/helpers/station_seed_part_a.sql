SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE station_graph;
TRUNCATE TABLE stations;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO stations (id, name, line, latitude, longitude, sequence, zone) VALUES
(1, 'Churchgate', 'Western', 18.935358, 72.827199, 1, 'South Mumbai'),
(2, 'Marine Lines', 'Western', 18.945764, 72.823719, 2, 'South Mumbai'),
(3, 'Charni Road', 'Western', 18.951844, 72.818240, 3, 'South Mumbai'),
(4, 'Grant Road', 'Western', 18.961980, 72.815948, 4, 'South Mumbai'),
(5, 'Mumbai Central', 'Western', 18.969586, 72.819315, 5, 'South Mumbai'),
(6, 'Mahalaxmi', 'Western', 18.982520, 72.824220, 6, 'South Mumbai'),
(7, 'Lower Parel', 'Western', 18.995000, 72.831500, 7, 'South Mumbai'),
(8, 'Prabhadevi', 'Western', 19.007500, 72.836100, 8, 'South Mumbai'),
(9, 'Dadar', 'Western', 19.019227, 72.842848, 9, 'South Mumbai'),
(10, 'Matunga Road', 'Western', 19.027815, 72.846681, 10, 'Suburbs'),
(11, 'Mahim Junction', 'Western', 19.041000, 72.847100, 11, 'Suburbs'),
(12, 'Bandra', 'Western', 19.055069, 72.840143, 12, 'Suburbs'),
(13, 'Khar Road', 'Western', 19.069100, 72.840200, 13, 'Suburbs'),
(14, 'Santacruz', 'Western', 19.079289, 72.847118, 14, 'Suburbs'),
(15, 'Vile Parle', 'Western', 19.099910, 72.844004, 15, 'Suburbs'),
(16, 'Andheri', 'Western', 19.119698, 72.846420, 16, 'Suburbs'),
(17, 'Jogeshwari', 'Western', 19.136000, 72.848800, 17, 'Suburbs'),
(18, 'Ram Mandir', 'Western', 19.151023, 72.850147, 18, 'Suburbs'),
(19, 'Goregaon', 'Western', 19.165107, 72.849597, 19, 'Suburbs'),
(20, 'Malad', 'Western', 19.186800, 72.848500, 20, 'Suburbs'),
(21, 'Kandivali', 'Western', 19.204114, 72.851738, 21, 'Suburbs'),
(22, 'Borivali', 'Western', 19.229068, 72.857363, 22, 'Far Suburbs'),
(23, 'Dahisar', 'Western', 19.249357, 72.859630, 23, 'Far Suburbs'),
(24, 'Mira Road', 'Western', 19.281800, 72.855800, 24, 'Far Suburbs'),
(25, 'Bhayandar', 'Western', 19.309905, 72.853254, 25, 'Far Suburbs'),
(26, 'Naigaon', 'Western', 19.349900, 72.839800, 26, 'Far Suburbs'),
(27, 'Vasai Road', 'Western', 19.382668, 72.832025, 27, 'Far Suburbs'),
(28, 'Nalasopara', 'Western', 19.417200, 72.819600, 28, 'Far Suburbs'),
(29, 'Virar', 'Western', 19.454200, 72.811500, 29, 'Far Suburbs'),
(30, 'Vaitarna', 'Western', 19.518652, 72.849977, 30, 'Far Suburbs'),
(31, 'Saphale', 'Western', 19.577132, 72.821881, 31, 'Far Suburbs'),
(32, 'Kelve Road', 'Western', 19.624053, 72.791175, 32, 'Far Suburbs'),
(33, 'Palghar', 'Western', 19.697888, 72.771889, 33, 'Far Suburbs'),
(34, 'Umroli', 'Western', 19.755761, 72.760451, 34, 'Far Suburbs'),
(35, 'Boisar', 'Western', 19.798486, 72.761452, 35, 'Far Suburbs'),
(36, 'Vangaon', 'Western', 19.882991, 72.763166, 36, 'Far Suburbs'),
(37, 'Dahanu Road', 'Western', 19.991524, 72.743408, 37, 'Far Suburbs'),
(38, 'CSMT', 'Central Main', 18.940833, 72.831861, 1, 'South Mumbai'),
(39, 'Masjid', 'Central Main', 18.946100, 72.836100, 2, 'South Mumbai'),
(40, 'Sandhurst Road', 'Central Main', 18.952500, 72.835400, 3, 'South Mumbai'),
(41, 'Byculla', 'Central Main', 18.976622, 72.832794, 4, 'South Mumbai'),
(42, 'Chinchpokli', 'Central Main', 18.986300, 72.830200, 5, 'South Mumbai'),
(43, 'Currey Road', 'Central Main', 18.994400, 72.833500, 6, 'South Mumbai'),
(44, 'Parel', 'Central Main', 18.995680, 72.830276, 7, 'South Mumbai'),
(45, 'Dadar', 'Central Main', 19.019227, 72.842848, 8, 'South Mumbai'),
(46, 'Matunga', 'Central Main', 19.027436, 72.850147, 9, 'Suburbs'),
(47, 'Sion', 'Central Main', 19.046521, 72.863283, 10, 'Suburbs'),
(48, 'Kurla', 'Central Main', 19.065817, 72.880284, 11, 'Suburbs'),
(49, 'Vidyavihar', 'Central Main', 19.079200, 72.897300, 12, 'Suburbs'),
(50, 'Ghatkopar', 'Central Main', 19.086129, 72.909020, 13, 'Suburbs'),
(51, 'Vikhroli', 'Central Main', 19.112331, 72.928303, 14, 'Suburbs'),
(52, 'Kanjurmarg', 'Central Main', 19.129400, 72.930400, 15, 'Suburbs'),
(53, 'Bhandup', 'Central Main', 19.144236, 72.937971, 16, 'Suburbs'),
(54, 'Nahur', 'Central Main', 19.153900, 72.946300, 17, 'Suburbs'),
(55, 'Mulund', 'Central Main', 19.171645, 72.956266, 18, 'Suburbs'),
(56, 'Thane', 'Central Main', 19.184614, 72.971028, 19, 'Suburbs'),
(57, 'Kalwa', 'Central Main', 19.199700, 72.993700, 20, 'Navi Mumbai'),
(58, 'Mumbra', 'Central Main', 19.189500, 73.024800, 21, 'Navi Mumbai'),
(59, 'Diva Junction', 'Central Main', 19.187300, 73.044100, 22, 'Navi Mumbai'),
(60, 'Dombivli', 'Central Main', 19.218400, 73.086700, 23, 'Navi Mumbai'),
(61, 'Thakurli', 'Central Main', 19.223800, 73.099100, 24, 'Navi Mumbai'),
(62, 'Kalyan Junction', 'Central Main', 19.235822, 73.130810, 25, 'Navi Mumbai'),
(63, 'Kalyan Junction', 'Central Kasara Branch', 19.235822, 73.130810, 1, 'Navi Mumbai'),
(64, 'Shahad', 'Central Kasara Branch', 19.245115, 73.158735, 2, 'Navi Mumbai'),
(65, 'Ambivli', 'Central Kasara Branch', 19.268311, 73.172162, 3, 'Navi Mumbai'),
(66, 'Titwala', 'Central Kasara Branch', 19.297236, 73.203549, 4, 'Navi Mumbai'),
(67, 'Khadavli', 'Central Kasara Branch', 19.356852, 73.219242, 5, 'Navi Mumbai'),
(68, 'Vasind', 'Central Kasara Branch', 19.406444, 73.267163, 6, 'Navi Mumbai'),
(69, 'Asangaon', 'Central Kasara Branch', 19.439747, 73.308012, 7, 'Navi Mumbai'),
(70, 'Atgaon', 'Central Kasara Branch', 19.503594, 73.328218, 8, 'Navi Mumbai'),
(71, 'Thansit', 'Central Kasara Branch', 19.550510, 73.352101, 9, 'Navi Mumbai'),
(72, 'Khardi', 'Central Kasara Branch', 19.580443, 73.394078, 10, 'Navi Mumbai'),
(73, 'Oombermali', 'Central Kasara Branch', 19.628367, 73.422953, 11, 'Navi Mumbai'),
(74, 'Kasara', 'Central Kasara Branch', 19.645970, 73.472359, 12, 'Navi Mumbai'),
(75, 'Kalyan Junction', 'Central Khopoli Branch', 19.235822, 73.130810, 1, 'Navi Mumbai'),
(76, 'Vithalwadi', 'Central Khopoli Branch', 19.231900, 73.146500, 2, 'Navi Mumbai'),
(77, 'Ulhasnagar', 'Central Khopoli Branch', 19.221500, 73.164300, 3, 'Navi Mumbai'),
(78, 'Ambernath', 'Central Khopoli Branch', 19.206900, 73.187200, 4, 'Navi Mumbai'),
(79, 'Badlapur', 'Central Khopoli Branch', 19.167800, 73.226300, 5, 'Navi Mumbai'),
(80, 'Vangani', 'Central Khopoli Branch', 19.094332, 73.300895, 6, 'Navi Mumbai'),
(81, 'Neral', 'Central Khopoli Branch', 19.027431, 73.318437, 7, 'Navi Mumbai'),
(82, 'Bhivpuri Road', 'Central Khopoli Branch', 18.970549, 73.331437, 8, 'South Mumbai'),
(83, 'Karjat', 'Central Khopoli Branch', 18.913079, 73.320849, 9, 'South Mumbai'),
(84, 'Palasdhari', 'Central Khopoli Branch', 18.884354, 73.320813, 10, 'South Mumbai'),
(85, 'Kelavli', 'Central Khopoli Branch', 18.844545, 73.318912, 11, 'South Mumbai'),
(86, 'Dolavli', 'Central Khopoli Branch', 18.833163, 73.320344, 12, 'South Mumbai'),
(87, 'Lowjee', 'Central Khopoli Branch', 18.809181, 73.335386, 13, 'South Mumbai'),
(88, 'Khopoli', 'Central Khopoli Branch', 18.788473, 73.346071, 14, 'South Mumbai'),
(89, 'CSMT', 'Harbour CSMT-Panvel', 18.940833, 72.831861, 1, 'South Mumbai'),
(90, 'Masjid', 'Harbour CSMT-Panvel', 18.946100, 72.836100, 2, 'South Mumbai'),
(91, 'Sandhurst Road', 'Harbour CSMT-Panvel', 18.952500, 72.835400, 3, 'South Mumbai'),
(92, 'Dockyard Road', 'Harbour CSMT-Panvel', 18.967477, 72.844548, 4, 'South Mumbai'),
(93, 'Reay Road', 'Harbour CSMT-Panvel', 18.977551, 72.844101, 5, 'South Mumbai'),
(94, 'Cotton Green', 'Harbour CSMT-Panvel', 18.986100, 72.843600, 6, 'South Mumbai'),
(95, 'Sewri', 'Harbour CSMT-Panvel', 19.000300, 72.855100, 7, 'South Mumbai'),
(96, 'Wadala Road', 'Harbour CSMT-Panvel', 19.016226, 72.859011, 8, 'South Mumbai'),
(97, 'GTB Nagar', 'Harbour CSMT-Panvel', 19.037300, 72.866000, 9, 'Suburbs'),
(98, 'Chunabhatti', 'Harbour CSMT-Panvel', 19.049400, 72.875200, 10, 'Suburbs'),
(99, 'Kurla', 'Harbour CSMT-Panvel', 19.065817, 72.880284, 11, 'Suburbs'),
(100, 'Tilak Nagar', 'Harbour CSMT-Panvel', 19.067300, 72.893100, 12, 'Suburbs'),
(101, 'Chembur', 'Harbour CSMT-Panvel', 19.062632, 72.901140, 13, 'Suburbs'),
(102, 'Govandi', 'Harbour CSMT-Panvel', 19.055300, 72.915200, 14, 'Suburbs'),
(103, 'Mankhurd', 'Harbour CSMT-Panvel', 19.048518, 72.932336, 15, 'Suburbs'),
(104, 'Vashi', 'Harbour CSMT-Panvel', 19.063248, 72.998797, 16, 'Navi Mumbai'),
(105, 'Sanpada', 'Harbour CSMT-Panvel', 19.062800, 73.009400, 17, 'Navi Mumbai'),
(106, 'Juinagar', 'Harbour CSMT-Panvel', 19.052600, 73.018400, 18, 'Navi Mumbai'),
(107, 'Nerul', 'Harbour CSMT-Panvel', 19.033594, 73.018164, 19, 'Navi Mumbai'),
(108, 'Seawoods-Darave', 'Harbour CSMT-Panvel', 19.021819, 73.019159, 20, 'Navi Mumbai'),
(109, 'Belapur CBD', 'Harbour CSMT-Panvel', 19.019008, 73.039130, 21, 'South Mumbai'),
(110, 'Kharghar', 'Harbour CSMT-Panvel', 19.025773, 73.059185, 22, 'Navi Mumbai'),
(111, 'Mansarovar', 'Harbour CSMT-Panvel', 19.016434, 73.080655, 23, 'South Mumbai'),
(112, 'Khandeshwar', 'Harbour CSMT-Panvel', 19.007350, 73.095283, 24, 'South Mumbai'),
(113, 'Panvel', 'Harbour CSMT-Panvel', 18.989166, 73.121222, 25, 'South Mumbai'),
(114, 'Andheri', 'Harbour Andheri-Panvel', 19.119698, 72.846420, 1, 'Suburbs'),
(115, 'Vile Parle', 'Harbour Andheri-Panvel', 19.099910, 72.844004, 2, 'Suburbs'),
(116, 'Santacruz', 'Harbour Andheri-Panvel', 19.079289, 72.847118, 3, 'Suburbs'),
(117, 'Khar Road', 'Harbour Andheri-Panvel', 19.069100, 72.840200, 4, 'Suburbs'),
(118, 'Bandra', 'Harbour Andheri-Panvel', 19.055069, 72.840143, 5, 'Suburbs'),
(119, 'Mahim Junction', 'Harbour Andheri-Panvel', 19.041000, 72.847100, 6, 'Suburbs'),
(120, 'King\'s Circle', 'Harbour Andheri-Panvel', 19.031682, 72.857737, 7, 'Suburbs'),
(121, 'Wadala Road', 'Harbour Andheri-Panvel', 19.016226, 72.859011, 8, 'South Mumbai'),
(122, 'GTB Nagar', 'Harbour Andheri-Panvel', 19.037300, 72.866000, 9, 'Suburbs'),
(123, 'Chunabhatti', 'Harbour Andheri-Panvel', 19.049400, 72.875200, 10, 'Suburbs'),
(124, 'Kurla', 'Harbour Andheri-Panvel', 19.065817, 72.880284, 11, 'Suburbs'),
(125, 'Tilak Nagar', 'Harbour Andheri-Panvel', 19.067300, 72.893100, 12, 'Suburbs'),
(126, 'Chembur', 'Harbour Andheri-Panvel', 19.062632, 72.901140, 13, 'Suburbs'),
(127, 'Govandi', 'Harbour Andheri-Panvel', 19.055300, 72.915200, 14, 'Suburbs'),
(128, 'Mankhurd', 'Harbour Andheri-Panvel', 19.048518, 72.932336, 15, 'Suburbs'),
(129, 'Vashi', 'Harbour Andheri-Panvel', 19.063248, 72.998797, 16, 'Navi Mumbai'),
(130, 'Sanpada', 'Harbour Andheri-Panvel', 19.062800, 73.009400, 17, 'Navi Mumbai'),
(131, 'Juinagar', 'Harbour Andheri-Panvel', 19.052600, 73.018400, 18, 'Navi Mumbai'),
(132, 'Nerul', 'Harbour Andheri-Panvel', 19.033594, 73.018164, 19, 'Navi Mumbai'),
(133, 'Seawoods-Darave', 'Harbour Andheri-Panvel', 19.021819, 73.019159, 20, 'Navi Mumbai'),
(134, 'Belapur CBD', 'Harbour Andheri-Panvel', 19.019008, 73.039130, 21, 'South Mumbai'),
(135, 'Kharghar', 'Harbour Andheri-Panvel', 19.025773, 73.059185, 22, 'Navi Mumbai'),
(136, 'Mansarovar', 'Harbour Andheri-Panvel', 19.016434, 73.080655, 23, 'South Mumbai'),
(137, 'Khandeshwar', 'Harbour Andheri-Panvel', 19.007350, 73.095283, 24, 'South Mumbai'),
(138, 'Panvel', 'Harbour Andheri-Panvel', 18.989166, 73.121222, 25, 'South Mumbai'),
(139, 'Thane', 'Trans Harbour Thane-Panvel', 19.184614, 72.971028, 1, 'Suburbs'),
(140, 'Airoli', 'Trans Harbour Thane-Panvel', 19.157900, 72.993400, 2, 'Navi Mumbai'),
(141, 'Rabale', 'Trans Harbour Thane-Panvel', 19.141500, 72.998200, 3, 'Navi Mumbai'),
(142, 'Ghansoli', 'Trans Harbour Thane-Panvel', 19.121900, 73.007800, 4, 'Navi Mumbai'),
(143, 'Koparkhairane', 'Trans Harbour Thane-Panvel', 19.103900, 73.010800, 5, 'Navi Mumbai'),
(144, 'Turbhe', 'Trans Harbour Thane-Panvel', 19.076165, 73.017662, 6, 'Navi Mumbai'),
(145, 'Juinagar', 'Trans Harbour Thane-Panvel', 19.052600, 73.018400, 7, 'Navi Mumbai'),
(146, 'Nerul', 'Trans Harbour Thane-Panvel', 19.033594, 73.018164, 8, 'Navi Mumbai'),
(147, 'Seawoods-Darave', 'Trans Harbour Thane-Panvel', 19.021819, 73.019159, 9, 'Navi Mumbai'),
(148, 'Belapur CBD', 'Trans Harbour Thane-Panvel', 19.019008, 73.039130, 10, 'South Mumbai'),
(149, 'Kharghar', 'Trans Harbour Thane-Panvel', 19.025773, 73.059185, 11, 'Navi Mumbai'),
(150, 'Mansarovar', 'Trans Harbour Thane-Panvel', 19.016434, 73.080655, 12, 'South Mumbai'),
(151, 'Khandeshwar', 'Trans Harbour Thane-Panvel', 19.007350, 73.095283, 13, 'South Mumbai'),
(152, 'Panvel', 'Trans Harbour Thane-Panvel', 18.989166, 73.121222, 14, 'South Mumbai');

INSERT INTO station_graph (station_id, prev_station_id, next_station_id, line) VALUES
(1, NULL, 2, 'Western'),
(2, 1, 3, 'Western'),
(3, 2, 4, 'Western'),
(4, 3, 5, 'Western'),
(5, 4, 6, 'Western'),
(6, 5, 7, 'Western'),
(7, 6, 8, 'Western'),
(8, 7, 9, 'Western'),
(9, 8, 10, 'Western'),
(10, 9, 11, 'Western'),
(11, 10, 12, 'Western'),
(12, 11, 13, 'Western'),
(13, 12, 14, 'Western'),
(14, 13, 15, 'Western'),
(15, 14, 16, 'Western'),
(16, 15, 17, 'Western'),
(17, 16, 18, 'Western'),
(18, 17, 19, 'Western'),
(19, 18, 20, 'Western'),
(20, 19, 21, 'Western'),
(21, 20, 22, 'Western'),
(22, 21, 23, 'Western'),
(23, 22, 24, 'Western'),
(24, 23, 25, 'Western'),
(25, 24, 26, 'Western'),
(26, 25, 27, 'Western'),
(27, 26, 28, 'Western'),
(28, 27, 29, 'Western'),
(29, 28, 30, 'Western'),
(30, 29, 31, 'Western'),
(31, 30, 32, 'Western'),
(32, 31, 33, 'Western'),
(33, 32, 34, 'Western'),
(34, 33, 35, 'Western'),
(35, 34, 36, 'Western'),
(36, 35, 37, 'Western'),
(37, 36, NULL, 'Western'),
(38, NULL, 39, 'Central Main'),
(39, 38, 40, 'Central Main'),
(40, 39, 41, 'Central Main'),
(41, 40, 42, 'Central Main'),
(42, 41, 43, 'Central Main'),
(43, 42, 44, 'Central Main'),
(44, 43, 45, 'Central Main'),
(45, 44, 46, 'Central Main'),
(46, 45, 47, 'Central Main'),
(47, 46, 48, 'Central Main'),
(48, 47, 49, 'Central Main'),
(49, 48, 50, 'Central Main'),
(50, 49, 51, 'Central Main'),
(51, 50, 52, 'Central Main'),
(52, 51, 53, 'Central Main'),
(53, 52, 54, 'Central Main'),
(54, 53, 55, 'Central Main'),
(55, 54, 56, 'Central Main'),
(56, 55, 57, 'Central Main'),
(57, 56, 58, 'Central Main'),
(58, 57, 59, 'Central Main'),
(59, 58, 60, 'Central Main'),
(60, 59, 61, 'Central Main'),
(61, 60, 62, 'Central Main'),
(62, 61, NULL, 'Central Main'),
(63, NULL, 64, 'Central Kasara Branch'),
(64, 63, 65, 'Central Kasara Branch'),
(65, 64, 66, 'Central Kasara Branch'),
(66, 65, 67, 'Central Kasara Branch'),
(67, 66, 68, 'Central Kasara Branch'),
(68, 67, 69, 'Central Kasara Branch'),
(69, 68, 70, 'Central Kasara Branch'),
(70, 69, 71, 'Central Kasara Branch'),
(71, 70, 72, 'Central Kasara Branch'),
(72, 71, 73, 'Central Kasara Branch'),
(73, 72, 74, 'Central Kasara Branch'),
(74, 73, NULL, 'Central Kasara Branch'),
(75, NULL, 76, 'Central Khopoli Branch'),
(76, 75, 77, 'Central Khopoli Branch'),
(77, 76, 78, 'Central Khopoli Branch'),
(78, 77, 79, 'Central Khopoli Branch'),
(79, 78, 80, 'Central Khopoli Branch'),
(80, 79, 81, 'Central Khopoli Branch'),
(81, 80, 82, 'Central Khopoli Branch'),
(82, 81, 83, 'Central Khopoli Branch'),
(83, 82, 84, 'Central Khopoli Branch'),
(84, 83, 85, 'Central Khopoli Branch'),
(85, 84, 86, 'Central Khopoli Branch'),
(86, 85, 87, 'Central Khopoli Branch'),
(87, 86, 88, 'Central Khopoli Branch'),
(88, 87, NULL, 'Central Khopoli Branch'),
(89, NULL, 90, 'Harbour CSMT-Panvel'),
(90, 89, 91, 'Harbour CSMT-Panvel'),
(91, 90, 92, 'Harbour CSMT-Panvel'),
(92, 91, 93, 'Harbour CSMT-Panvel'),
(93, 92, 94, 'Harbour CSMT-Panvel'),
(94, 93, 95, 'Harbour CSMT-Panvel'),
(95, 94, 96, 'Harbour CSMT-Panvel'),
(96, 95, 97, 'Harbour CSMT-Panvel'),
(97, 96, 98, 'Harbour CSMT-Panvel'),
(98, 97, 99, 'Harbour CSMT-Panvel'),
(99, 98, 100, 'Harbour CSMT-Panvel'),
(100, 99, 101, 'Harbour CSMT-Panvel'),
(101, 100, 102, 'Harbour CSMT-Panvel'),
(102, 101, 103, 'Harbour CSMT-Panvel'),
(103, 102, 104, 'Harbour CSMT-Panvel'),
(104, 103, 105, 'Harbour CSMT-Panvel'),
(105, 104, 106, 'Harbour CSMT-Panvel'),
(106, 105, 107, 'Harbour CSMT-Panvel'),
(107, 106, 108, 'Harbour CSMT-Panvel'),
(108, 107, 109, 'Harbour CSMT-Panvel'),
(109, 108, 110, 'Harbour CSMT-Panvel'),
(110, 109, 111, 'Harbour CSMT-Panvel'),
(111, 110, 112, 'Harbour CSMT-Panvel'),
(112, 111, 113, 'Harbour CSMT-Panvel'),
(113, 112, NULL, 'Harbour CSMT-Panvel'),
(114, NULL, 115, 'Harbour Andheri-Panvel'),
(115, 114, 116, 'Harbour Andheri-Panvel'),
(116, 115, 117, 'Harbour Andheri-Panvel'),
(117, 116, 118, 'Harbour Andheri-Panvel'),
(118, 117, 119, 'Harbour Andheri-Panvel'),
(119, 118, 120, 'Harbour Andheri-Panvel'),
(120, 119, 121, 'Harbour Andheri-Panvel'),
(121, 120, 122, 'Harbour Andheri-Panvel'),
(122, 121, 123, 'Harbour Andheri-Panvel'),
(123, 122, 124, 'Harbour Andheri-Panvel'),
(124, 123, 125, 'Harbour Andheri-Panvel'),
(125, 124, 126, 'Harbour Andheri-Panvel'),
(126, 125, 127, 'Harbour Andheri-Panvel'),
(127, 126, 128, 'Harbour Andheri-Panvel'),
(128, 127, 129, 'Harbour Andheri-Panvel'),
(129, 128, 130, 'Harbour Andheri-Panvel'),
(130, 129, 131, 'Harbour Andheri-Panvel'),
(131, 130, 132, 'Harbour Andheri-Panvel'),
(132, 131, 133, 'Harbour Andheri-Panvel'),
(133, 132, 134, 'Harbour Andheri-Panvel'),
(134, 133, 135, 'Harbour Andheri-Panvel'),
(135, 134, 136, 'Harbour Andheri-Panvel'),
(136, 135, 137, 'Harbour Andheri-Panvel'),
(137, 136, 138, 'Harbour Andheri-Panvel'),
(138, 137, NULL, 'Harbour Andheri-Panvel'),
(139, NULL, 140, 'Trans Harbour Thane-Panvel'),
(140, 139, 141, 'Trans Harbour Thane-Panvel'),
(141, 140, 142, 'Trans Harbour Thane-Panvel'),
(142, 141, 143, 'Trans Harbour Thane-Panvel'),
(143, 142, 144, 'Trans Harbour Thane-Panvel'),
(144, 143, 145, 'Trans Harbour Thane-Panvel'),
(145, 144, 146, 'Trans Harbour Thane-Panvel'),
(146, 145, 147, 'Trans Harbour Thane-Panvel'),
(147, 146, 148, 'Trans Harbour Thane-Panvel'),
(148, 147, 149, 'Trans Harbour Thane-Panvel'),
(149, 148, 150, 'Trans Harbour Thane-Panvel'),
(150, 149, 151, 'Trans Harbour Thane-Panvel'),
(151, 150, 152, 'Trans Harbour Thane-Panvel'),
(152, 151, NULL, 'Trans Harbour Thane-Panvel');

-- Populate nearest station for each active location
-- Location to station mapping with neighboring stations (previous/next)
-- Mapping mode: nearest (nearest = strict Haversine closest station)
-- 1: Vikhroli -> Bhandup (Central Main), prev=Kanjurmarg, next=Nahur, dist=0.869 km
UPDATE location SET nearest_station_id = 53, nearest_station_distance = 0.869 WHERE id = 1;
-- 2: Powai -> Kanjurmarg (Central Main), prev=Vikhroli, next=Bhandup, dist=0.115 km
UPDATE location SET nearest_station_id = 52, nearest_station_distance = 0.115 WHERE id = 2;
-- 3: Andheri West -> Andheri (Western), prev=Vile Parle, next=Jogeshwari, dist=0.959 km
UPDATE location SET nearest_station_id = 16, nearest_station_distance = 0.959 WHERE id = 3;
-- 4: Andheri East -> Jogeshwari (Western), prev=Andheri, next=Ram Mandir, dist=1.218 km
UPDATE location SET nearest_station_id = 17, nearest_station_distance = 1.218 WHERE id = 4;
-- 5: Bandra West -> Santacruz (Western), prev=Khar Road, next=Vile Parle, dist=0.454 km
UPDATE location SET nearest_station_id = 14, nearest_station_distance = 0.454 WHERE id = 5;
-- 6: Bandra East -> Bandra (Western), prev=Mahim Junction, next=Khar Road, dist=0.630 km
UPDATE location SET nearest_station_id = 12, nearest_station_distance = 0.630 WHERE id = 6;
-- 7: Kurla -> Tilak Nagar (Harbour CSMT-Panvel), prev=Kurla, next=Chembur, dist=0.229 km
UPDATE location SET nearest_station_id = 100, nearest_station_distance = 0.229 WHERE id = 7;
-- 8: Ghatkopar West -> Vidyavihar (Central Main), prev=Kurla, next=Ghatkopar, dist=0.725 km
UPDATE location SET nearest_station_id = 49, nearest_station_distance = 0.725 WHERE id = 8;
-- 9: Ghatkopar East -> Tilak Nagar (Harbour CSMT-Panvel), prev=Kurla, next=Chembur, dist=0.448 km
UPDATE location SET nearest_station_id = 100, nearest_station_distance = 0.448 WHERE id = 9;
-- 10: Thane West -> Nahur (Central Main), prev=Bhandup, next=Mulund, dist=0.993 km
UPDATE location SET nearest_station_id = 54, nearest_station_distance = 0.993 WHERE id = 10;
-- 11: Thane East -> Kalwa (Central Main), prev=Thane, next=Mumbra, dist=0.520 km
UPDATE location SET nearest_station_id = 57, nearest_station_distance = 0.520 WHERE id = 11;
-- 12: Mulund West -> Nahur (Central Main), prev=Bhandup, next=Mulund, dist=0.283 km
UPDATE location SET nearest_station_id = 54, nearest_station_distance = 0.283 WHERE id = 12;
-- 13: Mulund East -> Nahur (Central Main), prev=Bhandup, next=Mulund, dist=0.796 km
UPDATE location SET nearest_station_id = 54, nearest_station_distance = 0.796 WHERE id = 13;
-- 14: Bhandup West -> Nahur (Central Main), prev=Bhandup, next=Mulund, dist=1.245 km
UPDATE location SET nearest_station_id = 54, nearest_station_distance = 1.245 WHERE id = 14;
-- 15: Kanjurmarg -> Kanjurmarg (Central Main), prev=Vikhroli, next=Bhandup, dist=0.314 km
UPDATE location SET nearest_station_id = 52, nearest_station_distance = 0.314 WHERE id = 15;
-- 16: Goregaon West -> Malad (Western), prev=Goregaon, next=Kandivali, dist=0.357 km
UPDATE location SET nearest_station_id = 20, nearest_station_distance = 0.357 WHERE id = 16;
-- 17: Goregaon East -> Jogeshwari (Western), prev=Andheri, next=Ram Mandir, dist=0.625 km
UPDATE location SET nearest_station_id = 17, nearest_station_distance = 0.625 WHERE id = 17;
-- 18: Malad West -> Malad (Western), prev=Goregaon, next=Kandivali, dist=1.405 km
UPDATE location SET nearest_station_id = 20, nearest_station_distance = 1.405 WHERE id = 18;
-- 19: Malad East -> Malad (Western), prev=Goregaon, next=Kandivali, dist=1.228 km
UPDATE location SET nearest_station_id = 20, nearest_station_distance = 1.228 WHERE id = 19;
-- 20: Kandivali West -> Malad (Western), prev=Goregaon, next=Kandivali, dist=0.642 km
UPDATE location SET nearest_station_id = 20, nearest_station_distance = 0.642 WHERE id = 20;
-- 21: Kandivali East -> Malad (Western), prev=Goregaon, next=Kandivali, dist=0.710 km
UPDATE location SET nearest_station_id = 20, nearest_station_distance = 0.710 WHERE id = 21;
-- 22: Borivali West -> Malad (Western), prev=Goregaon, next=Kandivali, dist=0.690 km
UPDATE location SET nearest_station_id = 20, nearest_station_distance = 0.690 WHERE id = 22;
-- 23: Borivali East -> Mira Road (Western), prev=Dahisar, next=Bhayandar, dist=1.212 km
UPDATE location SET nearest_station_id = 24, nearest_station_distance = 1.212 WHERE id = 23;
-- 24: Dahisar West -> Mira Road (Western), prev=Dahisar, next=Bhayandar, dist=0.648 km
UPDATE location SET nearest_station_id = 24, nearest_station_distance = 0.648 WHERE id = 24;
-- 25: Dahisar East -> Mira Road (Western), prev=Dahisar, next=Bhayandar, dist=0.877 km
UPDATE location SET nearest_station_id = 24, nearest_station_distance = 0.877 WHERE id = 25;
-- 26: Mira Road -> Mira Road (Western), prev=Dahisar, next=Bhayandar, dist=1.012 km
UPDATE location SET nearest_station_id = 24, nearest_station_distance = 1.012 WHERE id = 26;
-- 27: Navi Mumbai -> Juinagar (Harbour CSMT-Panvel), prev=Sanpada, next=Nerul, dist=1.209 km
UPDATE location SET nearest_station_id = 106, nearest_station_distance = 1.209 WHERE id = 27;
-- 28: Kharghar -> Juinagar (Harbour CSMT-Panvel), prev=Sanpada, next=Nerul, dist=0.513 km
UPDATE location SET nearest_station_id = 106, nearest_station_distance = 0.513 WHERE id = 28;
-- 29: Panvel -> Juinagar (Harbour CSMT-Panvel), prev=Sanpada, next=Nerul, dist=1.018 km
UPDATE location SET nearest_station_id = 106, nearest_station_distance = 1.018 WHERE id = 29;
-- 30: Vashi -> Sanpada (Harbour CSMT-Panvel), prev=Vashi, next=Juinagar, dist=0.625 km
UPDATE location SET nearest_station_id = 105, nearest_station_distance = 0.625 WHERE id = 30;
-- 31: Worli -> Dadar (Western), prev=Prabhadevi, next=Matunga Road, dist=0.405 km
UPDATE location SET nearest_station_id = 9, nearest_station_distance = 0.405 WHERE id = 31;
-- 32: Lower Parel -> Prabhadevi (Western), prev=Lower Parel, next=Dadar, dist=0.920 km
UPDATE location SET nearest_station_id = 8, nearest_station_distance = 0.920 WHERE id = 32;
-- 33: Dadar -> Prabhadevi (Western), prev=Lower Parel, next=Dadar, dist=0.464 km
UPDATE location SET nearest_station_id = 8, nearest_station_distance = 0.464 WHERE id = 33;
-- 34: Chembur -> Govandi (Harbour CSMT-Panvel), prev=Chembur, next=Mankhurd, dist=1.040 km
UPDATE location SET nearest_station_id = 102, nearest_station_distance = 1.040 WHERE id = 34;
-- 35: Sion -> King\'s Circle (Harbour Andheri-Panvel), prev=Mahim Junction, next=Wadala Road, dist=0.406 km
UPDATE location SET nearest_station_id = 120, nearest_station_distance = 0.406 WHERE id = 35;
-- 36: BKC -> Kurla (Central Main), prev=Sion, next=Vidyavihar, dist=1.061 km
UPDATE location SET nearest_station_id = 48, nearest_station_distance = 1.061 WHERE id = 36;
-- 37: Juhu -> Jogeshwari (Western), prev=Andheri, next=Ram Mandir, dist=0.512 km
UPDATE location SET nearest_station_id = 17, nearest_station_distance = 0.512 WHERE id = 37;
-- 38: Vile Parle West -> Khar Road (Western), prev=Bandra, next=Santacruz, dist=0.807 km
UPDATE location SET nearest_station_id = 13, nearest_station_distance = 0.807 WHERE id = 38;
-- 39: Santacruz West -> Khar Road (Western), prev=Bandra, next=Santacruz, dist=0.912 km
UPDATE location SET nearest_station_id = 13, nearest_station_distance = 0.912 WHERE id = 39;
-- 40: Santacruz East -> Bandra (Western), prev=Mahim Junction, next=Khar Road, dist=1.058 km
UPDATE location SET nearest_station_id = 12, nearest_station_distance = 1.058 WHERE id = 40;
-- 41: Jogeshwari West -> Jogeshwari (Western), prev=Andheri, next=Ram Mandir, dist=0.875 km
UPDATE location SET nearest_station_id = 17, nearest_station_distance = 0.875 WHERE id = 41;
-- 42: Jogeshwari East -> Jogeshwari (Western), prev=Andheri, next=Ram Mandir, dist=0.472 km
UPDATE location SET nearest_station_id = 17, nearest_station_distance = 0.472 WHERE id = 42;
-- 43: Airoli -> Rabale (Trans Harbour Thane-Panvel), prev=Airoli, next=Ghansoli, dist=0.896 km
UPDATE location SET nearest_station_id = 141, nearest_station_distance = 0.896 WHERE id = 43;
-- 44: Belapur -> Turbhe (Trans Harbour Thane-Panvel), prev=Koparkhairane, next=Juinagar, dist=0.708 km
UPDATE location SET nearest_station_id = 144, nearest_station_distance = 0.708 WHERE id = 44;
-- 45: Nerul -> Juinagar (Harbour CSMT-Panvel), prev=Sanpada, next=Nerul, dist=1.272 km
UPDATE location SET nearest_station_id = 106, nearest_station_distance = 1.272 WHERE id = 45;
-- 46: Kalyan -> Vithalwadi (Central Khopoli Branch), prev=Kalyan Junction, next=Ulhasnagar, dist=0.485 km
UPDATE location SET nearest_station_id = 76, nearest_station_distance = 0.485 WHERE id = 46;
-- 47: Dombivli -> Thakurli (Central Main), prev=Dombivli, next=Kalyan Junction, dist=0.349 km
UPDATE location SET nearest_station_id = 61, nearest_station_distance = 0.349 WHERE id = 47;
-- 48: Nalasopara -> Nalasopara (Western), prev=Vasai Road, next=Virar, dist=1.381 km
UPDATE location SET nearest_station_id = 28, nearest_station_distance = 1.381 WHERE id = 48;
-- 49: Virar -> Virar (Western), prev=Nalasopara, next=Vaitarna, dist=0.493 km
UPDATE location SET nearest_station_id = 29, nearest_station_distance = 0.493 WHERE id = 49;
-- 50: Vasai -> Naigaon (Western), prev=Bhayandar, next=Vasai Road, dist=0.859 km
UPDATE location SET nearest_station_id = 26, nearest_station_distance = 0.859 WHERE id = 50;
-- 51: Palghar -> Chembur (Harbour CSMT-Panvel), prev=Tilak Nagar, next=Govandi, dist=0.265 km
UPDATE location SET nearest_station_id = 101, nearest_station_distance = 0.265 WHERE id = 51;
-- 52: Badlapur -> Badlapur (Central Khopoli Branch), prev=Ambernath, next=Vangani, dist=0.886 km
UPDATE location SET nearest_station_id = 79, nearest_station_distance = 0.886 WHERE id = 52;
-- 53: Ulhasnagar -> Ulhasnagar (Central Khopoli Branch), prev=Vithalwadi, next=Ambernath, dist=0.895 km
UPDATE location SET nearest_station_id = 77, nearest_station_distance = 0.895 WHERE id = 53;
-- 54: Neral -> Juinagar (Harbour CSMT-Panvel), prev=Sanpada, next=Nerul, dist=0.677 km
UPDATE location SET nearest_station_id = 106, nearest_station_distance = 0.677 WHERE id = 54;
-- 55: Karjat -> Vidyavihar (Central Main), prev=Kurla, next=Ghatkopar, dist=0.699 km
UPDATE location SET nearest_station_id = 49, nearest_station_distance = 0.699 WHERE id = 55;
-- 56: Titwala -> Ulhasnagar (Central Khopoli Branch), prev=Vithalwadi, next=Ambernath, dist=1.120 km
UPDATE location SET nearest_station_id = 77, nearest_station_distance = 1.120 WHERE id = 56;
-- 57: Bhiwandi -> Thakurli (Central Main), prev=Dombivli, next=Kalyan Junction, dist=0.694 km
UPDATE location SET nearest_station_id = 61, nearest_station_distance = 0.694 WHERE id = 57;
-- 58: Taloja -> Juinagar (Harbour CSMT-Panvel), prev=Sanpada, next=Nerul, dist=0.452 km
UPDATE location SET nearest_station_id = 106, nearest_station_distance = 0.452 WHERE id = 58;
-- 59: Kamothe -> Juinagar (Harbour CSMT-Panvel), prev=Sanpada, next=Nerul, dist=0.770 km
UPDATE location SET nearest_station_id = 106, nearest_station_distance = 0.770 WHERE id = 59;
-- 60: Ulwe -> Sanpada (Harbour CSMT-Panvel), prev=Vashi, next=Juinagar, dist=0.480 km
UPDATE location SET nearest_station_id = 105, nearest_station_distance = 0.480 WHERE id = 60;

UPDATE projects p JOIN location l ON p.location_id = l.id SET p.proj_latitude = l.latitude, p.proj_longitude = l.longitude WHERE p.proj_latitude IS NULL;
