

function deleteNewAnnounce(){
//console.log('delete');
var id=document.getElementById("idAnnounce").value;
//idInList_
var arrdata={
	    "toDo":"delete",
	    "id":id
	    }
    var answer = requestToDo(arrdata);
    if(answer != "error"){
	document.getElementById("idInListStats_"+id+"").innerHTML="<span class=\"badge bg-danger\">Удаление</span>";    
    }
//    console.log(answer);
}

function saveNewAnnounce(){
var id=document.getElementById("idAnnounce").value;

//console.log('saven New Announce');
document.getElementById("idInListStats_"+id+"").innerHTML="<span class=\"badge bg-success\">Публікація</span>";    
    if(document.getElementById("NewName").value != document.getElementById("pname").value){
    var newName = document.getElementById("NewName").value;
    }
    
var arrdata={
	    "toDo":"published",
	    "newName":newName,
	    "id":id
	    }
    var answer = requestToDo(arrdata);
    //console.log(answer);

}

function makeNewName(description,value){
document.getElementById("NewName").value=document.getElementById("NewName").value+" "+description+value;

}



function requestToDo(arrdata){
//var arrdata={
//	    "toDo":data,
	    ////"i":document.getElementById("marke").value,
	    ////"secCode":document.getElementById("secCode").value,
	    ////"stan":document.getElementById("stan").value,
	    ////"lastIdCar":document.getElementById("lastIdCar").value,
	    ////"todo":"searchMoreAllCars",
//	    "showCar":id
//	    };

//console.log(arrdata);
 $.ajax({
        type: "POST",
        url: "https://zsuauto.info/abra/simpleRequest.php",
        data: arrdata,
        cache: false,
        dataType: 'text',
        success: function(data){
    	    if(data!=""){
    			console.log('successful');
    //			console.log(data);
			//document.getElementById("modalInner").innerHTML=data;

    /*			document.getElementById("showMoreButton").remove();
    			document.getElementById("nextCars").remove();
    			var timestamp = new Date().getUTCMilliseconds();
    			document.getElementById("showNextCar").setAttribute("id",timestamp);
    			document.getElementById(""+timestamp+"").innerHTML=data;
    */
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




function showItem(showCar,id){
document.getElementById("modalInner").innerHTML="";
document.getElementById("modalInner").innerHTML="<div class=\"spinner-border\" style=\"width: 50px; height: 50px;\" role=\"status\"><span class=\"visually-hidden\">Loading...</span></div>";

var arrdata={
	    ////"show":document.getElementById("price").value,
	    ////"i":document.getElementById("marke").value,
	    ////"secCode":document.getElementById("secCode").value,
	    ////"stan":document.getElementById("stan").value,
	    ////"lastIdCar":document.getElementById("lastIdCar").value,
	    ////"todo":"searchMoreAllCars",
	    "showCar":id
	    };

//console.log(arrdata);
 $.ajax({
        type: "POST",
        url: "https://zsuauto.info/abra/requestShow.php",
        data: arrdata,
        cache: false,
        dataType: 'text',
        success: function(data){
    	    if(data!=""){
    			console.log('successful');
    //			console.log(data);
			document.getElementById("modalInner").innerHTML=data;

    /*			document.getElementById("showMoreButton").remove();
    			document.getElementById("nextCars").remove();
    			var timestamp = new Date().getUTCMilliseconds();
    			document.getElementById("showNextCar").setAttribute("id",timestamp);
    			document.getElementById(""+timestamp+"").innerHTML=data;
    */
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
function showItemUk(showCar,id){
document.getElementById("modalInner").innerHTML="";
document.getElementById("modalInner").innerHTML="<div class=\"spinner-border\" style=\"width: 50px; height: 50px;\" role=\"status\"><span class=\"visually-hidden\">Loading...</span></div>";
var arrdata={
	    ////"show":document.getElementById("price").value,
	    ////"i":document.getElementById("marke").value,
	    ////"secCode":document.getElementById("secCode").value,
	    ////"stan":document.getElementById("stan").value,
	    ////"lastIdCar":document.getElementById("lastIdCar").value,
	    ////"todo":"searchMoreAllCars",
	    "showCar":id
	    };

//console.log(arrdata);
 $.ajax({
        type: "POST",
        url: "https://zsuauto.info/abra/requestShowUk.php",
        data: arrdata,
        cache: false,
        dataType: 'text',
        success: function(data){
    	    if(data!=""){
    			console.log('successful');
    //			console.log(data);
			document.getElementById("modalInner").innerHTML=data;

    /*			document.getElementById("showMoreButton").remove();
    			document.getElementById("nextCars").remove();
    			var timestamp = new Date().getUTCMilliseconds();
    			document.getElementById("showNextCar").setAttribute("id",timestamp);
    			document.getElementById(""+timestamp+"").innerHTML=data;
    */
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