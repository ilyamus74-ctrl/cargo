



function searchMoreNewCars(){
//console.log('search');
var arrdata={
	    "price":document.getElementById("price").value,
	    "marke":document.getElementById("marke").value,
	    "secCode":document.getElementById("secCode").value,
	    "stan":document.getElementById("stan").value,
	    "lastIdCar":document.getElementById("lastIdCar").value,
	    "todo":"searchMoreAllCars",
	    "type":document.getElementById("type").value
	    };

 $.ajax({
        type: "POST",
        url: "https://zsuauto.info/requestSearchNew.php",
        data: arrdata,
        cache: false,
        dataType: 'text',
        success: function(data){
    	    if(data!=""){
    			console.log('successful');
    			console.log(data);
    			document.getElementById("showMoreButton").remove();
    			document.getElementById("nextCars").remove();
    			var timestamp = new Date().getUTCMilliseconds();
    			document.getElementById("showNextCar").setAttribute("id",timestamp);
    			document.getElementById(""+timestamp+"").innerHTML=data;
    //		if(data.length > 6){
    //		document.getElementById("reaSuccessful").style="display:block";
    //		document.getElementById("secCode").value=data;
    //		document.getElementById("reqText").style="display:none";
    //		}
    			}
    	    },
        error: function(errMsg) {
            console.log('error');
        }
    });

}




function searchNewCars(){
//console.log('search');
var arrdata={
	    "price":document.getElementById("price").value,
	    "marke":document.getElementById("marke").value,
	    "secCode":document.getElementById("secCode").value,
	    "stan":document.getElementById("stan").value,
	    "type":document.getElementById("type").value
	    };

 $.ajax({
        type: "POST",
        url: "https://zsuauto.info/requestSearchNew.php",
        data: arrdata,
        cache: false,
        dataType: 'text',
        success: function(data){
    	    if(data!=""){
    			console.log('successful');
    			console.log(data);
    			document.getElementById("listAllCars").innerHTML=data;
    //		if(data.length > 6){
    //		document.getElementById("reaSuccessful").style="display:block";
    //		document.getElementById("secCode").value=data;
    //		document.getElementById("reqText").style="display:none";
    //		}
    			}
    	    },
        error: function(errMsg) {
            console.log('error');
        }
    });

}





function searchMoreAllCars(){
//console.log('search');
var arrdata={
	    "price":document.getElementById("price").value,
	    "marke":document.getElementById("marke").value,
	    "secCode":document.getElementById("secCode").value,
	    "stan":document.getElementById("stan").value,
	    "lastIdCar":document.getElementById("lastIdCar").value,
	    "todo":"searchMoreAllCars",
	    "type":document.getElementById("type").value
	    };

 $.ajax({
        type: "POST",
        url: "https://zsuauto.info/requestSearch.php",
        data: arrdata,
        cache: false,
        dataType: 'text',
        success: function(data){
    	    if(data!=""){
    			console.log('successful');
    			console.log(data);
    			document.getElementById("showMoreButton").remove();
    			document.getElementById("nextCars").remove();
    			var timestamp = new Date().getUTCMilliseconds();
    			document.getElementById("showNextCar").setAttribute("id",timestamp);
    			document.getElementById(""+timestamp+"").innerHTML=data;
    //		if(data.length > 6){
    //		document.getElementById("reaSuccessful").style="display:block";
    //		document.getElementById("secCode").value=data;
    //		document.getElementById("reqText").style="display:none";
    //		}
    			}
    	    },
        error: function(errMsg) {
            console.log('error');
        }
    });

}




function searchAllCars(){
//console.log('search');
var arrdata={
	    "price":document.getElementById("price").value,
	    "marke":document.getElementById("marke").value,
	    "secCode":document.getElementById("secCode").value,
	    "stan":document.getElementById("stan").value,
	    "type":document.getElementById("type").value
	    };

 $.ajax({
        type: "POST",
        url: "https://zsuauto.info/requestSearch.php",
        data: arrdata,
        cache: false,
        dataType: 'text',
        success: function(data){
    	    if(data!=""){
    			console.log('successful');
    			console.log(data);
    			document.getElementById("listAllCars").innerHTML=data;
    //		if(data.length > 6){
    //		document.getElementById("reaSuccessful").style="display:block";
    //		document.getElementById("secCode").value=data;
    //		document.getElementById("reqText").style="display:none";
    //		}
    			}
    	    },
        error: function(errMsg) {
            console.log('error');
        }
    });

}


function sendReqestUser(){

var arrdata={
	    "name":document.getElementById("validationDefault01").value,
	    "phone":document.getElementById("validationDefault02").value,
	    "secCode":document.getElementById("secCode").value,
	    "car":document.getElementById("car").value
	    };
	    console.log(arrdata);
//console.log(secCode);
    document.getElementById("sendRequestButton").setAttribute("disabled","true");
    document.getElementById("validationDefault02").value = "";
    document.getElementById("validationDefault01").value = "";
 $.ajax({
        type: "POST",
        url: "https://zsuauto.info/request.php",
//        method: "POST",        
//        data: { "name":arrdata, "phone":phone,"secCode":secCode,"car":car },
        data: arrdata,
//        contentType: false,
//        headers: { "enctype":"multipart/form-data" },
//        contentType: "text",
        cache: false,
//        processData: false,
        dataType: 'text',
        success: function(data){
    	    if(data!=""){
    			//alert(JSON.stringify(data));
    			console.log('successful');
    //			console.log(data);
    		if(data.length > 6){
    		//console.log('eeeeeeeee');
    		document.getElementById("reaSuccessful").style="display:block";
    		document.getElementById("secCode").value=data;
    		document.getElementById("reqText").style="display:none";
    		//setTimeout(closeModal,1544);
    		//startTimer();
    		//closeModal();
    		}
    			}
    	    },
        error: function(errMsg) {
            console.log('error');
            //alert(JSON.stringify(errMsg));
        }
    });
}

function startTimer () {
    timer.start();
    setTimeout(stopTimer,10);
}

function stopTimer () {
    timer.stop();
}


function checkThisForm(){
    if(document.getElementById("validationDefault02").value.length > 6 && document.getElementById("validationDefault01").value.length > 3){
	document.getElementById("sendRequestButton").removeAttribute("disabled");
	document.getElementById("sendRequestButton").value="Спочатку заповни поля";
	}
    else{
    document.getElementById("sendRequestButton").setAttribute("disabled","true");
    }
}


function myModalOpen(){

//console.log('www');
 document.getElementById("myModal").style="display:block"; 
}

function closeModal(){
document.getElementById("myModal").style="display:none";
document.getElementById("reaSuccessful").style="display:none";
document.getElementById("reqText").style="display:block";

}
// Get the <span> element that closes the modal
//var span = document.getElementsById("myModal")[0];


// When the user clicks on <span> (x), close the modal
//span.onclick = function() {
//  document.getElementById("myModal").style="display:none";
//}

window.onclick = function(event) {
  if (event.target == document.getElementById("myModal")) {
  document.getElementById("myModal").style="display:none";
//    modal.style.display = "none";
document.getElementById("reaSuccessful").style="display:none";
document.getElementById("reqText").style="display:block";

  }
}
