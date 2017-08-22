<?php
namespace Shared\Libraries\Jaeger\Thrift\Zipkin;

/**
 * Autogenerated by Thrift Compiler (0.10.0)
 *
 * DO NOT EDIT UNLESS YOU ARE SURE THAT YOU KNOW WHAT YOU ARE DOING
 *  @generated
 */
use Thrift\Base\TBase;
use Thrift\Type\TType;
use Thrift\Type\TMessageType;
use Thrift\Exception\TException;
use Thrift\Exception\TProtocolException;
use Thrift\Protocol\TProtocol;
use Thrift\Protocol\TBinaryProtocolAccelerated;
use Thrift\Exception\TApplicationException;


/**
 * Indicates the network context of a service recording an annotation with two
 * exceptions.
 * 
 * When a BinaryAnnotation, and key is CLIENT_ADDR or SERVER_ADDR,
 * the endpoint indicates the source or destination of an RPC. This exception
 * allows zipkin to display network context of uninstrumented services, or
 * clients such as web browsers.
 */
class Endpoint {
  static $_TSPEC;

  /**
   * IPv4 host address packed into 4 bytes.
   * 
   * Ex for the ip 1.2.3.4, it would be (1 << 24) | (2 << 16) | (3 << 8) | 4
   * 
   * @var int
   */
  public $ipv4 = null;
  /**
   * IPv4 port
   * 
   * Note: this is to be treated as an unsigned integer, so watch for negatives.
   * 
   * Conventionally, when the port isn't known, port = 0.
   * 
   * @var int
   */
  public $port = null;
  /**
   * Service name in lowercase, such as "memcache" or "zipkin-web"
   * 
   * Conventionally, when the service name isn't known, service_name = "unknown".
   * 
   * @var string
   */
  public $service_name = null;
  /**
   * IPv6 host address packed into 16 bytes. Ex Inet6Address.getBytes()
   * 
   * @var string
   */
  public $ipv6 = null;

  public function __construct($vals=null) {
    if (!isset(self::$_TSPEC)) {
      self::$_TSPEC = array(
        1 => array(
          'var' => 'ipv4',
          'type' => TType::I32,
          ),
        2 => array(
          'var' => 'port',
          'type' => TType::I16,
          ),
        3 => array(
          'var' => 'service_name',
          'type' => TType::STRING,
          ),
        4 => array(
          'var' => 'ipv6',
          'type' => TType::STRING,
          ),
        );
    }
    if (is_array($vals)) {
      if (isset($vals['ipv4'])) {
        $this->ipv4 = $vals['ipv4'];
      }
      if (isset($vals['port'])) {
        $this->port = $vals['port'];
      }
      if (isset($vals['service_name'])) {
        $this->service_name = $vals['service_name'];
      }
      if (isset($vals['ipv6'])) {
        $this->ipv6 = $vals['ipv6'];
      }
    }
  }

  public function getName() {
    return 'Endpoint';
  }

  public function read($input)
  {
    $xfer = 0;
    $fname = null;
    $ftype = 0;
    $fid = 0;
    $xfer += $input->readStructBegin($fname);
    while (true)
    {
      $xfer += $input->readFieldBegin($fname, $ftype, $fid);
      if ($ftype == TType::STOP) {
        break;
      }
      switch ($fid)
      {
        case 1:
          if ($ftype == TType::I32) {
            $xfer += $input->readI32($this->ipv4);
          } else {
            $xfer += $input->skip($ftype);
          }
          break;
        case 2:
          if ($ftype == TType::I16) {
            $xfer += $input->readI16($this->port);
          } else {
            $xfer += $input->skip($ftype);
          }
          break;
        case 3:
          if ($ftype == TType::STRING) {
            $xfer += $input->readString($this->service_name);
          } else {
            $xfer += $input->skip($ftype);
          }
          break;
        case 4:
          if ($ftype == TType::STRING) {
            $xfer += $input->readString($this->ipv6);
          } else {
            $xfer += $input->skip($ftype);
          }
          break;
        default:
          $xfer += $input->skip($ftype);
          break;
      }
      $xfer += $input->readFieldEnd();
    }
    $xfer += $input->readStructEnd();
    return $xfer;
  }

  public function write($output) {
    $xfer = 0;
    $xfer += $output->writeStructBegin('Endpoint');
    if ($this->ipv4 !== null) {
      $xfer += $output->writeFieldBegin('ipv4', TType::I32, 1);
      $xfer += $output->writeI32($this->ipv4);
      $xfer += $output->writeFieldEnd();
    }
    if ($this->port !== null) {
      $xfer += $output->writeFieldBegin('port', TType::I16, 2);
      $xfer += $output->writeI16($this->port);
      $xfer += $output->writeFieldEnd();
    }
    if ($this->service_name !== null) {
      $xfer += $output->writeFieldBegin('service_name', TType::STRING, 3);
      $xfer += $output->writeString($this->service_name);
      $xfer += $output->writeFieldEnd();
    }
    if ($this->ipv6 !== null) {
      $xfer += $output->writeFieldBegin('ipv6', TType::STRING, 4);
      $xfer += $output->writeString($this->ipv6);
      $xfer += $output->writeFieldEnd();
    }
    $xfer += $output->writeFieldStop();
    $xfer += $output->writeStructEnd();
    return $xfer;
  }

}

