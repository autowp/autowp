<?php

namespace Autowp\ExternalLoginService;

use Autowp\ExternalLoginService\Exception;

use DateTime;

use Zend_Date;
use Zend_Uri;

class Result
{
    /**
     * @var string
     */
    protected $_externalId = null;

    /**
     * @var string
     */
    protected $_name = null;

    /**
     * @var string
     */
    protected $_profileUrl = null;

    /**
     * @var string
     */
    protected $_photoUrl = null;

    /**
     * @var Zend_Date
     */
    protected $_birthday = null;

    /**
     * @var string
     */
    protected $_email = null;

    /**
     * @var string
     */
    protected $_residence = null;


    /**
     * @var int
     */
    protected $_gender = null;

    /**
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options = array())
    {
        $this->setOptions($options);
    }

    /**
     * @param array $options
     * @return Result
     * @throws Exception
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);

            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                $message = "Unexpected option '$key'";
                throw new Exception($message);
            }
        }

        return $this;
    }

    /**
     * @param string $externalId
     * @return Result
     */
    public function setExternalId($externalId)
    {
        $this->_externalId = (string)$externalId;

        return $this;
    }

    /**
     * @return string
     */
    public function getExternalId()
    {
        return $this->_externalId;
    }

    /**
     * @param string $name
     * @return Result
     */
    public function setName($name)
    {
        $this->_name = (string)$name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * @param string $profileUrl
     * @return Result
     */
    public function setProfileUrl($profileUrl)
    {
        $profileUrl = (string)$profileUrl;

        if ($profileUrl) {
            $this->_profileUrl = $profileUrl;
            /*if (Zend_Uri::check($profileUrl)) {
                $this->_profileUrl = $profileUrl;
            } else {
                $message = "Invalid profile url `$profileUrl`";
                throw new Exception($message);
            }*/
        } else {
            $this->_profileUrl = null;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getProfileUrl()
    {
        return $this->_profileUrl;
    }

    /**
     * @param string $photoUrl
     * @return Result
     */
    public function setPhotoUrl($photoUrl)
    {
        $photoUrl = (string)$photoUrl;

        if ($photoUrl) {
            if (Zend_Uri::check($photoUrl)) {
                $this->_photoUrl = $photoUrl;
            } else {
                $message = "Invalid profile url `$photoUrl`";
                throw new Exception($message);
            }
        } else {
            $this->_photoUrl = null;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getPhotoUrl()
    {
        return $this->_photoUrl;
    }

    /**
     * @param string $email
     * @return Result
     */
    public function setEmail($email)
    {
        $this->_email = (string)$email;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->_email;
    }

    /**
     * @param DateTime $birthday
     * @return Result
     */
    public function setBirthday(DateTime $birthday = null)
    {
        $this->_birthday = $birthday;

        return $this;
    }

    /**
     * @return Zend_Date
     */
    public function getBirthday()
    {
        return $this->_birthday;
    }

    /**
     * @param string $residence
     * @return Result
     */
    public function setResidence($residence)
    {
        $this->_residence = (string)$residence;

        return $this;
    }

    /**
     * @return string
     */
    public function getResidence()
    {
        return $this->_residence;
    }

    /**
     * @param int $gender
     * @return Result
     */
    public function setGender($gender)
    {
        $this->_gender = $gender;

        return $this;
    }

    /**
     * @return int
     */
    public function getGender()
    {
        return $this->_gender;
    }

    public function toArray()
    {
        return array(
            'externalId' => $this->_externalId,
            'name'       => $this->_name,
            'profileUrl' => $this->_profileUrl,
            'photoUrl'   => $this->_photoUrl,
            'email'      => $this->_email,
            'birthday'   => $this->_birthday,
            'residence'  => $this->_residence,
            'gender'     => $this->_gender
        );
    }

    public static function fromArray(array $options)
    {
        return new self($options);
    }
}