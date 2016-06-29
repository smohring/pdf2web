<?php
/**
 * @author    Steffen Mohring
 */

	class pdf2web {

		private $imagick = '/usr/bin/convert';
		private $out = 'output';
		private $zip = 'zip';
		private $density = '-density 200';
		private $quality = '-quality 80';
		private $arrQuery = null;

		function __construct(){

			$this->parseQueryString();
			$this->routeParameters();
		}

		private function parseQueryString(){

			$url = $_SERVER['QUERY_STRING'];

			if($url){
				$this->arrQuery = explode('/', $url);
			}
		}

		private function routeParameters(){

			//Route First Parameter
			switch($this->arrQuery[0]){
				case 'action':

					//Route Second Parameter
					switch($this->arrQuery[1]){

						case 'upload' :
							//Requires filename
							$this->pdf2jpg($_FILES['file']['tmp_name'], $_FILES['file']['name'], $_FILES['file']['type']);
							break;

						case 'createzip' :
							//Requires Dir Hash
							$this->createzip($this->arrQuery[2]);
							break;

						case 'listDirs':
							$this->resp($this->getpdfs());
							break;

						case 'view':
							$this->view($this->arrQuery[2]);
							break;

						case 'delete':
							$this->delete($this->arrQuery[2]);
							break;
					}

					break;

				default:
						$this->getClient();
					break;
			}
		}

		//Simply return Client App if Parameters are Missing
		private function getClient(){
			echo file_get_contents('app.html');
		}

		//Convert file To JPG
		private function pdf2jpg($tmp, $name, $type){

			if($type != 'application/pdf'){
				$this->resp(array('Error','Wrong mime Type!'));
				return false;
			}

			//Check if output directory already exists
			if(! is_dir($this->out)) mkdir($this->out, 0777);

			//Hash File and use as FolderName
			$outDir = hash_file('md5', $tmp);

			//Check if already exists and skip creation if exists
			if(! is_dir($this->out.'/'.$outDir)){
				mkdir($this->out.'/'.$outDir, 0777);
			}else{
				$this->resp(array('Error','File already exists'));
			}

			//Use ImageMagick to create Preview
			$tmpname = $tmp.'[0]';
			exec("$this->imagick -density 50 $this->quality $tmpname $this->out/$outDir/preview.jpg");

			//Use ImageMagick to create output files
			$return = shell_exec("$this->imagick $this->density $this->quality -limit memory 512 -scene 1 $tmp $this->out/$outDir/page.jpg 2>&1");

      if(file_exists("$this->out/$outDir/page.jpg")) {
        rename("$this->out/$outDir/page.jpg", "$this->out/$outDir/page-1.jpg");
      }

			if($return)
				return true;
			else
				return false;
		}

		//Creates a Zip file for a given Hashed Directory
		private function createzip($dirHash){

			if(! is_dir($this->out.'/'.$dirHash)){
				return false;
			}

			$arrFiles = $this->fillArrayWithFileNodes( new DirectoryIterator($this->out.'/'.$dirHash));
			$zipArc = new ZipArchive();

			if(! is_dir($this->zip)) mkdir($this->zip, 0777);

			$zipArc->open($this->zip.'/'.$dirHash.'.zip', ZIPARCHIVE::OVERWRITE);

			//ToDo: Bullshit - replace with recursive Function
			foreach($arrFiles as $file){
				$zipArc->addFile($this->out.'/'.$dirHash.'/'.$file, 'pages/'.$file);
			}

			$arrFiles = $this->fillArrayWithFileNodes( new DirectoryIterator('src'));

			//ToDo: Bullshit - replace with recursive Function
			foreach($arrFiles as $key => $file){

				if($file == 'index.html'){

					$page = file_get_contents('src/index.html');

					$search = array(
							'/###pages###/',
							'/###hash###/',
							'/###base###/',
							'/###width###/',
							'/###height###/'
					);

					$imgSize = getimagesize($this->out.'/'.$dirHash.'/page-1.jpg');

					$replace = array(
							$this->countFilesinDir($this->out.'/'.$dirHash),
							'pages/',
						  '',
							$imgSize[0],
							$imgSize[1]
				);

					$page = preg_replace($search, $replace, $page);
					$zipArc->addFromString('index.html', $page);

					continue;
				}

				if($file == 'preview.jpg'){
					continue;
				}

				if(! is_array($file)){

						$zipArc->addFile('src/'.$file, $file);

				}else{

					foreach($file as $file2) {
						$zipArc->addFile('src/'.$key.'/'.$file2, $key.'/'.$file2 );
					}
				}
			}

			$zipArc->close();

			header('Content-Type: application/zip');
			header('Content-Length: ' . filesize($this->zip.'/'.$dirHash.'.zip'));
			header('Content-Disposition: attachment; filename="'.$dirHash.'.zip"');
			readfile($this->zip.'/'.$dirHash.'.zip');
			unlink($this->zip.'/'.$dirHash.'.zip');

			return true;
		}


		//Create Array With File Nodes (Recursive Call)
		private function getpdfs(){

			return is_dir($this->out) ? $this->fillArrayWithFileNodes( new DirectoryIterator($this->out)) : [];
		}


		//Create Array with File / Directory structure
		private function fillArrayWithFileNodes( DirectoryIterator $dir )
		{
			$data = array();
			foreach ( $dir as $node )
			{
				if ( $node->isDir() && !$node->isDot() )
				{
					$data[$node->getFilename()] = $this->fillArrayWithFileNodes( new DirectoryIterator( $node->getPathname() ) );
				}
				else if ( $node->isFile() )
				{
					$data[] = $node->getFilename();
				}
			}
			return $data;
		}

		//Count files in Directory
		private function countFilesinDir($dir){

			$dir = new DirectoryIterator($dir);
			$x = 0;

			foreach($dir as $file ){
				if($file->isFile() && $file->getFilename() != 'preview.jpg') $x++;
			}

			return $x;
		}

		//Return View of PDF
		private function view($dirHash){

			if(! is_dir($this->out.'/'.$dirHash)){
				return false;
			}

			header('Content-Type: text/html');
			$page = file_get_contents('src/index.html');

			$search = array(
					'/###pages###/',
					'/###hash###/',
					'/###base###/',
					'/###width###/',
					'/###height###/'
			);

			$imgSize = getimagesize($this->out.'/'.$dirHash.'/page-1.jpg');

			$replace = array(
					$this->countFilesinDir($this->out.'/'.$dirHash),
					'/output/'.$dirHash,
					'/src/',
					$imgSize[0],
					$imgSize[1]
			);

			$page = preg_replace($search, $replace, $page);
			echo $page;
		}

		//Delete Directory with PDF
		private function delete($dirHash){

			if(! is_dir($this->out.'/'.$dirHash)){
				return false;
			}

			if (substr($this->out.'/'.$dirHash, strlen($this->out.'/'.$dirHash) - 1, 1) != '/') {
				$this->out.'/'.$dirHash .= '/';
			}
			$files = glob($this->out.'/'.$dirHash . '*', GLOB_MARK);
			foreach ($files as $file) {
				if (! is_dir($file)) {
					unlink($file);
				}
			}
			rmdir($this->out.'/'.$dirHash);
			return true;
		}

		//Return JSON Response
		private function resp($data){

			header('Content-Type: application/json');
			echo json_encode($data);
		}
	}

	new pdf2web();