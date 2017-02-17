<?php 
namespace Nizarp\PdfVision;

use Google\Cloud\Vision\VisionClient;
use Dotenv\Dotenv;
use File;
use Exception;

class Pdf
{
	
	protected $pdfFile;
    protected $dotEnv;
    protected $basePath = 'images';
    protected $outputFormat = 'png';
    
	/**
     * @param string $pdfFile The path to the pdffile.
     *
     * @throws Exceptions\PdfDoesNotExist
     */
    public function __construct($pdfFile)
    {

        if (!file_exists($pdfFile)) {
            throw new Exception('PDF file does not exist!', 1);
        }

        $this->pdfFile = $pdfFile;
    }

    /**
     * Set base path to .env file
     *
     * @param string $envPath
     */
    public function setEnvPath($envFile)
    {
        // Verify Google Vision API configuration
        $this->dotEnv = new Dotenv(rtrim($envFile, '.env'));
        $this->dotEnv->load();

        if( !getenv('GOOGLE_VISION_PROJECTID') 
            || !getenv('GOOGLE_APPLICATION_CREDENTIALS') ) {
            throw new Exception("Google vision API key missing in .env file!");
        }

    }

    /**
     * Convert the PDF file to Text
     *
     * @return string
     */
    public function convertToText()
    {
        // Set an unlimitted time limit because this probably going to take some time if the PDF file has too many pages.
        set_time_limit(0);

        $textData = '';
        $pdfToImage = new \Spatie\PdfToImage\Pdf($this->pdfFile);
        $pdfToImage->setOutputFormat($this->outputFormat);

        // Generate a random folder for each request
        $path = $this->basePath . '/' . time() . '-' . substr(md5(rand()), 0, 7);
        if(! mkdir($path)) {
            throw new Exception('Unable to create directory for processing PDF file!', 1);
        }
        $images = $pdfToImage->saveAllPagesAsImages($path);

        if(!empty($images)) {
            foreach ($images as $image) {
                
                // Convert to text
                $textData.= " ". $this->imageToText($image);

                // Delete image after processing
                if(!unlink($image)) {
                    throw new Exception("Unable to delete image after processing!", 1);
                }
            }
        } else {
            throw new Exception("Error converting PDF file to images!", 1);            
        }

        // Delete directory
        if(!rmdir($path)) {
            throw new Exception("Unable to delete temp directory!", 1);
        }

        if(trim($textData) == "") {
            throw new Exception('PDF file is empty!', 1);
        }

        return $textData;
    }

    /**
     * Convert the image to text using Vision API
     *
     * @return string
     */
    public function imageToText($image) {

        $response = '';
        
        // Instantiates a client
        $vision = new VisionClient([
            'projectId' => getenv('GOOGLE_VISION_PROJECTID')
        ]);

        // Prepare the image to be annotated
        $image = $vision->image(file_get_contents($image), ['TEXT_DETECTION']);
        
        // Performs OCR on the image file
        $result = $vision->annotate($image);
        
        // Collects and appends text data
        foreach ((array) $result->text() as $text) {
            $response.= ($text->description());
        }

        return $response;
    }


}