<?php
namespace DuncrowGmbh\CaptchaEu\Form;

use Contao\Input;
use Contao\PageModel;
use Contao\FormCaptcha;

class FormCaptchaEu extends FormCaptcha
{
    protected $strTemplate = 'form_recaptcha';
    protected $recaptchaType = 'invisible';
    protected $publicKey = null;
    protected $privateKey = null;

    public function __construct($arrAttributes = null) 
    {
        parent::__construct($arrAttributes);
        if(isset($GLOBALS['objPage'])){
            $rootId = $GLOBALS['objPage']->rootId; 
            $rootPage = PageModel::findByPk($rootId);
            $this->recaptchaType = $this->useCaptchaEuWidget ? 'widget' : 'invisible' ; 
            $this->publicKey =  $rootPage->captchaEuPublicKey; 
            $this->privateKey = $rootPage->captchaEuPrivateKey;
        }
        if($this->useCaptchaEu){

        }else if ($this->useFallback()) {
            $this->strTemplate = 'form_captcha';
        }

    }

    protected function useFallback()
    {
        return !$this->publicKey || !$this->privateKey || !$this->useCaptchaEu;
    }

    public function validate()
    {
        if ($this->useFallback()) return parent::validate();
        
        try {
            function checkSolution($solution, $privateKey) {
                $ch = curl_init("https://www.captcha.eu/validate");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $solution);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Rest-Key: '.$privateKey));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                curl_close($ch);
          
                $resultObject = json_decode($result);
                if ($resultObject->success) {
                  return true;
                } else {
                  return false;
                }
              }
              $solution = html_entity_decode((Input::post('captcha_at_solution') ?? Input::post('captcha_at_hidden_field')) ) ; // $_POST["captcha_at_solution"];
              $valid = checkSolution($solution, $this->privateKey);

              if (!$valid ) {
                    $this->class = 'error';
                    $this->addError($GLOBALS['TL_LANG']['ERR']['catpchaEu']);
                }else{
                    // return true; 
                }

        } catch (\Exception $e) {
            $this->class = 'error';
            $this->addError($GLOBALS['TL_LANG']['ERR']['catpchaEu']);
        }
    }
    

}