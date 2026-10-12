<?php

/*
 * All Indian states and union territories with their districts, used to seed
 * the `states` and `districts` tables. `code` is the common two-letter state
 * code and `gst_code` the GST state code (the first two digits of a GSTIN).
 * Renamed districts keep their former name in brackets so search finds both.
 */

return [
    ['name' => 'Andaman and Nicobar Islands', 'code' => 'AN', 'gst_code' => '35', 'type' => 'union_territory', 'districts' => [
        'Nicobar', 'North and Middle Andaman', 'South Andaman',
    ]],
    ['name' => 'Andhra Pradesh', 'code' => 'AP', 'gst_code' => '37', 'type' => 'state', 'districts' => [
        'Alluri Sitharama Raju', 'Anakapalli', 'Anantapur', 'Annamayya', 'Bapatla', 'Chittoor',
        'Dr. B.R. Ambedkar Konaseema', 'East Godavari', 'Eluru', 'Guntur', 'Kakinada', 'Krishna', 'Kurnool',
        'Nandyal', 'NTR', 'Palnadu', 'Parvathipuram Manyam', 'Prakasam', 'Sri Potti Sriramulu Nellore',
        'Sri Sathya Sai', 'Srikakulam', 'Tirupati', 'Visakhapatnam', 'Vizianagaram', 'West Godavari', 'YSR Kadapa',
    ]],
    ['name' => 'Arunachal Pradesh', 'code' => 'AR', 'gst_code' => '12', 'type' => 'state', 'districts' => [
        'Anjaw', 'Bichom', 'Changlang', 'Dibang Valley', 'East Kameng', 'East Siang', 'Itanagar Capital Complex',
        'Kamle', 'Keyi Panyor', 'Kra Daadi', 'Kurung Kumey', 'Leparada', 'Lohit', 'Longding', 'Lower Dibang Valley',
        'Lower Siang', 'Lower Subansiri', 'Namsai', 'Pakke-Kessang', 'Papum Pare', 'Shi Yomi', 'Siang', 'Tawang',
        'Tirap', 'Upper Siang', 'Upper Subansiri', 'West Kameng', 'West Siang',
    ]],
    ['name' => 'Assam', 'code' => 'AS', 'gst_code' => '18', 'type' => 'state', 'districts' => [
        'Bajali', 'Baksa', 'Barpeta', 'Biswanath', 'Bongaigaon', 'Cachar', 'Charaideo', 'Chirang', 'Darrang',
        'Dhemaji', 'Dhubri', 'Dibrugarh', 'Dima Hasao', 'Goalpara', 'Golaghat', 'Hailakandi', 'Hojai', 'Jorhat',
        'Kamrup', 'Kamrup Metropolitan', 'Karbi Anglong', 'Kokrajhar', 'Lakhimpur', 'Majuli', 'Morigaon', 'Nagaon',
        'Nalbari', 'Sivasagar', 'Sonitpur', 'South Salmara-Mankachar', 'Sribhumi (Karimganj)', 'Tamulpur',
        'Tinsukia', 'Udalguri', 'West Karbi Anglong',
    ]],
    ['name' => 'Bihar', 'code' => 'BR', 'gst_code' => '10', 'type' => 'state', 'districts' => [
        'Araria', 'Arwal', 'Aurangabad', 'Banka', 'Begusarai', 'Bhagalpur', 'Bhojpur', 'Buxar', 'Darbhanga',
        'East Champaran', 'Gaya', 'Gopalganj', 'Jamui', 'Jehanabad', 'Kaimur', 'Katihar', 'Khagaria', 'Kishanganj',
        'Lakhisarai', 'Madhepura', 'Madhubani', 'Munger', 'Muzaffarpur', 'Nalanda', 'Nawada', 'Patna', 'Purnia',
        'Rohtas', 'Saharsa', 'Samastipur', 'Saran', 'Sheikhpura', 'Sheohar', 'Sitamarhi', 'Siwan', 'Supaul',
        'Vaishali', 'West Champaran',
    ]],
    ['name' => 'Chandigarh', 'code' => 'CH', 'gst_code' => '04', 'type' => 'union_territory', 'districts' => [
        'Chandigarh',
    ]],
    ['name' => 'Chhattisgarh', 'code' => 'CG', 'gst_code' => '22', 'type' => 'state', 'districts' => [
        'Balod', 'Baloda Bazar', 'Balrampur', 'Bastar', 'Bemetara', 'Bijapur', 'Bilaspur', 'Dantewada', 'Dhamtari',
        'Durg', 'Gariaband', 'Gaurela-Pendra-Marwahi', 'Janjgir-Champa', 'Jashpur', 'Kabirdham', 'Kanker',
        'Khairagarh-Chhuikhadan-Gandai', 'Kondagaon', 'Korba', 'Koriya', 'Mahasamund', 'Manendragarh-Chirmiri-Bharatpur',
        'Mohla-Manpur-Ambagarh Chowki', 'Mungeli', 'Narayanpur', 'Raigarh', 'Raipur', 'Rajnandgaon', 'Sakti',
        'Sarangarh-Bilaigarh', 'Sukma', 'Surajpur', 'Surguja',
    ]],
    ['name' => 'Dadra and Nagar Haveli and Daman and Diu', 'code' => 'DH', 'gst_code' => '26', 'type' => 'union_territory', 'districts' => [
        'Dadra and Nagar Haveli', 'Daman', 'Diu',
    ]],
    ['name' => 'Delhi', 'code' => 'DL', 'gst_code' => '07', 'type' => 'union_territory', 'districts' => [
        'Central Delhi', 'East Delhi', 'New Delhi', 'North Delhi', 'North East Delhi', 'North West Delhi', 'Shahdara',
        'South Delhi', 'South East Delhi', 'South West Delhi', 'West Delhi',
    ]],
    ['name' => 'Goa', 'code' => 'GA', 'gst_code' => '30', 'type' => 'state', 'districts' => [
        'Kushavati', 'North Goa', 'South Goa',
    ]],
    ['name' => 'Gujarat', 'code' => 'GJ', 'gst_code' => '24', 'type' => 'state', 'districts' => [
        'Ahmedabad', 'Amreli', 'Anand', 'Aravalli', 'Banaskantha', 'Bharuch', 'Bhavnagar', 'Botad', 'Chhota Udaipur',
        'Dahod', 'Dang', 'Devbhumi Dwarka', 'Gandhinagar', 'Gir Somnath', 'Jamnagar', 'Junagadh', 'Kheda', 'Kutch',
        'Mahisagar', 'Mehsana', 'Morbi', 'Narmada', 'Navsari', 'Panchmahal', 'Patan', 'Porbandar', 'Rajkot',
        'Sabarkantha', 'Surat', 'Surendranagar', 'Tapi', 'Vadodara', 'Valsad', 'Vav-Tharad',
    ]],
    ['name' => 'Haryana', 'code' => 'HR', 'gst_code' => '06', 'type' => 'state', 'districts' => [
        'Ambala', 'Bhiwani', 'Charkhi Dadri', 'Faridabad', 'Fatehabad', 'Gurugram', 'Hansi', 'Hisar', 'Jhajjar', 'Jind',
        'Kaithal', 'Karnal', 'Kurukshetra', 'Mahendragarh', 'Nuh', 'Palwal', 'Panchkula', 'Panipat', 'Rewari',
        'Rohtak', 'Sirsa', 'Sonipat', 'Yamunanagar',
    ]],
    ['name' => 'Himachal Pradesh', 'code' => 'HP', 'gst_code' => '02', 'type' => 'state', 'districts' => [
        'Bilaspur', 'Chamba', 'Hamirpur', 'Kangra', 'Kinnaur', 'Kullu', 'Lahaul and Spiti', 'Mandi', 'Shimla',
        'Sirmaur', 'Solan', 'Una',
    ]],
    ['name' => 'Jammu and Kashmir', 'code' => 'JK', 'gst_code' => '01', 'type' => 'union_territory', 'districts' => [
        'Anantnag', 'Bandipora', 'Baramulla', 'Budgam', 'Doda', 'Ganderbal', 'Jammu', 'Kathua', 'Kishtwar', 'Kulgam',
        'Kupwara', 'Poonch', 'Pulwama', 'Rajouri', 'Ramban', 'Reasi', 'Samba', 'Shopian', 'Srinagar', 'Udhampur',
    ]],
    ['name' => 'Jharkhand', 'code' => 'JH', 'gst_code' => '20', 'type' => 'state', 'districts' => [
        'Bokaro', 'Chatra', 'Deoghar', 'Dhanbad', 'Dumka', 'East Singhbhum', 'Garhwa', 'Giridih', 'Godda', 'Gumla',
        'Hazaribagh', 'Jamtara', 'Khunti', 'Koderma', 'Latehar', 'Lohardaga', 'Pakur', 'Palamu', 'Ramgarh', 'Ranchi',
        'Sahebganj', 'Seraikela Kharsawan', 'Simdega', 'West Singhbhum',
    ]],
    ['name' => 'Karnataka', 'code' => 'KA', 'gst_code' => '29', 'type' => 'state', 'districts' => [
        'Bagalkot', 'Ballari', 'Belagavi', 'Bengaluru Rural', 'Bengaluru South (Ramanagara)', 'Bengaluru Urban',
        'Bidar', 'Chamarajanagar', 'Chikkaballapur', 'Chikkamagaluru', 'Chitradurga', 'Dakshina Kannada',
        'Davanagere', 'Dharwad', 'Gadag', 'Hassan', 'Haveri', 'Kalaburagi', 'Kodagu', 'Kolar', 'Koppal', 'Mandya',
        'Mysuru', 'Raichur', 'Shivamogga', 'Tumakuru', 'Udupi', 'Uttara Kannada', 'Vijayanagara', 'Vijayapura',
        'Yadgir',
    ]],
    ['name' => 'Kerala', 'code' => 'KL', 'gst_code' => '32', 'type' => 'state', 'districts' => [
        'Alappuzha', 'Ernakulam', 'Idukki', 'Kannur', 'Kasaragod', 'Kollam', 'Kottayam', 'Kozhikode', 'Malappuram',
        'Palakkad', 'Pathanamthitta', 'Thiruvananthapuram', 'Thrissur', 'Wayanad',
    ]],
    ['name' => 'Ladakh', 'code' => 'LA', 'gst_code' => '38', 'type' => 'union_territory', 'districts' => [
        'Changthang', 'Drass', 'Kargil', 'Leh', 'Nubra', 'Sham', 'Zanskar',
    ]],
    ['name' => 'Lakshadweep', 'code' => 'LD', 'gst_code' => '31', 'type' => 'union_territory', 'districts' => [
        'Lakshadweep',
    ]],
    ['name' => 'Madhya Pradesh', 'code' => 'MP', 'gst_code' => '23', 'type' => 'state', 'districts' => [
        'Agar Malwa', 'Alirajpur', 'Anuppur', 'Ashoknagar', 'Balaghat', 'Barwani', 'Betul', 'Bhind', 'Bhopal',
        'Burhanpur', 'Chhatarpur', 'Chhindwara', 'Damoh', 'Datia', 'Dewas', 'Dhar', 'Dindori', 'Guna', 'Gwalior',
        'Harda', 'Indore', 'Jabalpur', 'Jhabua', 'Katni', 'Khandwa', 'Khargone', 'Maihar', 'Mandla', 'Mandsaur',
        'Mauganj', 'Morena', 'Narmadapuram', 'Narsinghpur', 'Neemuch', 'Niwari', 'Pandhurna', 'Panna', 'Raisen',
        'Rajgarh', 'Ratlam', 'Rewa', 'Sagar', 'Satna', 'Sehore', 'Seoni', 'Shahdol', 'Shajapur', 'Sheopur',
        'Shivpuri', 'Sidhi', 'Singrauli', 'Tikamgarh', 'Ujjain', 'Umaria', 'Vidisha',
    ]],
    ['name' => 'Maharashtra', 'code' => 'MH', 'gst_code' => '27', 'type' => 'state', 'districts' => [
        'Ahilyanagar (Ahmednagar)', 'Akola', 'Amravati', 'Beed', 'Bhandara', 'Buldhana', 'Chandrapur',
        'Chhatrapati Sambhajinagar (Aurangabad)', 'Dharashiv (Osmanabad)', 'Dhule', 'Gadchiroli', 'Gondia', 'Hingoli',
        'Jalgaon', 'Jalna', 'Kolhapur', 'Latur', 'Mumbai City', 'Mumbai Suburban', 'Nagpur', 'Nanded', 'Nandurbar',
        'Nashik', 'Palghar', 'Parbhani', 'Pune', 'Raigad', 'Ratnagiri', 'Sangli', 'Satara', 'Sindhudurg', 'Solapur',
        'Thane', 'Wardha', 'Washim', 'Yavatmal',
    ]],
    ['name' => 'Manipur', 'code' => 'MN', 'gst_code' => '14', 'type' => 'state', 'districts' => [
        'Bishnupur', 'Chandel', 'Churachandpur', 'Imphal East', 'Imphal West', 'Jiribam', 'Kakching', 'Kamjong',
        'Kangpokpi', 'Noney', 'Pherzawl', 'Senapati', 'Tamenglong', 'Tengnoupal', 'Thoubal', 'Ukhrul',
    ]],
    ['name' => 'Meghalaya', 'code' => 'ML', 'gst_code' => '17', 'type' => 'state', 'districts' => [
        'East Garo Hills', 'East Jaintia Hills', 'East Khasi Hills', 'Eastern West Khasi Hills', 'North Garo Hills',
        'Ri Bhoi', 'South Garo Hills', 'South West Garo Hills', 'South West Khasi Hills', 'West Garo Hills',
        'West Jaintia Hills', 'West Khasi Hills',
    ]],
    ['name' => 'Mizoram', 'code' => 'MZ', 'gst_code' => '15', 'type' => 'state', 'districts' => [
        'Aizawl', 'Champhai', 'Hnahthial', 'Khawzawl', 'Kolasib', 'Lawngtlai', 'Lunglei', 'Mamit', 'Saitual',
        'Serchhip', 'Siaha',
    ]],
    ['name' => 'Nagaland', 'code' => 'NL', 'gst_code' => '13', 'type' => 'state', 'districts' => [
        'Chumoukedima', 'Dimapur', 'Kiphire', 'Kohima', 'Longleng', 'Meluri', 'Mokokchung', 'Mon', 'Niuland',
        'Noklak', 'Peren', 'Phek', 'Shamator', 'Tseminyu', 'Tuensang', 'Wokha', 'Zunheboto',
    ]],
    ['name' => 'Odisha', 'code' => 'OD', 'gst_code' => '21', 'type' => 'state', 'districts' => [
        'Angul', 'Balangir', 'Balasore', 'Bargarh', 'Bhadrak', 'Boudh', 'Cuttack', 'Deogarh', 'Dhenkanal', 'Gajapati',
        'Ganjam', 'Jagatsinghpur', 'Jajpur', 'Jharsuguda', 'Kalahandi', 'Kandhamal', 'Kendrapara', 'Keonjhar',
        'Khordha', 'Koraput', 'Malkangiri', 'Mayurbhanj', 'Nabarangpur', 'Nayagarh', 'Nuapada', 'Puri', 'Rayagada',
        'Sambalpur', 'Subarnapur', 'Sundargarh',
    ]],
    ['name' => 'Puducherry', 'code' => 'PY', 'gst_code' => '34', 'type' => 'union_territory', 'districts' => [
        'Karaikal', 'Mahe', 'Puducherry', 'Yanam',
    ]],
    ['name' => 'Punjab', 'code' => 'PB', 'gst_code' => '03', 'type' => 'state', 'districts' => [
        'Amritsar', 'Barnala', 'Bathinda', 'Faridkot', 'Fatehgarh Sahib', 'Fazilka', 'Ferozepur', 'Gurdaspur',
        'Hoshiarpur', 'Jalandhar', 'Kapurthala', 'Ludhiana', 'Malerkotla', 'Mansa', 'Moga', 'Pathankot', 'Patiala',
        'Rupnagar', 'Sahibzada Ajit Singh Nagar (Mohali)', 'Sangrur', 'Shaheed Bhagat Singh Nagar',
        'Sri Muktsar Sahib', 'Tarn Taran',
    ]],
    ['name' => 'Rajasthan', 'code' => 'RJ', 'gst_code' => '08', 'type' => 'state', 'districts' => [
        'Ajmer', 'Alwar', 'Balotra', 'Banswara', 'Baran', 'Barmer', 'Beawar', 'Bharatpur', 'Bhilwara', 'Bikaner',
        'Bundi', 'Chittorgarh', 'Churu', 'Dausa', 'Deeg', 'Dholpur', 'Didwana-Kuchaman', 'Dungarpur', 'Hanumangarh',
        'Jaipur', 'Jaisalmer', 'Jalore', 'Jhalawar', 'Jhunjhunu', 'Jodhpur', 'Karauli', 'Khairthal-Tijara', 'Kota',
        'Kotputli-Behror', 'Nagaur', 'Pali', 'Phalodi', 'Pratapgarh', 'Rajsamand', 'Salumbar', 'Sawai Madhopur',
        'Sikar', 'Sirohi', 'Sri Ganganagar', 'Tonk', 'Udaipur',
    ]],
    ['name' => 'Sikkim', 'code' => 'SK', 'gst_code' => '11', 'type' => 'state', 'districts' => [
        'Gangtok', 'Gyalshing', 'Mangan', 'Namchi', 'Pakyong', 'Soreng',
    ]],
    ['name' => 'Tamil Nadu', 'code' => 'TN', 'gst_code' => '33', 'type' => 'state', 'districts' => [
        'Ariyalur', 'Chengalpattu', 'Chennai', 'Coimbatore', 'Cuddalore', 'Dharmapuri', 'Dindigul', 'Erode',
        'Kallakurichi', 'Kancheepuram', 'Kanniyakumari', 'Karur', 'Krishnagiri', 'Madurai', 'Mayiladuthurai',
        'Nagapattinam', 'Namakkal', 'Nilgiris', 'Perambalur', 'Pudukkottai', 'Ramanathapuram', 'Ranipet', 'Salem',
        'Sivaganga', 'Tenkasi', 'Thanjavur', 'Theni', 'Thoothukudi', 'Tiruchirappalli', 'Tirunelveli', 'Tirupathur',
        'Tiruppur', 'Tiruvallur', 'Tiruvannamalai', 'Tiruvarur', 'Vellore', 'Viluppuram', 'Virudhunagar',
    ]],
    ['name' => 'Telangana', 'code' => 'TS', 'gst_code' => '36', 'type' => 'state', 'districts' => [
        'Adilabad', 'Bhadradri Kothagudem', 'Hanumakonda', 'Hyderabad', 'Jagtial', 'Jangaon',
        'Jayashankar Bhupalpally', 'Jogulamba Gadwal', 'Kamareddy', 'Karimnagar', 'Khammam',
        'Komaram Bheem Asifabad', 'Mahabubabad', 'Mahabubnagar', 'Mancherial', 'Medak', 'Medchal-Malkajgiri',
        'Mulugu', 'Nagarkurnool', 'Nalgonda', 'Narayanpet', 'Nirmal', 'Nizamabad', 'Peddapalli', 'Rajanna Sircilla',
        'Ranga Reddy', 'Sangareddy', 'Siddipet', 'Suryapet', 'Vikarabad', 'Wanaparthy', 'Warangal',
        'Yadadri Bhuvanagiri',
    ]],
    ['name' => 'Tripura', 'code' => 'TR', 'gst_code' => '16', 'type' => 'state', 'districts' => [
        'Dhalai', 'Gomati', 'Khowai', 'North Tripura', 'Sepahijala', 'South Tripura', 'Unakoti', 'West Tripura',
    ]],
    ['name' => 'Uttar Pradesh', 'code' => 'UP', 'gst_code' => '09', 'type' => 'state', 'districts' => [
        'Agra', 'Aligarh', 'Ambedkar Nagar', 'Amethi', 'Amroha', 'Auraiya', 'Ayodhya', 'Azamgarh', 'Baghpat',
        'Bahraich', 'Ballia', 'Balrampur', 'Banda', 'Barabanki', 'Bareilly', 'Basti', 'Bhadohi', 'Bijnor', 'Budaun',
        'Bulandshahr', 'Chandauli', 'Chitrakoot', 'Deoria', 'Etah', 'Etawah', 'Farrukhabad', 'Fatehpur', 'Firozabad',
        'Gautam Buddha Nagar', 'Ghaziabad', 'Ghazipur', 'Gonda', 'Gorakhpur', 'Hamirpur', 'Hapur', 'Hardoi',
        'Hathras', 'Jalaun', 'Jaunpur', 'Jhansi', 'Kannauj', 'Kanpur Dehat', 'Kanpur Nagar', 'Kasganj', 'Kaushambi',
        'Kushinagar', 'Lakhimpur Kheri', 'Lalitpur', 'Lucknow', 'Maharajganj', 'Mahoba', 'Mainpuri', 'Mathura', 'Mau',
        'Meerut', 'Mirzapur', 'Moradabad', 'Muzaffarnagar', 'Pilibhit', 'Pratapgarh', 'Prayagraj', 'Rae Bareli',
        'Rampur', 'Saharanpur', 'Sambhal', 'Sant Kabir Nagar', 'Shahjahanpur', 'Shamli', 'Shravasti',
        'Siddharthnagar', 'Sitapur', 'Sonbhadra', 'Sultanpur', 'Unnao', 'Varanasi',
    ]],
    ['name' => 'Uttarakhand', 'code' => 'UK', 'gst_code' => '05', 'type' => 'state', 'districts' => [
        'Almora', 'Bageshwar', 'Chamoli', 'Champawat', 'Dehradun', 'Haridwar', 'Nainital', 'Pauri Garhwal',
        'Pithoragarh', 'Rudraprayag', 'Tehri Garhwal', 'Udham Singh Nagar', 'Uttarkashi',
    ]],
    ['name' => 'West Bengal', 'code' => 'WB', 'gst_code' => '19', 'type' => 'state', 'districts' => [
        'Alipurduar', 'Bankura', 'Birbhum', 'Cooch Behar', 'Dakshin Dinajpur', 'Darjeeling', 'Hooghly', 'Howrah',
        'Jalpaiguri', 'Jhargram', 'Kalimpong', 'Kolkata', 'Malda', 'Murshidabad', 'Nadia', 'North 24 Parganas',
        'Paschim Bardhaman', 'Paschim Medinipur', 'Purba Bardhaman', 'Purba Medinipur', 'Purulia',
        'South 24 Parganas', 'Uttar Dinajpur',
    ]],
];
