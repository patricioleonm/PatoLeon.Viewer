	var state = 2;
	function swapPanel(){	
		if(state == 1){
			document.getElementById('metadata').className = 'left cincuenta';
			document.getElementById('viewer').className = 'right';
			state = 2;
		}else{
			document.getElementById('metadata').className = 'right cincuenta';
			document.getElementById('viewer').className = 'left';
			state = 1;
		}
	}	
