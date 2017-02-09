<?php 
namespace Nizarp\PdfVision;

use Google\Cloud\Vision\VisionClient;
use Dotenv\Dotenv;
use File;
use Exception;

class Pdf
{
	
	protected $pdfFile;
    protected $envFile;
    protected $basePath = '/Users/qbuser/Sites/images';
    protected $outputFormat = 'jpg';
    
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
        if (!file_exists($envFile)) {
            throw new Exception('.env file does not exist!', 1);
        }

        $this->envFile = $envFile;
    }

    /**
     * Convert the PDF file to Text
     *
     * @return string
     */
    public function convertToText()
    {
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
                unlink($image);
            }
        }

        // Delete directory
        rmdir($path);

        return $textData;
    }

    /**
     * Conver the image to text using Vision API
     *
     * @return string
     */
    public function imageToText($image) {

        $response = '';        
        $dotenv = new Dotenv(dirname($this->envFile));
        $dotenv->load();
        
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