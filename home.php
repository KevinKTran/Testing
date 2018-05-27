<?php
ob_start();
//set up database attribute
$server = "localhost";  
$database = "mydbpdo";
$user = "root";
$password = "";
//finish setting up database attribute

try {
  //connect to database
  $conn = new PDO("mysql:host=$server;dbname=$database", $user, $password);
  //set the PDO error mode to exception
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//--------------------------------------------------------------------first run

  //create table if not exist
  $sql = "CREATE TABLE IF NOT EXISTS farms ("
		  . "id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, "
		  . "fname VARCHAR(200) NOT NULL, "
		  . "pname VARCHAR(200) NOT NULL, "
		  . "snam VARCHAR(200) NOT NULL, "
		  . "latitude VARCHAR(15) NOT NULL, "
		  . "longitude VARCHAR(15) NOT NULL)";
  $conn->exec($sql);
    
//--------------------------------------------------------------------add_farm

  // insert data to database
  if (isset($_POST["lat"])) {
		$conn->beginTransaction();
		$table = "farms";
		$sql = $conn->prepare("INSERT INTO $table"
				. "(fname, pname, sname, latitude, longitude) VALUES "
				. "(:fname, :pname, :sname, :lat, :lng)");
		$sql->bindParam(':fname', $fname);
		$sql->bindParam(':pname', $pname);
		$sql->bindParam(':sname', $sname);
		$sql->bindParam(':lat', $lat);
		$sql->bindParam(':lng', $lng);
		// collect form data
		$fname = $_POST["farmName"];
		$pname = $_POST["paddockName"];
		$sname = $_POST["siteName"];
		$lat = $_POST["lat"];
		$lng = $_POST["lng"];
		// insert form data to database
		$sql->execute();
		$conn->commit();
		// redirect to avoid resubmitting form
		header('location: home.php');
  }

//--------------------------------------------------------------------remove

  // remove unwanted data from database
  if (isset($_POST["remove_id"])) {
		$conn->beginTransaction();
		$table = "farms";
		$sql = $conn->prepare("DELETE FROM $table WHERE id = :id");
		$sql->bindParam(':id',$id);
		// collect form data
		$id = (int)$_POST["remove_id"];
		// delete unwanted record
		$sql->execute();
		$conn->commit();
		// redirect to avoid resubmitting form
		header('location: home.php');
  }

//--------------------------------------------------------------------edit data

  // modify data in databse
  if (isset($_POST["change_id"])) {
		$conn->beginTransaction();
		$table = "farms";
		$sql = $conn->prepare("UPDATE $table SET fname = :fname,"
				. "pname = :pname, sname = :sname WHERE id = :id");
		$sql->bindParam(':id',$id);
		$sql->bindParam(':fname', $fname);
		$sql->bindParam(':pname', $pname);
		$sql->bindParam(':sname', $sname);
		// collect form data
		$id = (int)$_POST["change_id"];
		$fname = $_POST["farmName"];
		$pname = $_POST["paddockName"];
		$sname = $_POST["siteName"];
		// update database
		$sql->execute();
		$conn->commit();
		// redirect to avoid resubmitting form
		header('location: home.php');
  }

//--------------------------------------------------------------------fetch data
  //fetch data from Farms table
  $records = [];
  $sql = $conn->prepare("SELECT * FROM farms");
  $sql->execute();
  $data = $sql->fetchAll();
  $recordCount = count($data);
  if ($recordCount > 0){ // if there are any records avalable
		$southWest = [90, 180];
		$northEast = [-90, -180];
		
		// Check if user want to search for specific data
		if (isset($_GET["searchText"]) && !(empty(trim($_GET["searchText"])))) {
			$search = true;  // this means user want to search for specific data
			$s_type = $_GET["searchType"];
			$s_text = $_GET["searchText"];
			$types = array("farm"=>1, "paddock"=>2, "site"=>3);
			$type = $types[$s_type];
		} else {$search = false;}
		
		if ($search) {
			// when user want to search for specific records
			for($i = 0; $i < $recordCount; $i++){
				if (stripos($data[$i][$type], $s_text) !== false){
					array_push($records, $data[$i]);
					$lat = floatval($data[$i][4]);
					$lng = floatval($data[$i][5]);
					// determine the minimum bound that contains all records
					if ($lat > $northEast[0]) {$northEast[0] = $lat;}
					if ($lng > $northEast[1]) {$northEast[1] = $lng;}
					if ($lat < $southWest[0]) {$southWest[0] = $lat;}
					if ($lng < $southWest[1]) {$southWest[1] = $lng;}
				}
			}
		} else {
			// when user want to show all records
			for($i = 0; $i < $recordCount; $i++){
				array_push($records, $data[$i]);
				$lat = floatval($data[$i][4]);
				$lng = floatval($data[$i][5]);
				// determine the minimum bound that contains all records
				if ($lat > $northEast[0]) {$northEast[0] = $lat;}
				if ($lng > $northEast[1]) {$northEast[1] = $lng;}
				if ($lat < $southWest[0]) {$southWest[0] = $lat;}
				if ($lng < $southWest[1]) {$southWest[1] = $lng;}
			}
		}
		// extend the minimum bound to avoid overzoom and add some padding
		$northEast[0] += 0.003;
		$northEast[1] += 0.003;
		$southWest[0] -= 0.003;
		$southWest[1] -= 0.003;
  }
} catch (Exception $e) {
  echo "Connection failed: " . $e->getMessage();
}
ob_end_flush();
?>
<!DOCTYPE html>
<html>
  <head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
	<!-- load Google maps api -->
	<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC9Css4Zmop3fRUL2pjBCVf9m5UYWneI3Y&signed_in=true&libraries=drawing&callback=initialize" async defer></script>
	<!-- load AngularJS -->
	<script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.6.4/angular.min.js"></script>

	<style type="text/css">
	  h1 {color: black; font-size: 200%;margin-left:20px;}
	  html, body { height: 100%; margin: 0; padding: 0}
	  #map-canvas {height: 500px; width:60%;}
	  .left-panel {margin-left: 20px; margin-right: 20px; width: 300px; 
				  height: 500px; float: left; overflow: auto; background: rgb(241,241,241);}
	  .search-panel {margin-left: 20px; margin-right: 20px; margin-bottom: 20px; 
					 width: 300px;}
	  .right-panel {float: right;}
	  .input {width: 200px;}
	  .recordinfo {margin: 5px; border-left:5px solid rgb(231,214,151); 
				   border-top:1px solid grey;border-bottom:1px solid grey; 
				   border-right:1px solid grey;padding:10px;}
	  .recordedit {margin: 5px; border-left:5px solid rgb(255,0,12); 
				   border-top:1px solid grey;border-bottom:1px solid grey; 
				   border-right:1px solid grey;padding:10px;}
	  .showE {display:block;}
	  .hideE {display:none;}
	  .button {margin-top:5px;margin-left:10px;}
	  .float {float:left;margin-right:10px;}
	</style>

	<script>
	  var map, markers = [];
	  var onFocus = "", onEdit = "";
	  var records = <?php echo json_encode($records); ?>;
		var show = "showE", hide = "hideE";

	  function initialize() {
			var myLatlng = new google.maps.LatLng(-33.637, 117.413);
			var myOptions = {
				zoom: 15,
				center: myLatlng,
				panControl: false,
				zoomControl: true,
				mapTypeControl: true,
				mapTypeControlOptions: {
						style: google.maps.MapTypeControlStyle.DROPDOWN_MENU
				},
				mapTypeId: 'satellite'
			};

			// Create new map
			map = new google.maps.Map(document.getElementById("map-canvas"), myOptions);
			google.maps.event.trigger(map, 'resize');

			// Add Drawing tools
			var drawingManager = new google.maps.drawing.DrawingManager({
				drawingMode: google.maps.drawing.OverlayType.MARKER,
				drawingControl: true,
				drawingControlOptions: {
				position: google.maps.ControlPosition.TOP_CENTER,
				drawingModes: ['marker']
				}
			});
			drawingManager.setMap(map);
			// Finish Drawing tools

			// draw available records
			drawRecords();

			// markercomplete event to collect marker's location and display form to add new site
			google.maps.event.addListener(drawingManager, 'markercomplete', function(myMarker){
				var elementExists = document.getElementById("intro");
				// if warning exists, remove it
				if (elementExists) {
				document.getElementById("intro").style.display = "none";
				}
				// display form to collect user's data
				if (document.getElementById("form").classList.contains(hide)) {
				changeFocus("form");
				} else {
				// form shown already, meaning last marker was not added, it should be removed from markers
				var marker = markers.pop();
				marker.setMap(null);
				}
				markers.push(myMarker);
				// precision of latitude and longitude is up to 4 digits after decimal point
				document.getElementById("lat").value = myMarker.getPosition().lat().toFixed(6);
				document.getElementById("lng").value = myMarker.getPosition().lng().toFixed(6);
			});
	  };

	  // function to cancel adding new site
	  function cancelAdding(){
		document.getElementById("form").classList.replace(show, hide);
		var marker = markers.pop();
		marker.setMap(null);
		if (onFocus === "form"){onFocus = "";}
	  };

	  // draw available records
	  function drawRecords(){
			if (records.length > 0) {
				// set map bound that cover all records
				var southWest = <?php echo json_encode($southWest); ?>,
				northEast = <?php echo json_encode($northEast); ?>,
				sw = new google.maps.LatLng(southWest[0], southWest[1]),
				ne = new google.maps.LatLng(northEast[0], northEast[1]),
				bounds = new google.maps.LatLngBounds(sw, ne);
				map.fitBounds(bounds);

				// draw a marker for each record
				var i;
				for (i = 0; i < records.length; i++){
				var lat = records[i][4];
				var lng = records[i][5];
				var id = records[i][0];
				var myMarker = new google.maps.Marker({
					map: map,
					position: new google.maps.LatLng(lat, lng),
					title: id
				});

				// what happen when a marker is clicked
				myMarker.addListener('click',function(e){
					var lat = e.latLng.lat().toFixed(6); 
					var lng = e.latLng.lng().toFixed(6);
					var i = 0;
					var getIt = false;
					while (!(getIt) && (i < records.length)) {
					if ((lat === records[i][4]) && (lng === records[i][5])){
						getIt = true;
					} else {
						i++;
					}
					}
					// change the focus on panel to that marker
					changeFocus(records[i][0]);
				});

				markers.push(myMarker);
				}
			}
	  }

	  // function to change apprearance when a record is selected
	  function changeFocus(formId){
			if (typeof(formId) === "number") {formId = formId.toString();}
			// if the same record is click, just exit this function
			if (formId === onFocus) {
				if (formId !== "form") {panToMarker(formId);}
				return;
			}

			if (formId === "form"){
				// when users want to add a new site
				// if any record is edited, just cancel the edit
				if (onEdit !== ""){cancelEdit();}
				// activate the form for adding new site
				document.getElementById(formId).classList.replace(hide, show);
				document.getElementById("panel").scrollTop = 0; // scroll to top of panel
				// deactivate access to any record
				if ((onFocus !== "") && (onFocus !== "form")){
					document.getElementById("info" + onFocus).classList.replace("recordedit", "recordinfo");
					document.getElementById("editbt" + onFocus).classList.replace(show, hide);
					document.getElementById("removebt" + onFocus).classList.replace(show, hide); 
				}
			} else {
				// when users want to access a record
				// if any record is edited, just cancel the edit
				if (onEdit !== ""){cancelEdit();}
				// make this record available for editing
				document.getElementById("info" + formId).classList.replace("recordinfo", "recordedit");
				document.getElementById("editbt" + formId).classList.replace(hide, show);
				document.getElementById("removebt" + formId).classList.replace(hide, show);
				document.getElementById("info" + formId).scrollIntoView(false); // scroll to this record
				panToMarker(formId);
				if (onFocus === "form") {
				cancelAdding();  // if the adding site form is activated, deactivate it
				} else {
				if (onFocus !== "") {
					// if another record is being accessed, close the access
					document.getElementById("info" + onFocus).classList.replace("recordedit", "recordinfo");
					document.getElementById("editbt" + onFocus).classList.replace(show, hide);
					document.getElementById("removebt" + onFocus).classList.replace(show, hide); 
				}
				}
			}
			onFocus = formId;  // keep track on what is activated
	  }

	  // function that pans to selected marker if not in the current map bound
	  function panToMarker(id){
			var bounds = map.getBounds();
			var i = 0;
			var getIt = false;
			while (!(getIt)){
				if (records[i][0] === id) {getIt = true;} else {i++;}
			}
			var lat = records[i][4],
				lng = records[i][5];
			var latlng = new google.maps.LatLng(lat, lng);
			if (!(bounds.contains(latlng))) {map.panTo(latlng);}
	  }

	  // function to show edit options
	  function editRecord(id){
			onEdit = id;
			document.getElementById("info" + id).classList.replace(show, hide);
			document.getElementById("edit" + id).classList.replace(hide, show);
	  }

	  // function to hide editing options
	  function cancelEdit(){
			var id = onEdit;
			document.getElementById("info" + id).classList.replace(hide, show);
			document.getElementById("edit" + id).classList.replace(show, hide);
			onEdit = "";
	  }  
	  
	  //---------------------- AngularJS ---------------------------------
	  var app = angular.module("myApp", []);
	  
	  app.controller("myCtrl", ["$scope", "$window", function($scope, $window){
			
			$scope.myRecords = $window.records;

			$scope.ichangeFocus = function(id){
				changeFocus(id);
			};

			$scope.ieditRecord = function(id){
				editRecord(id);
			};

			$scope.icancelEdit = function(){
				cancelEdit();
			};
	  }]);

	</script>
  </head>            

  <body ng-app="myApp" ng-controller="myCtrl">
	<h1>Acme Farms Soil Samples</h1>
	
	<div>
	  <!-------------- Search section --------------->
	  <div class="search-panel">
			<form action="home.php" method="GET">
				Search for: 
				<input type="radio" name="searchType" value="farm"
					<?php if (isset($_GET["searchType"]) && ($_GET["searchType"] === "farm"))
						{echo " checked";}?>>Farm
				<input type="radio" name="searchType" value="paddock"
					<?php if (isset($_GET["searchType"]) && ($_GET["searchType"] === "paddock"))
						{echo " checked";}?>>Paddock
				<input type="radio" name="searchType" value="site"
					<?php if (isset($_GET["searchType"]) && ($_GET["searchType"] === "site"))
						{echo " checked";}?>>Site<br>
				Text to search: 
				<input type="text" name="searchText" title="Leave blank to display all records"><br>
				<input type="submit" value="Search">
			</form>
	  </div>
	  
	  <!--------------- Information panel ----------------->
	  <div id="panel" class="left-panel">
		<!-- Warning when there's no record -->
		<?php
		if ($recordCount == 0) {
		  $text = <<<ETO
		<p id="intro" style="font-size: 11; font-style: bold;">
		No Sites Added, use the drawing tools on the map to add points to get started</p>            
ETO;
		  echo $text;
		}
		?>

		<!-- this is the form to add new site -->
		<form id="form" action="home.php" method="post" class="recordedit hideE">
		  <label>Farm Name</label><br>
		  <input name="farmName" id="farmName" class="input" type="text" size="30" 
				 placeholder="Farm" maxlength="200" pattern="[A-Za-z_][A-Za-z0-9'-_\s]*" 
				 title ="Accept letters, numbers, ', -, _ and space only. Must start with letter or _." 
				 required/><br>
		  <label>Paddock Name</label><br>
		  <input name="paddockName" id="paddockName" class="input" type="text" size="30" 
				 placeholder="Paddock" maxlength="200" pattern="[A-Za-z_][A-Za-z0-9'-_\s]*" 
				 title ="Accept letters, numbers, ', -, _ and space only. Must start with letter or _." 
				 required/><br>
		  <label>Site Name</label><br>
		  <input name="siteName" id="siteName" class="input" type="text" size="30" 
				 placeholder="Site" maxlength="200" pattern="[A-Za-z_][A-Za-z0-9'-_\s]*" 
				 title ="Accept letters, numbers, ', -, _ and space only. Must start with letter or _." 
				 required/><br>
		  <input name="lat" id="lat" type="text" class="hideE"/>
		  <input name="lng" id="lng" type="text" class="hideE"/>
		  <input type="submit" value="Add" class="button"/>
		  <input type="button" onclick="cancelAdding()" value="Cancel" class="button"/>
		</form>

		<!-- start adding available records to panel -->
		<div ng-repeat="x in myRecords">
		  <div ng-click="ichangeFocus(x[0])">
			<form id="info{{x[0]}}" action="home.php" method="post" class="recordinfo showE">
				<label>Farm: <span ng-bind="x[1]"></span></label><br>
			  <label>Paddock: <span ng-bind="x[2]"></span></label><br>
			  <label>Site: <span ng-bind="x[3]"></span></label><br>
			  <input id="editbt{{x[0]}}" type="button" ng-click="ieditRecord(x[0])" 
					 value="Edit" class="button float hideE"/>
			  <input id="removebt{{x[0]}}" type="submit" value="Remove" class="button hideE"/>
			  <input type="text" name="remove_id" value="{{x[0]}}" class="hideE"/>
			</form>
			<form id="edit{{x[0]}}" action="home.php" method="post" class="recordedit hideE">
			  <label>Farm Name</label><br>
			  <input name="farmName" id="farmName{{x[0]}}" class="input" type="text" 
					 size="30" value="{{x[1]}}" maxlength="200" 
					 pattern="[A-Za-z_][A-Za-z0-9'-_\s]*" required 
					 title ="Accept letters, numbers, ', -, _ and space only. Must start with letter or _."/><br>
			  <label>Paddock Name</label><br>
			  <input name="paddockName" id="paddockName{{x[0]}}" class="input" type="text" 
					 size="30" value="{{x[2]}}" maxlength="200" 
					 pattern="[A-Za-z_][A-Za-z0-9'-_\s]*" required 
					 title ="Accept letters, numbers, ', -, _ and space only. Must start with letter or _."/><br>
			  <label>Site Name</label><br>
			  <input name="siteName" id="siteName{{x[0]}}" class="input" type="text" 
					 size="30" value="{{x[3]}}" maxlength="200" 
					 pattern="[A-Za-z_][A-Za-z0-9'-_\s]*" required 
					 title ="Accept letters, numbers, ', -, _ and space only. Must start with letter or _."/><br>
			  <input type="submit" value="Save Changes" class="button"/>
			  <input type="button" ng-click="icancelEdit()" value="Cancel" class="button"/>
			  <input type="text" name="change_id" value="{{x[0]}}" class="hideE"/>
			</form>
		  </div>
		</div> <!-- finish adding available records to panel -->
	  </div>
	</div>
	<!--------------- Map panel ---------------->
	<div id="map-canvas">
	</div>
  </body>
</html>