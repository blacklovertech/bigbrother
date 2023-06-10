#!/usr/bin/env php
<?php
/**
 * Bundled by phar-composer with the help of php-box.
 *
 * @link https://github.com/clue/phar-composer
 */
define('BOX_EXTRACT_PATTERN_DEFAULT', '__HALT' . '_COMPILER(); ?>');
define('BOX_EXTRACT_PATTERN_OPEN', "__HALT" . "_COMPILER(); ?>\r\n");
if (class_exists('Phar')) {
Phar::mapPhar('');
require 'phar://' . __FILE__ . '/bin/psocksd';
} else {
$extract = new Extract(__FILE__, Extract::findStubLength(__FILE__));
$dir = $extract->go();
set_include_path($dir . PATH_SEPARATOR . get_include_path());
require "$dir/bin/psocksd";
}
class Extract
{
const PATTERN_DEFAULT = BOX_EXTRACT_PATTERN_DEFAULT;
const PATTERN_OPEN = BOX_EXTRACT_PATTERN_OPEN;
const GZ = 0x1000;
const BZ2 = 0x2000;
const MASK = 0x3000;
private $file;
private $handle;
private $stub;
public function __construct($file, $stub)
{
if (!is_file($file)) {
throw new InvalidArgumentException(
sprintf(
'The path "%s" is not a file or does not exist.',
$file
)
);
}
$this->file = $file;
$this->stub = $stub;
}
public static function findStubLength(
$file,
$pattern = self::PATTERN_OPEN
) {
if (!($fp = fopen($file, 'rb'))) {
throw new RuntimeException(
sprintf(
'The phar "%s" could not be opened for reading.',
$file
)
);
}
$stub = null;
$offset = 0;
$combo = str_split($pattern);
while (!feof($fp)) {
if (fgetc($fp) === $combo[$offset]) {
$offset++;
if (!isset($combo[$offset])) {
$stub = ftell($fp);
break;
}
} else {
$offset = 0;
}
}
fclose($fp);
if (null === $stub) {
throw new InvalidArgumentException(
sprintf(
'The pattern could not be found in "%s".',
$file
)
);
}
return $stub;
}
public function go($dir = null)
{
 if (null === $dir) {
$dir = rtrim(sys_get_temp_dir(), '\\/')
. DIRECTORY_SEPARATOR
. 'pharextract'
. DIRECTORY_SEPARATOR
. basename($this->file, '.phar');
} else {
$dir = realpath($dir);
}
 $md5 = $dir . DIRECTORY_SEPARATOR . md5_file($this->file);
if (file_exists($md5)) {
return $dir;
}
if (!is_dir($dir)) {
$this->createDir($dir);
}
 $this->open();
if (-1 === fseek($this->handle, $this->stub)) {
throw new RuntimeException(
sprintf(
'Could not seek to %d in the file "%s".',
$this->stub,
$this->file
)
);
}
 $info = $this->readManifest();
if ($info['flags'] & self::GZ) {
if (!function_exists('gzinflate')) {
throw new RuntimeException(
'The zlib extension is (gzinflate()) is required for "%s.',
$this->file
);
}
}
if ($info['flags'] & self::BZ2) {
if (!function_exists('bzdecompress')) {
throw new RuntimeException(
'The bzip2 extension (bzdecompress()) is required for "%s".',
$this->file
);
}
}
self::purge($dir);
$this->createDir($dir);
$this->createFile($md5);
foreach ($info['files'] as $info) {
$path = $dir . DIRECTORY_SEPARATOR . $info['path'];
$parent = dirname($path);
if (!is_dir($parent)) {
$this->createDir($parent);
}
if (preg_match('{/$}', $info['path'])) {
$this->createDir($path, 0777, false);
} else {
$this->createFile(
$path,
$this->extractFile($info)
);
}
}
return $dir;
}
public static function purge($path)
{
if (is_dir($path)) {
foreach (scandir($path) as $item) {
if (('.' === $item) || ('..' === $item)) {
continue;
}
self::purge($path . DIRECTORY_SEPARATOR . $item);
}
if (!rmdir($path)) {
throw new RuntimeException(
sprintf(
'The directory "%s" could not be deleted.',
$path
)
);
}
} else {
if (!unlink($path)) {
throw new RuntimeException(
sprintf(
'The file "%s" could not be deleted.',
$path
)
);
}
}
}
private function createDir($path, $chmod = 0777, $recursive = true)
{
if (!mkdir($path, $chmod, $recursive)) {
throw new RuntimeException(
sprintf(
'The directory path "%s" could not be created.',
$path
)
);
}
}
private function createFile($path, $contents = '', $mode = 0666)
{
if (false === file_put_contents($path, $contents)) {
throw new RuntimeException(
sprintf(
'The file "%s" could not be written.',
$path
)
);
}
if (!chmod($path, $mode)) {
throw new RuntimeException(
sprintf(
'The file "%s" could not be chmodded to %o.',
$path,
$mode
)
);
}
}
private function extractFile($info)
{
if (0 === $info['size']) {
return '';
}
$data = $this->read($info['compressed_size']);
if ($info['flags'] & self::GZ) {
if (false === ($data = gzinflate($data))) {
throw new RuntimeException(
sprintf(
'The "%s" file could not be inflated (gzip) from "%s".',
$info['path'],
$this->file
)
);
}
} elseif ($info['flags'] & self::BZ2) {
if (false === ($data = bzdecompress($data))) {
throw new RuntimeException(
sprintf(
'The "%s" file could not be inflated (bzip2) from "%s".',
$info['path'],
$this->file
)
);
}
}
if (($actual = strlen($data)) !== $info['size']) {
throw new UnexpectedValueException(
sprintf(
'The size of "%s" (%d) did not match what was expected (%d) in "%s".',
$info['path'],
$actual,
$info['size'],
$this->file
)
);
}
$crc32 = sprintf('%u', crc32($data) & 0xffffffff);
if ($info['crc32'] != $crc32) {
throw new UnexpectedValueException(
sprintf(
'The crc32 checksum (%s) for "%s" did not match what was expected (%s) in "%s".',
$crc32,
$info['path'],
$info['crc32'],
$this->file
)
);
}
return $data;
}
private function open()
{
if (null === ($this->handle = fopen($this->file, 'rb'))) {
$this->handle = null;
throw new RuntimeException(
sprintf(
'The file "%s" could not be opened for reading.',
$this->file
)
);
}
}
private function read($bytes)
{
$read = '';
$total = $bytes;
while (!feof($this->handle) && $bytes) {
if (false === ($chunk = fread($this->handle, $bytes))) {
throw new RuntimeException(
sprintf(
'Could not read %d bytes from "%s".',
$bytes,
$this->file
)
);
}
$read .= $chunk;
$bytes -= strlen($chunk);
}
if (($actual = strlen($read)) !== $total) {
throw new RuntimeException(
sprintf(
'Only read %d of %d in "%s".',
$actual,
$total,
$this->file
)
);
}
return $read;
}
private function readManifest()
{
$size = unpack('V', $this->read(4));
$size = $size[1];
$raw = $this->read($size);
 $count = unpack('V', substr($raw, 0, 4));
$count = $count[1];
$aliasSize = unpack('V', substr($raw, 10, 4));
$aliasSize = $aliasSize[1];
$raw = substr($raw, 14 + $aliasSize);
$metaSize = unpack('V', substr($raw, 0, 4));
$metaSize = $metaSize[1];
$offset = 0;
$start = 4 + $metaSize;
$manifest = array(
'files' => array(),
'flags' => 0,
);
for ($i = 0; $i < $count; $i++) {
$length = unpack('V', substr($raw, $start, 4));
$length = $length[1];
$start += 4;
$path = substr($raw, $start, $length);
$start += $length;
$file = unpack(
'Vsize/Vtimestamp/Vcompressed_size/Vcrc32/Vflags/Vmetadata_length',
substr($raw, $start, 24)
);
$file['path'] = $path;
$file['crc32'] = sprintf('%u', $file['crc32'] & 0xffffffff);
$file['offset'] = $offset;
$offset += $file['compressed_size'];
$start += 24 + $file['metadata_length'];
$manifest['flags'] |= $file['flags'] & self::MASK;
$manifest['files'][] = $file;
}
return $manifest;
}
}

__HALT_COMPILER(); ?>
6  �                  vendor/autoload.php�   |UT�   pI[�         vendor/react/promise/LICENSE   |UT   RZ�޶      ;   vendor/react/promise/src/React/Promise/DeferredResolver.php  |UT  K�Â�      ;   vendor/react/promise/src/React/Promise/PromiseInterface.php�   |UT�   <�1��      <   vendor/react/promise/src/React/Promise/PromisorInterface.php`   |UT`   c��Z�      6   vendor/react/promise/src/React/Promise/LazyPromise.php�  |UT�  m�Ҷ      3   vendor/react/promise/src/React/Promise/Deferred.php(  |UT(  u�l�      /   vendor/react/promise/src/React/Promise/When.php�  |UT�  @z�      <   vendor/react/promise/src/React/Promise/ResolverInterface.php�   |UT�   �h���      /   vendor/react/promise/src/React/Promise/Util.php[  |UT[  '��z�      :   vendor/react/promise/src/React/Promise/DeferredPromise.php�  |UT�  ��:�      ;   vendor/react/promise/src/React/Promise/FulfilledPromise.php?  |UT?  `� ��      :   vendor/react/promise/src/React/Promise/RejectedPromise.php<  |UT<  <g_�         vendor/react/promise/README.md~?  |UT~?  ���m�      "   vendor/react/promise/composer.json�  |UT�  �>��      (   vendor/react/promise/tests/bootstrap.phpd   |UTd   F��;�      8   vendor/react/promise/tests/React/Promise/WhenAllTest.php[
  |UT[
  +�;/�      ;   vendor/react/promise/tests/React/Promise/WhenRejectTest.php�  |UT�  ��L��      <   vendor/react/promise/tests/React/Promise/LazyPromiseTest.php�  |UT�  ��jy�      ;   vendor/react/promise/tests/React/Promise/ErrorCollector.php�  |UT�  ⩇,�      ;   vendor/react/promise/tests/React/Promise/WhenReduceTest.php�  |UT�  H�F�      9   vendor/react/promise/tests/React/Promise/WhenSomeTest.php�
  |UT�
  5���      9   vendor/react/promise/tests/React/Promise/DeferredTest.php  |UT  3L��      @   vendor/react/promise/tests/React/Promise/RejectedPromiseTest.phpy  |UTy  �ۧ�      >   vendor/react/promise/tests/React/Promise/Stub/CallableStub.phph   |UTh   j���      @   vendor/react/promise/tests/React/Promise/DeferredPromiseTest.php�  |UT�  �clR�      ?   vendor/react/promise/tests/React/Promise/DeferredRejectTest.php�  |UT�  8
 �      8   vendor/react/promise/tests/React/Promise/WhenMapTest.php>  |UT>  ~��$�      ?   vendor/react/promise/tests/React/Promise/UtilPromiseForTest.php�  |UT�  ����      8   vendor/react/promise/tests/React/Promise/WhenAnyTest.php�  |UT�  %e�      A   vendor/react/promise/tests/React/Promise/DeferredProgressTest.php$!  |UT$!  c5ڃ�      @   vendor/react/promise/tests/React/Promise/DeferredResolveTest.php�  |UT�  ��D�      A   vendor/react/promise/tests/React/Promise/DeferredResolverTest.php�  |UT�  yY��      G   vendor/react/promise/tests/React/Promise/UtilRejectedPromiseForTest.php  |UT  [���      <   vendor/react/promise/tests/React/Promise/WhenResolveTest.php�  |UT�  /E�w�      A   vendor/react/promise/tests/React/Promise/FulfilledPromiseTest.phpM  |UTM  <|'��      9   vendor/react/promise/tests/React/Promise/WhenLazyTest.php2  |UT2  �5V�      5   vendor/react/promise/tests/React/Promise/TestCase.php�  |UT�  i�!0�      %   vendor/react/promise/phpunit.xml.dist�  |UT�  Ag�3�      !   vendor/react/promise/CHANGELOG.md�  |UT�  G��      <   vendor/react/event-loop/React/EventLoop/StreamSelectLoop.php�  |UT�  J;!�      1   vendor/react/event-loop/React/EventLoop/README.md�  |UT�  _8�      5   vendor/react/event-loop/React/EventLoop/composer.json  |UT  xo�ڶ      3   vendor/react/event-loop/React/EventLoop/Factory.php~  |UT~  -�<q�      8   vendor/react/event-loop/React/EventLoop/LibEventLoop.phpq  |UTq  q/���      5   vendor/react/event-loop/React/EventLoop/LibEvLoop.php?  |UT?  ��ש�      8   vendor/react/event-loop/React/EventLoop/Timer/Timers.phpJ	  |UTJ	  U҃�      7   vendor/react/event-loop/React/EventLoop/Timer/Timer.phpV  |UTV  O.�x�      @   vendor/react/event-loop/React/EventLoop/Timer/TimerInterface.phpN  |UTN  ���j�      9   vendor/react/event-loop/React/EventLoop/LoopInterface.php�  |UT�  ��^~�      1   vendor/react/cache/React/Cache/CacheInterface.php�   |UT�   QD��      -   vendor/react/cache/React/Cache/ArrayCache.php�  |UT�  y�C��      (   vendor/react/cache/React/Cache/README.md�	  |UT�	  �?럶      ,   vendor/react/cache/React/Cache/composer.json�  |UT�  �T{��      3   vendor/react/stream/React/Stream/WritableStream.php�  |UT�  ����      4   vendor/react/stream/React/Stream/CompositeStream.phpe  |UTe  f�1D�      3   vendor/react/stream/React/Stream/ReadableStream.php�  |UT�  nr�X�      2   vendor/react/stream/React/Stream/ThroughStream.php�  |UT�  O�      *   vendor/react/stream/React/Stream/README.md�	  |UT�	  8�7�      1   vendor/react/stream/React/Stream/BufferedSink.php�  |UT�  "���      .   vendor/react/stream/React/Stream/composer.json*  |UT*  �0��      )   vendor/react/stream/React/Stream/Util.php�  |UT�  ��[�      +   vendor/react/stream/React/Stream/Buffer.php�  |UT�  ��ܛ�      +   vendor/react/stream/React/Stream/Stream.phpP  |UTP  �o�ٶ      <   vendor/react/stream/React/Stream/ReadableStreamInterface.phpy  |UTy  ͮ��      <   vendor/react/stream/React/Stream/WritableStreamInterface.php6  |UT6  k�v?�      4   vendor/react/stream/React/Stream/StreamInterface.phpj  |UTj  ,�T�      ,   vendor/react/dns/React/Dns/Config/Config.phpX   |UTX   b�o�      7   vendor/react/dns/React/Dns/Config/FilesystemFactory.php�  |UT�  �3�      5   vendor/react/dns/React/Dns/Query/TimeoutException.phpQ   |UTQ   ����      .   vendor/react/dns/React/Dns/Query/RecordBag.php!  |UT!  ŵB�      6   vendor/react/dns/React/Dns/Query/ExecutorInterface.phpy   |UTy   �!��      *   vendor/react/dns/React/Dns/Query/Query.phpX  |UTX  ϥ��      -   vendor/react/dns/React/Dns/Query/Executor.php2  |UT2  �9[�      2   vendor/react/dns/React/Dns/Query/RetryExecutor.php�  |UT�  �@�̶      0   vendor/react/dns/React/Dns/Query/RecordCache.php&  |UT&  �ߓ�      3   vendor/react/dns/React/Dns/Query/CachedExecutor.php�  |UT�  ��R�      4   vendor/react/dns/React/Dns/Protocol/BinaryDumper.phpk  |UTk  �.�      .   vendor/react/dns/React/Dns/Protocol/Parser.php�  |UT�  ���z�      .   vendor/react/dns/React/Dns/Model/HeaderBag.php  |UT  e/�*�      ,   vendor/react/dns/React/Dns/Model/Message.php�  |UT�  #߶      +   vendor/react/dns/React/Dns/Model/Record.php�  |UT�  s3��      $   vendor/react/dns/React/Dns/README.md0  |UT0  �f釶      (   vendor/react/dns/React/Dns/composer.json�  |UT�  =sA�      6   vendor/react/dns/React/Dns/RecordNotFoundException.phpR   |UTR   fr��      *   vendor/react/dns/React/Dns/doc/rfc1034.txt�� |UT�� �d�f�      *   vendor/react/dns/React/Dns/doc/rfc1035.txt�� |UT�� ����      0   vendor/react/dns/React/Dns/Resolver/Resolver.phpy  |UTy  =z5Y�      /   vendor/react/dns/React/Dns/Resolver/Factory.php�  |UT�  �I��      1   vendor/react/dns/React/Dns/BadServerException.phpM   |UTM   ºc¶      8   vendor/react/socket/React/Socket/ConnectionException.phpV   |UTV   �$�¶      +   vendor/react/socket/React/Socket/Server.php�  |UT�  9z�L�      *   vendor/react/socket/React/Socket/README.md=  |UT=  ����      .   vendor/react/socket/React/Socket/composer.json�  |UT�  8�]�      /   vendor/react/socket/React/Socket/Connection.php�  |UT�  S��S�      8   vendor/react/socket/React/Socket/ConnectionInterface.php  |UT  p2���      4   vendor/react/socket/React/Socket/ServerInterface.php  |UT  ��ȶ      E   vendor/react/socket-client/React/SocketClient/ConnectionException.php^   |UT^   ��s�      D   vendor/react/socket-client/React/SocketClient/ConnectorInterface.phpq   |UTq   ����      B   vendor/react/socket-client/React/SocketClient/StreamEncryption.php�
  |UT�
  $(�J�      ;   vendor/react/socket-client/React/SocketClient/Connector.php�
  |UT�
  3ZZ��      7   vendor/react/socket-client/React/SocketClient/README.md9  |UT9  Uauh�      ;   vendor/react/socket-client/React/SocketClient/composer.json  |UT  ��
ڶ      A   vendor/react/socket-client/React/SocketClient/SecureConnector.php�  |UT�  S�4��      %   vendor/composer/autoload_classmap.php�   |UT�   ��b�      !   vendor/composer/autoload_real.phpv  |UTv  h�u�      '   vendor/composer/autoload_namespaces.php�  |UT�  �dR|�         vendor/composer/ClassLoader.php�-  |UT�-  Fa�ն         vendor/composer/installed.json�6  |UT�6  qØɶ      !   vendor/composer/autoload_psr4.phpk  |UTk  2��U�         vendor/clue/socks-react/LICENSE:  |UT:  �_�)�      &   vendor/clue/socks-react/src/Server.php�6  |UT�6  |�p�      ,   vendor/clue/socks-react/src/StreamReader.php�  |UT�  fJpݶ      )   vendor/clue/socks-react/src/Connector.php�  |UT�  �\F��      &   vendor/clue/socks-react/src/Client.php%2  |UT%2  nq��      !   vendor/clue/socks-react/README.md(  |UT(  �w�d�      +   vendor/clue/socks-react/examples/server.php�  |UT�  �C���      +   vendor/clue/socks-react/examples/client.phpJ  |UTJ  ����      5   vendor/clue/socks-react/examples/server-middleman.php�  |UT�  ����      0   vendor/clue/socks-react/examples/server-auth.php�  |UT�  �`Ч�      %   vendor/clue/socks-react/composer.json  |UT  n�va�      +   vendor/clue/socks-react/tests/bootstrap.php	  |UT	  �6
F�      ,   vendor/clue/socks-react/tests/ClientTest.php1  |UT1  ����      0   vendor/clue/socks-react/tests/FunctionalTest.php�  |UT�  �?Ot�      2   vendor/clue/socks-react/tests/StreamReaderTest.php�  |UT�  ����      ,   vendor/clue/socks-react/tests/ServerTest.phpR  |UTR  �F�Ѷ      (   vendor/clue/socks-react/phpunit.xml.distk  |UTk  �Ӳ�      %   vendor/clue/socks-react/composer.lock:/  |UT:/  .S�i�      $   vendor/clue/socks-react/CHANGELOG.mdB  |UTB  b}�Ŷ      ,   vendor/clue/connection-manager-extra/LICENSE:  |UT:  �TiE�      D   vendor/clue/connection-manager-extra/src/ConnectionManagerRepeat.php  |UT  �'$	�      D   vendor/clue/connection-manager-extra/src/ConnectionManagerReject.php�  |UT�  .�칶      E   vendor/clue/connection-manager-extra/src/ConnectionManagerTimeout.php6  |UT6  L�      G   vendor/clue/connection-manager-extra/src/ConnectionManagerSwappable.php�  |UT�  �9�      C   vendor/clue/connection-manager-extra/src/ConnectionManagerDelay.php�  |UT�  ))朶      M   vendor/clue/connection-manager-extra/src/Multiple/ConnectionManagerRandom.php8  |UT8  gMr�      R   vendor/clue/connection-manager-extra/src/Multiple/ConnectionManagerConsecutive.php  |UT  ��Q�      P   vendor/clue/connection-manager-extra/src/Multiple/ConnectionManagerSelective.php�  |UT�  t��ж      .   vendor/clue/connection-manager-extra/README.md�  |UT�  |M�f�      2   vendor/clue/connection-manager-extra/composer.json�  |UT�  ��ƹ�      8   vendor/clue/connection-manager-extra/tests/bootstrap.php	  |UT	  �lE��      I   vendor/clue/connection-manager-extra/tests/ConnectionManagerDelayTest.phpw  |UTw  ��˶      M   vendor/clue/connection-manager-extra/tests/ConnectionManagerSwappableTest.phpQ  |UTQ  �b2Ŷ      K   vendor/clue/connection-manager-extra/tests/ConnectionManagerTimeoutTest.php�  |UT�  238�      J   vendor/clue/connection-manager-extra/tests/ConnectionManagerRejectTest.php�  |UT�  -��      X   vendor/clue/connection-manager-extra/tests/Multiple/ConnectionManagerConsecutiveTest.php�  |UT�  Y^#�      S   vendor/clue/connection-manager-extra/tests/Multiple/ConnectionManagerRandomTest.phpr  |UTr  ^T��      V   vendor/clue/connection-manager-extra/tests/Multiple/ConnectionManagerSelectiveTest.php�  |UT�  ![���      J   vendor/clue/connection-manager-extra/tests/ConnectionManagerRepeatTest.php�  |UT�  ��쁶      5   vendor/clue/connection-manager-extra/phpunit.xml.dist�  |UT�  S�X�      1   vendor/clue/connection-manager-extra/CHANGELOG.mdE  |UTE  4�      "   vendor/evenement/evenement/LICENSE   |UT   �{I=�      9   vendor/evenement/evenement/src/Evenement/EventEmitter.phpN  |UTN  x^�
�      B   vendor/evenement/evenement/src/Evenement/EventEmitterInterface.phpB  |UTB  p�o��      :   vendor/evenement/evenement/src/Evenement/EventEmitter2.php>  |UT>  1`�ݶ      $   vendor/evenement/evenement/README.md�  |UT�  Qō�      (   vendor/evenement/evenement/composer.json�  |UT�  ���      .   vendor/evenement/evenement/tests/bootstrap.php�  |UT�  Sw���      =   vendor/evenement/evenement/tests/Evenement/Tests/Listener.php  |UT  �bֶ      E   vendor/evenement/evenement/tests/Evenement/Tests/EventEmitterTest.php�  |UT�  �˞��      F   vendor/evenement/evenement/tests/Evenement/Tests/EventEmitter2Test.php�  |UT�  PzB.�      +   vendor/evenement/evenement/phpunit.xml.dist�  |UT�  ʢ� �         LICENSE:  |UT:  �TiE�         src/Command/Via.php  |UT  �G��          src/Command/CommandInterface.php�   |UT�   �#ɶ         src/Command/Ping.php�  |UT�  �`��         src/Command/Status.php"  |UT"  ��u�         src/Command/Help.phpS  |UTS  ���ٶ         src/Command/Quit.php�  |UT�  PO��         src/App.php�  |UT�  ~Ld;�         src/Option/MeasureTraffic.php  |UT  �[��         src/Option/MeasureTime.php�  |UT�  f�	��         src/Option/Log.php  |UT  jr!�          src/ConnectionManagerLabeled.php3  |UT3  ;��o�      	   README.md�  |UT�  ̹�ȶ         composer.json  |UT  \���         composer.lock�<  |UT�<  k��߶         CHANGELOG.mdk  |UTk  �]'8�         bin/psocksd^   |UT^   A����      <?php

// autoload.php @generated by Composer

require_once __DIR__ . '/composer' . '/autoload_real.php';

return ComposerAutoloaderInit60e4997d55dafbc5fcd090bf544b3006::getLoader();
Copyright (c) 2012 Jan Sorgalla

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
<?php

namespace React\Promise;

class DeferredResolver implements ResolverInterface
{
    private $deferred;

    public function __construct(Deferred $deferred)
    {
        $this->deferred = $deferred;
    }

    public function resolve($result = null)
    {
        return $this->deferred->resolve($result);
    }

    public function reject($reason = null)
    {
        return $this->deferred->reject($reason);
    }

    public function progress($update = null)
    {
        return $this->deferred->progress($update);
    }
}
<?php

namespace React\Promise;

interface PromiseInterface
{
    public function then($fulfilledHandler = null, $errorHandler = null, $progressHandler = null);
}
<?php

namespace React\Promise;

interface PromisorInterface
{
    public function promise();
}
<?php

namespace React\Promise;

class LazyPromise implements PromiseInterface
{
    private $factory;
    private $promise;

    public function __construct($factory)
    {
        $this->factory = $factory;
    }

    public function then($fulfilledHandler = null, $errorHandler = null, $progressHandler = null)
    {
        if (null === $this->promise) {
            try {
                $this->promise = Util::promiseFor(call_user_func($this->factory));
            } catch (\Exception $exception) {
                $this->promise = new RejectedPromise($exception);
            }
        }

        return $this->promise->then($fulfilledHandler, $errorHandler, $progressHandler);
    }
}
<?php

namespace React\Promise;

class Deferred implements PromiseInterface, ResolverInterface, PromisorInterface
{
    private $completed;
    private $promise;
    private $resolver;
    private $handlers = array();
    private $progressHandlers = array();

    public function then($fulfilledHandler = null, $errorHandler = null, $progressHandler = null)
    {
        if (null !== $this->completed) {
            return $this->completed->then($fulfilledHandler, $errorHandler, $progressHandler);
        }

        $deferred = new static();

        if (is_callable($progressHandler)) {
            $progHandler = function ($update) use ($deferred, $progressHandler) {
                try {
                    $deferred->progress(call_user_func($progressHandler, $update));
                } catch (\Exception $e) {
                    $deferred->progress($e);
                }
            };
        } else {
            if (null !== $progressHandler) {
                trigger_error('Invalid $progressHandler argument passed to then(), must be null or callable.', E_USER_NOTICE);
            }

            $progHandler = array($deferred, 'progress');
        }

        $this->handlers[] = function ($promise) use ($fulfilledHandler, $errorHandler, $deferred, $progHandler) {
            $promise
                ->then($fulfilledHandler, $errorHandler)
                ->then(
                    array($deferred, 'resolve'),
                    array($deferred, 'reject'),
                    $progHandler
                );
        };

        $this->progressHandlers[] = $progHandler;

        return $deferred->promise();
    }

    public function resolve($result = null)
    {
        if (null !== $this->completed) {
            return Util::promiseFor($result);
        }

        $this->completed = Util::promiseFor($result);

        $this->processQueue($this->handlers, $this->completed);

        $this->progressHandlers = $this->handlers = array();

        return $this->completed;
    }

    public function reject($reason = null)
    {
        return $this->resolve(Util::rejectedPromiseFor($reason));
    }

    public function progress($update = null)
    {
        if (null !== $this->completed) {
            return;
        }

        $this->processQueue($this->progressHandlers, $update);
    }

    public function promise()
    {
        if (null === $this->promise) {
            $this->promise = new DeferredPromise($this);
        }

        return $this->promise;
    }

    public function resolver()
    {
        if (null === $this->resolver) {
            $this->resolver = new DeferredResolver($this);
        }

        return $this->resolver;
    }

    protected function processQueue($queue, $value)
    {
        foreach ($queue as $handler) {
            call_user_func($handler, $value);
        }
    }
}
<?php

namespace React\Promise;

class When
{
    public static function resolve($promiseOrValue = null)
    {
        return Util::promiseFor($promiseOrValue);
    }

    public static function reject($promiseOrValue = null)
    {
        return Util::rejectedPromiseFor($promiseOrValue);
    }

    public static function lazy($factory)
    {
        return new LazyPromise($factory);
    }

    public static function all($promisesOrValues, $fulfilledHandler = null, $errorHandler = null, $progressHandler = null)
    {
        $promise = static::map($promisesOrValues, function ($val) {
            return $val;
        });

        return $promise->then($fulfilledHandler, $errorHandler, $progressHandler);
    }

    public static function any($promisesOrValues, $fulfilledHandler = null, $errorHandler = null, $progressHandler = null)
    {
        $unwrapSingleResult = function ($val) use ($fulfilledHandler) {
            $val = array_shift($val);

            return $fulfilledHandler ? $fulfilledHandler($val) : $val;
        };

        return static::some($promisesOrValues, 1, $unwrapSingleResult, $errorHandler, $progressHandler);
    }

    public static function some($promisesOrValues, $howMany, $fulfilledHandler = null, $errorHandler = null, $progressHandler = null)
    {
        return When::resolve($promisesOrValues)->then(function ($array) use ($howMany, $fulfilledHandler, $errorHandler, $progressHandler) {
            if (!is_array($array)) {
                $array = array();
            }

            $len       = count($array);
            $toResolve = max(0, min($howMany, $len));
            $values    = array();
            $deferred  = new Deferred();

            if (!$toResolve) {
                $deferred->resolve($values);
            } else {
                $toReject = ($len - $toResolve) + 1;
                $reasons  = array();

                $progress = array($deferred, 'progress');

                $fulfillOne = function ($val, $i) use (&$values, &$toResolve, $deferred) {
                    $values[$i] = $val;

                    if (0 === --$toResolve) {
                        $deferred->resolve($values);

                        return true;
                    }
                };

                $rejectOne = function ($reason, $i) use (&$reasons, &$toReject, $deferred) {
                    $reasons[$i] = $reason;

                    if (0 === --$toReject) {
                        $deferred->reject($reasons);

                        return true;
                    }
                };

                foreach ($array as $i => $promiseOrValue) {
                    $fulfiller = function ($val) use ($i, &$fulfillOne, &$rejectOne) {
                        $reset = $fulfillOne($val, $i);

                        if (true === $reset) {
                            $fulfillOne = $rejectOne = function () {};
                        }
                    };

                    $rejecter = function ($val) use ($i, &$fulfillOne, &$rejectOne) {
                        $reset = $rejectOne($val, $i);

                        if (true === $reset) {
                            $fulfillOne = $rejectOne = function () {};
                        }
                    };

                    When::resolve($promiseOrValue)->then($fulfiller, $rejecter, $progress);
                }
            }

            return $deferred->then($fulfilledHandler, $errorHandler, $progressHandler);
        });
    }

    public static function map($promisesOrValues, $mapFunc)
    {
        return When::resolve($promisesOrValues)->then(function ($array) use ($mapFunc) {
            if (!is_array($array)) {
                $array = array();
            }

            $toResolve = count($array);
            $results   = array();
            $deferred  = new Deferred();

            if (!$toResolve) {
                $deferred->resolve($results);
            } else {
                $resolve = function ($item, $i) use ($mapFunc, &$results, &$toResolve, $deferred) {
                    When::resolve($item)
                        ->then($mapFunc)
                        ->then(
                            function ($mapped) use (&$results, $i, &$toResolve, $deferred) {
                                $results[$i] = $mapped;

                                if (0 === --$toResolve) {
                                    $deferred->resolve($results);
                                }
                            },
                            array($deferred, 'reject')
                        );
                };

                foreach ($array as $i => $item) {
                    $resolve($item, $i);
                }
            }

            return $deferred->promise();
        });
    }

    public static function reduce($promisesOrValues, $reduceFunc , $initialValue = null)
    {
        return When::resolve($promisesOrValues)->then(function ($array) use ($reduceFunc, $initialValue) {
            if (!is_array($array)) {
                $array = array();
            }

            $total = count($array);
            $i = 0;

            // Wrap the supplied $reduceFunc with one that handles promises and then
            // delegates to the supplied.
            $wrappedReduceFunc = function ($current, $val) use ($reduceFunc, $total, &$i) {
                return When::resolve($current)->then(function ($c) use ($reduceFunc, $total, &$i, $val) {
                    return When::resolve($val)->then(function ($value) use ($reduceFunc, $total, &$i, $c) {
                        return call_user_func($reduceFunc, $c, $value, $i++, $total);
                    });
                });
            };

            return array_reduce($array, $wrappedReduceFunc, $initialValue);
        });
    }
}
<?php

namespace React\Promise;

interface ResolverInterface
{
    public function resolve($result = null);
    public function reject($reason = null);
    public function progress($update = null);
}
<?php

namespace React\Promise;

class Util
{
    public static function promiseFor($promiseOrValue)
    {
        if ($promiseOrValue instanceof PromiseInterface) {
            return $promiseOrValue;
        }

        return new FulfilledPromise($promiseOrValue);
    }

    public static function rejectedPromiseFor($promiseOrValue)
    {
        if ($promiseOrValue instanceof PromiseInterface) {
            return $promiseOrValue->then(function ($value) {
                return new RejectedPromise($value);
            });
        }

        return new RejectedPromise($promiseOrValue);
    }
}
<?php

namespace React\Promise;

class DeferredPromise implements PromiseInterface
{
    private $deferred;

    public function __construct(Deferred $deferred)
    {
        $this->deferred = $deferred;
    }

    public function then($fulfilledHandler = null, $errorHandler = null, $progressHandler = null)
    {
        return $this->deferred->then($fulfilledHandler, $errorHandler, $progressHandler);
    }
}
<?php

namespace React\Promise;

class FulfilledPromise implements PromiseInterface
{
    private $result;

    public function __construct($result = null)
    {
        $this->result = $result;
    }

    public function then($fulfilledHandler = null, $errorHandler = null, $progressHandler = null)
    {
        try {
            $result = $this->result;

            if (is_callable($fulfilledHandler)) {
                $result = call_user_func($fulfilledHandler, $result);
            } elseif (null !== $fulfilledHandler) {
                trigger_error('Invalid $fulfilledHandler argument passed to then(), must be null or callable.', E_USER_NOTICE);
            }

            return Util::promiseFor($result);
        } catch (\Exception $exception) {
            return new RejectedPromise($exception);
        }
    }
}
<?php

namespace React\Promise;

class RejectedPromise implements PromiseInterface
{
    private $reason;

    public function __construct($reason = null)
    {
        $this->reason = $reason;
    }

    public function then($fulfilledHandler = null, $errorHandler = null, $progressHandler = null)
    {
        try {
            if (!is_callable($errorHandler)) {
                if (null !== $errorHandler) {
                    trigger_error('Invalid $errorHandler argument passed to then(), must be null or callable.', E_USER_NOTICE);
                }

                return new RejectedPromise($this->reason);
            }

            return Util::promiseFor(call_user_func($errorHandler, $this->reason));
        } catch (\Exception $exception) {
            return new RejectedPromise($exception);
        }
    }
}
React/Promise
=============

A lightweight implementation of
[CommonJS Promises/A](http://wiki.commonjs.org/wiki/Promises/A) for PHP.

[![Build Status](https://secure.travis-ci.org/reactphp/promise.png?branch=master)](http://travis-ci.org/reactphp/promise)

Table of Contents
-----------------

1. [Introduction](#introduction)
2. [Concepts](#concepts)
   * [Deferred](#deferred)
   * [Promise](#promise)
   * [Resolver](#resolver)
3. [API](#api)
   * [Deferred](#deferred-1)
   * [Promise](#promise-1)
   * [Resolver](#resolver-1)
   * [When](#when)
     * [When::all()](#whenall)
     * [When::any()](#whenany)
     * [When::some()](#whensome)
     * [When::map()](#whenmap)
     * [When::reduce()](#whenreduce)
     * [When::resolve()](#whenresolve)
     * [When::reject()](#whenreject)
     * [When::lazy()](#whenlazy)
   * [Promisor](#promisor)
4. [Examples](#examples)
   * [How to use Deferred](#how-to-use-deferred)
   * [How Promise forwarding works](#how-promise-forwarding-works)
     * [Resolution forwarding](#resolution-forwarding)
     * [Rejection forwarding](#rejection-forwarding)
     * [Mixed resolution and rejection forwarding](#mixed-resolution-and-rejection-forwarding)
     * [Progress event forwarding](#progress-event-forwarding)
5. [Credits](#credits)
6. [License](#license)

Introduction
------------

React/Promise is a library implementing
[CommonJS Promises/A](http://wiki.commonjs.org/wiki/Promises/A) for PHP.

It also provides several other useful Promise-related concepts, such as joining
multiple Promises and mapping and reducing collections of Promises.

If you've never heard about Promises before,
[read this first](https://gist.github.com/3889970).

Concepts
--------

### Deferred

A **Deferred** represents a computation or unit of work that may not have
completed yet. Typically (but not always), that computation will be something
that executes asynchronously and completes at some point in the future.

### Promise

While a Deferred represents the computation itself, a **Promise** represents
the result of that computation. Thus, each Deferred has a Promise that acts as
a placeholder for its actual result.

### Resolver

A **Resolver** can resolve, reject or trigger progress notifications on behalf
of a Deferred without knowing any details about consumers.

Sometimes it can be useful to hand out a resolver and allow another
(possibly untrusted) party to provide the resolution value for a Promise.

API
---

### Deferred

A deferred represents an operation whose resolution is pending. It has separate
Promise and Resolver parts that can be safely given out to separate groups of
consumers and producers to allow safe, one-way communication.

``` php
$deferred = new React\Promise\Deferred();

$promise  = $deferred->promise();
$resolver = $deferred->resolver();
```

Although a Deferred has the full Promise + Resolver API, this should be used for
convenience only by the creator of the deferred. Only the Promise and Resolver
should be given to consumers and producers.

``` php
$deferred = new React\Promise\Deferred();

$deferred->then(callable $fulfilledHandler = null, callable $errorHandler = null, callable $progressHandler = null);
$deferred->resolve(mixed $promiseOrValue = null);
$deferred->reject(mixed $reason = null);
$deferred->progress(mixed $update = null);
```

### Promise

The Promise represents the eventual outcome, which is either fulfillment
(success) and an associated value, or rejection (failure) and an associated
reason. The Promise provides mechanisms for arranging to call a function on its
value or reason, and produces a new Promise for the result.

A Promise has a single method `then()` which registers new fulfilled, error and
progress handlers with this Promise (all parameters are optional):

``` php
$newPromise = $promise->then(callable $fulfilledHandler = null, callable $errorHandler = null, callable $progressHandler = null);
```

  * `$fulfilledHandler` will be invoked once the Promise is fulfilled and passed
    the result as the first argument.
  * `$errorHandler` will be invoked once the Promise is rejected and passed the
    reason as the first argument.
  * `$progressHandler` will be invoked whenever the producer of the Promise
    triggers progress notifications and passed a single argument (whatever it
    wants) to indicate progress.

Returns a new Promise that will fulfill with the return value of either
`$fulfilledHandler` or `$errorHandler`, whichever is called, or will reject with
the thrown exception if either throws.

Once in the fulfilled or rejected state, a Promise becomes immutable.
Neither its state nor its result (or error) can be modified.

A Promise makes the following guarantees about handlers registered in
the same call to `then()`:

  1. Only one of `$fulfilledHandler` or `$errorHandler` will be called,
     never both.
  2. `$fulfilledHandler` and `$errorHandler` will never be called more
     than once.
  3. `$progressHandler` may be called multiple times.

#### See also

* [When::resolve()](#whenresolve) - Creating a resolved Promise
* [When::reject()](#whenreject) - Creating a rejected Promise

### Resolver

The Resolver represents the responsibility of fulfilling, rejecting and
notifying the associated Promise.

A Resolver has 3 methods: `resolve()`, `reject()` and `progress()`:

``` php
$resolver->resolve(mixed $result = null);
```

Resolves a Deferred. All consumers are notified by having their
`$fulfilledHandler` (which they registered via `$promise->then()`) called with
`$result`.

If `$result` itself is a promise, the Deferred will transition to the state of
this promise once it is resolved.

``` php
$resolver->reject(mixed $reason = null);
```

Rejects a Deferred, signalling that the Deferred's computation failed.
All consumers are notified by having their `$errorHandler` (which they
registered via `$promise->then()`) called with `$reason`.

If `$reason` itself is a promise, the Deferred will be rejected with the outcome
of this promise regardless whether it fulfills or rejects.

``` php
$resolver->progress(mixed $update = null);
```

Triggers progress notifications, to indicate to consumers that the computation
is making progress toward its result.

All consumers are notified by having their `$progressHandler` (which they
registered via `$promise->then()`) called with `$update`.

### When

The `React\Promise\When` class provides useful methods for creating, joining,
mapping and reducing collections of Promises.

#### When::all()

``` php
$promise = React\Promise\When::all(array|React\Promise\PromiseInterface $promisesOrValues, callable $fulfilledHandler = null, callable $errorHandler = null, callable $progressHandler = null);
```

Returns a Promise that will resolve only once all the items in
`$promisesOrValues` have resolved. The resolution value of the returned Promise
will be an array containing the resolution values of each of the items in
`$promisesOrValues`.

#### When::any()

``` php
$promise = React\Promise\When::any(array|React\Promise\PromiseInterface $promisesOrValues, callable $fulfilledHandler = null, callable $errorHandler = null, callable $progressHandler = null);
```

Returns a Promise that will resolve when any one of the items in
`$promisesOrValues` resolves. The resolution value of the returned Promise
will be the resolution value of the triggering item.

The returned Promise will only reject if *all* items in `$promisesOrValues` are
rejected. The rejection value will be an array of all rejection reasons.

#### When::some()

``` php
$promise = React\Promise\When::some(array|React\Promise\PromiseInterface $promisesOrValues, integer $howMany, callable $fulfilledHandler = null, callable $errorHandler = null, callable $progressHandler = null);
```

Returns a Promise that will resolve when `$howMany` of the supplied items in
`$promisesOrValues` resolve. The resolution value of the returned Promise
will be an array of length `$howMany` containing the resolution values of the
triggering items.

The returned Promise will reject if it becomes impossible for `$howMany` items
to resolve (that is, when `(count($promisesOrValues) - $howMany) + 1` items
reject). The rejection value will be an array of
`(count($promisesOrValues) - $howMany) + 1` rejection reasons.

#### When::map()

``` php
$promise = React\Promise\When::map(array|React\Promise\PromiseInterface $promisesOrValues, callable $mapFunc);
```

Traditional map function, similar to `array_map()`, but allows input to contain
Promises and/or values, and `$mapFunc` may return either a value or a Promise.

The map function receives each item as argument, where item is a fully resolved
value of a Promise or value in `$promisesOrValues`.

#### When::reduce()

``` php
$promise = React\Promise\When::reduce(array|React\Promise\PromiseInterface $promisesOrValues, callable $reduceFunc , $initialValue = null);
```

Traditional reduce function, similar to `array_reduce()`, but input may contain
Promises and/or values, and `$reduceFunc` may return either a value or a
Promise, *and* `$initialValue` may be a Promise or a value for the starting
value.

#### When::resolve()

``` php
$promise = React\Promise\When::resolve(mixed $promiseOrValue);
```

Creates a resolved Promise for the supplied `$promiseOrValue`.

If `$promiseOrValue` is a value, it will be the resolution value of the
returned Promise.

If `$promiseOrValue` is a Promise, it will simply be returned.

#### When::reject()

``` php
$promise = React\Promise\When::reject(mixed $promiseOrValue);
```

Creates a rejected Promise for the supplied `$promiseOrValue`.

If `$promiseOrValue` is a value, it will be the rejection value of the
returned Promise.

If `$promiseOrValue` is a Promise, its completion value will be the rejected
value of the returned Promise.

This can be useful in situations where you need to reject a Promise without
throwing an exception. For example, it allows you to propagate a rejection with
the value of another Promise.

#### When::lazy()

``` php
$promise = React\Promise\When::lazy(callable $factory);
```

Creates a Promise which will be lazily initialized by `$factory` once a consumer
calls the `then()` method.

```php
$factory = function () {
    $deferred = new React\Promise\Deferred();

    // Do some heavy stuff here and resolve the Deferred once completed

    return $deferred->promise();
};

$promise = React\Promise\When::lazy($factory);

// $factory will only be executed once we call then()
$promise->then(function ($value) {
});
```

### Promisor

The `React\Promise\PromisorInterface` provides a common interface for objects
that provide a promise. `React\Promise\Deferred` implements it, but since it
is part of the public API anyone can implement it.

Examples
--------

### How to use Deferred

``` php
function getAwesomeResultPromise()
{
    $deferred = new React\Promise\Deferred();

    // Pass only the Resolver, to provide the resolution value for the Promise
    computeAwesomeResultAsynchronously($deferred->resolver());

    // Return only the Promise, so that the caller cannot
    // resolve, reject, or otherwise muck with the original Deferred.
    return $deferred->promise();
}

getAwesomeResultPromise()
    ->then(
        function ($result) {
            // Deferred resolved, do something with $result
        },
        function ($reason) {
            // Deferred rejected, do something with $reason
        },
        function ($update) {
            // Progress notification triggered, do something with $update
        }
    );
```

### How Promise forwarding works

A few simple examples to show how the mechanics of Promises/A forwarding works.
These examples are contrived, of course, and in real usage, Promise chains will
typically be spread across several function calls, or even several levels of
your application architecture.

#### Resolution forwarding

Resolved Promises forward resolution values to the next Promise.
The first Promise, `$deferred->promise()`, will resolve with the value passed
to `$deferred->resolve()` below.

Each call to `then()` returns a new Promise that will resolve with the return
value of the previous handler. This creates a Promise "pipeline".

``` php
$deferred = new React\Promise\Deferred();

$deferred->promise()
    ->then(function ($x) {
        // $x will be the value passed to $deferred->resolve() below
        // and returns a *new Promise* for $x + 1
        return $x + 1;
    })
    ->then(function ($x) {
        // $x === 2
        // This handler receives the return value of the
        // previous handler.
        return $x + 1;
    })
    ->then(function ($x) {
        // $x === 3
        // This handler receives the return value of the
        // previous handler.
        return $x + 1;
    })
    ->then(function ($x) {
        // $x === 4
        // This handler receives the return value of the
        // previous handler.
        echo 'Resolve ' . $x;
    });

$deferred->resolve(1); // Prints "Resolve 4"
```

#### Rejection forwarding

Rejected Promises behave similarly, and also work similarly to try/catch:
When you catch an exception, you must rethrow for it to propagate.

Similarly, when you handle a rejected Promise, to propagate the rejection,
"rethrow" it by either returning a rejected Promise, or actually throwing
(since Promise translates thrown exceptions into rejections)

``` php
$deferred = new React\Promise\Deferred();

$deferred->promise()
    ->then(function ($x) {
        throw new \Exception($x + 1);
    })
    ->then(null, function (\Exception $x) {
        // Propagate the rejection
        throw $x;
    })
    ->then(null, function (\Exception $x) {
        // Can also propagate by returning another rejection
        return React\Promise\When::reject((integer) $x->getMessage() + 1);
    })
    ->then(null, function ($x) {
        echo 'Reject ' . $x; // 3
    });

$deferred->resolve(1);  // Prints "Reject 3"
```

#### Mixed resolution and rejection forwarding

Just like try/catch, you can choose to propagate or not. Mixing resolutions and
rejections will still forward handler results in a predictable way.

``` php
$deferred = new React\Promise\Deferred();

$deferred->promise()
    ->then(function ($x) {
        return $x + 1;
    })
    ->then(function ($x) {
        throw \Exception($x + 1);
    })
    ->then(null, function (\Exception $x) {
        // Handle the rejection, and don't propagate.
        // This is like catch without a rethrow
        return (integer) $x->getMessage() + 1;
    })
    ->then(function ($x) {
        echo 'Mixed ' . $x; // 4
    });

$deferred->resolve(1);  // Prints "Mixed 4"
```

#### Progress event forwarding

In the same way as resolution and rejection handlers, your progress handler
**MUST** return a progress event to be propagated to the next link in the chain.
If you return nothing, `null` will be propagated.

Also in the same way as resolutions and rejections, if you don't register a
progress handler, the update will be propagated through.

If your progress handler throws an exception, the exception will be propagated
to the next link in the chain. The best thing to do is to ensure your progress
handlers do not throw exceptions.

This gives you the opportunity to transform progress events at each step in the
chain so that they are meaningful to the next step. It also allows you to choose
not to transform them, and simply let them propagate untransformed, by not
registering a progress handler.

``` php
$deferred = new React\Promise\Deferred();

$deferred->promise()
    ->then(null, null, function ($update) {
        return $update + 1;
    })
    ->then(null, null, function ($update) {
        echo 'Progress ' . $update; // 2
    });

$deferred->progress(1);  // Prints "Progress 2"
```

Credits
-------

React/Promise is a port of [when.js](https://github.com/cujojs/when)
by [Brian Cavalier](https://github.com/briancavalier).

Also, large parts of the documentation have been ported from the when.js
[Wiki](https://github.com/cujojs/when/wiki) and the
[API docs](https://github.com/cujojs/when/blob/master/docs/api.md).

License
-------

React/Promise is released under the [MIT](https://github.com/reactphp/promise/blob/master/LICENSE) license.
{
    "name": "react/promise",
    "description": "A lightweight implementation of CommonJS Promises/A for PHP",
    "license": "MIT",
    "authors": [
        {"name": "Jan Sorgalla", "email": "jsorgalla@googlemail.com"}
    ],
    "require": {
        "php": ">=5.3.3"
    },
    "autoload": {
        "psr-0": {
            "React\\Promise": "src/"
        }
    },
    "extra": {
        "branch-alias": {
            "dev-master": "1.0-dev"
        }
    }
}
<?php

$loader = require __DIR__.'/../vendor/autoload.php';
$loader->add('React\Promise', __DIR__);
<?php

namespace React\Promise;

/**
 * @group When
 * @group WhenAll
 */
class WhenAllTest extends TestCase
{
    /** @test */
    public function shouldResolveEmptyInput()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array()));

        When::all(array(), $mock);
    }

    /** @test */
    public function shouldResolveValuesArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(1, 2, 3)));

        When::all(
            array(1, 2, 3),
            $mock
        );
    }

    /** @test */
    public function shouldResolvePromisesArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(1, 2, 3)));

        When::all(
            array(When::resolve(1), When::resolve(2), When::resolve(3)),
            $mock
        );
    }

    /** @test */
    public function shouldResolveSparseArrayInput()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(null, 1, null, 1, 1)));

        When::all(
            array(null, 1, null, 1, 1),
            $mock
        );
    }

    /** @test */
    public function shouldRejectIfAnyInputPromiseRejects()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        When::all(
            array(When::resolve(1), When::reject(2), When::resolve(3)),
            $this->expectCallableNever(),
            $mock
        );
    }

    /** @test */
    public function shouldAcceptAPromiseForAnArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(1, 2, 3)));

        When::all(
            When::resolve(array(1, 2, 3)),
            $mock
        );
    }

    /** @test */
    public function shouldResolveToEmptyArrayWhenInputPromiseDoesNotResolveToArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array()));

        When::all(
            When::resolve(1),
            $mock
        );
    }
}
<?php

namespace React\Promise;

/**
 * @group When
 * @group WhenReject
 */
class WhenRejectTest extends TestCase
{
    /** @test */
    public function shouldRejectAnImmediateValue()
    {
        $expected = 123;

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        When::reject($expected)
            ->then(
                $this->expectCallableNever(),
                $mock
            );
    }

    /** @test */
    public function shouldRejectAResolvedPromise()
    {
        $expected = 123;

        $d = new Deferred();
        $d->resolve($expected);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        When::reject($d->promise())
            ->then(
                $this->expectCallableNever(),
                $mock
            );
    }

    /** @test */
    public function shouldRejectARejectedPromise()
    {
        $expected = 123;

        $d = new Deferred();
        $d->reject($expected);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        When::reject($d->promise())
            ->then(
                $this->expectCallableNever(),
                $mock
            );
    }
}
<?php

namespace React\Promise;

/**
 * @group Promise
 * @group LazyPromise
 */
class LazyPromiseTest extends TestCase
{
    /** @test */
    public function shouldNotCallFactoryIfThenIsNotInvoked()
    {
        $factory = $this->createCallableMock();
        $factory
            ->expects($this->never())
            ->method('__invoke');

        new LazyPromise($factory);
    }

    /** @test */
    public function shouldCallFactoryIfThenIsInvoked()
    {
        $factory = $this->createCallableMock();
        $factory
            ->expects($this->once())
            ->method('__invoke');

        $p = new LazyPromise($factory);
        $p->then();
    }

    /** @test */
    public function shouldReturnPromiseFromFactory()
    {
        $factory = $this->createCallableMock();
        $factory
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue(new FulfilledPromise(1)));

        $fulfilledHandler = $this->createCallableMock();
        $fulfilledHandler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $p = new LazyPromise($factory);

        $p->then($fulfilledHandler);
    }

    /** @test */
    public function shouldReturnPromiseIfFactoryReturnsNull()
    {
        $factory = $this->createCallableMock();
        $factory
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue(null));

        $p = new LazyPromise($factory);
        $this->assertInstanceOf('React\\Promise\\PromiseInterface', $p->then());
    }
    
    /** @test */
    public function shouldReturnRejectedPromiseIfFactoryThrowsException()
    {
        $exception = new \Exception();
        
        $factory = $this->createCallableMock();
        $factory
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException($exception));

        $errorHandler = $this->createCallableMock();
        $errorHandler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        $p = new LazyPromise($factory);

        $p->then($this->expectCallableNever(), $errorHandler);
    }
}
<?php

namespace React\Promise;

class ErrorCollector
{
    private $errors = array();

    public function register()
    {
        $errors = array();

        set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext) use (&$errors) {
            $errors[] = compact('errno', 'errstr', 'errfile', 'errline', 'errcontext');
        });

        $this->errors = &$errors;
    }

    public function unregister()
    {
        $this->errors = array();
        restore_error_handler();
    }

    public function assertCollectedError($errstr, $errno)
    {
        foreach ($this->errors as $error) {
            if ($error['errstr'] === $errstr && $error['errno'] === $errno) {
                return;
            }
        }

        $message = 'Error with level ' . $errno . ' and message "' . $errstr . '" not found in ' . var_export($this->errors, true);

        throw new \PHPUnit_Framework_AssertionFailedError($message);
    }
}
<?php

namespace React\Promise;

/**
 * @group When
 * @group WhenReduce
 */
class WhenReduceTest extends TestCase
{
    protected function plus()
    {
        return function ($sum, $val) {
            return $sum + $val;
        };
    }

    protected function append()
    {
        return function ($sum, $val) {
            return $sum . $val;
        };
    }

    /** @test */
    public function shouldReduceValuesWithoutInitialValue()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(6));

        When::reduce(
            array(1, 2, 3),
            $this->plus()
        )->then($mock);
    }

    /** @test */
    public function shouldReduceValuesWithInitialValue()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(7));

        When::reduce(
            array(1, 2, 3),
            $this->plus(),
            1
        )->then($mock);
    }

    /** @test */
    public function shouldReduceValuesWithInitialPromise()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(7));

        When::reduce(
            array(1, 2, 3),
            $this->plus(),
            When::resolve(1)
        )->then($mock);
    }

    /** @test */
    public function shouldReducePromisedValuesWithoutInitialValue()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(6));

        When::reduce(
            array(When::resolve(1), When::resolve(2), When::resolve(3)),
            $this->plus()
        )->then($mock);
    }

    /** @test */
    public function shouldReducePromisedValuesWithInitialValue()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(7));

        When::reduce(
            array(When::resolve(1), When::resolve(2), When::resolve(3)),
            $this->plus(),
            1
        )->then($mock);
    }

    /** @test */
    public function shouldReducePromisedValuesWithInitialPromise()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(7));

        When::reduce(
            array(When::resolve(1), When::resolve(2), When::resolve(3)),
            $this->plus(),
            When::resolve(1)
        )->then($mock);
    }

    /** @test */
    public function shouldReduceEmptyInputWithInitialValue()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        When::reduce(
            array(),
            $this->plus(),
            1
        )->then($mock);
    }

    /** @test */
    public function shouldReduceEmptyInputWithInitialPromise()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        When::reduce(
            array(),
            $this->plus(),
            When::resolve(1)
        )->then($mock);
    }

    /** @test */
    public function shouldRejectWhenInputContainsRejection()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        When::reduce(
            array(When::resolve(1), When::reject(2), When::resolve(3)),
            $this->plus(),
            When::resolve(1)
        )->then($this->expectCallableNever(), $mock);
    }

    /** @test */
    public function shouldResolveWithNullWhenInputIsEmptyAndNoInitialValueOrPromiseProvided()
    {
        // Note: this is different from when.js's behavior!
        // In when.reduce(), this rejects with a TypeError exception (following
        // JavaScript's [].reduce behavior.
        // We're following PHP's array_reduce behavior and resolve with NULL.
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(null));

        When::reduce(
            array(),
            $this->plus()
        )->then($mock);
    }

    /** @test */
    public function shouldAllowSparseArrayInputWithoutInitialValue()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(3));

        When::reduce(
            array(null, null, 1, null, 1, 1),
            $this->plus()
        )->then($mock);
    }

    /** @test */
    public function shouldAllowSparseArrayInputWithInitialValue()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(4));

        When::reduce(
            array(null, null, 1, null, 1, 1),
            $this->plus(),
            1
        )->then($mock);
    }

    /** @test */
    public function shouldReduceInInputOrder()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo('123'));

        When::reduce(
            array(1, 2, 3),
            $this->append(),
            ''
        )->then($mock);
    }

    /** @test */
    public function shouldAcceptAPromiseForAnArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo('123'));

        When::reduce(
            When::resolve(array(1, 2, 3)),
            $this->append(),
            ''
        )->then($mock);
    }

    /** @test */
    public function shouldResolveToInitialValueWhenInputPromiseDoesNotResolveToAnArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        When::reduce(
            When::resolve(1),
            $this->plus(),
            1
        )->then($mock);
    }

    /** @test */
    public function shouldProvideCorrectBasisValue()
    {
        $insertIntoArray = function ($arr, $val, $i) {
            $arr[$i] = $val;

            return $arr;
        };

        $d1 = new Deferred();
        $d2 = new Deferred();
        $d3 = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(1, 2, 3)));

        When::reduce(
            array($d1->promise(), $d2->promise(), $d3->promise()),
            $insertIntoArray,
            array()
        )->then($mock);

        $d3->resolve(3);
        $d1->resolve(1);
        $d2->resolve(2);
    }
}
<?php

namespace React\Promise;

/**
 * @group When
 * @group WhenSome
 */
class WhenSomeTest extends TestCase
{
    /** @test */
    public function shouldResolveEmptyInput()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array()));

        When::some(array(), 1, $mock);
    }

    /** @test */
    public function shouldResolveValuesArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(1, 2)));

        When::some(
            array(1, 2, 3),
            2,
            $mock
        );
    }

    /** @test */
    public function shouldResolvePromisesArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(1, 2)));

        When::some(
            array(When::resolve(1), When::resolve(2), When::resolve(3)),
            2,
            $mock
        );
    }

    /** @test */
    public function shouldResolveSparseArrayInput()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(null, 1)));

        When::some(
            array(null, 1, null, 2, 3),
            2,
            $mock
        );
    }

    /** @test */
    public function shouldRejectIfAnyInputPromiseRejectsBeforeDesiredNumberOfInputsAreResolved()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(1 => 2, 2 => 3)));

        When::some(
            array(When::resolve(1), When::reject(2), When::reject(3)),
            2,
            $this->expectCallableNever(),
            $mock
        );
    }

    /** @test */
    public function shouldAcceptAPromiseForAnArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(1, 2)));

        When::some(
            When::resolve(array(1, 2, 3)),
            2,
            $mock
        );
    }

    /** @test */
    public function shouldResolveToEmptyArrayWhenInputPromiseDoesNotResolveToArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array()));

        When::some(
            When::resolve(1),
            1,
            $mock
        );
    }
}
<?php

namespace React\Promise;

/**
 * @group Deferred
 */
class DeferredTest extends TestCase
{
    /** @test */
    public function shouldReturnAPromiseForPassedInResolutionValueWhenAlreadyResolved()
    {
        $d = new Deferred();
        $d->resolve(1);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $d->resolve(2)->then($mock);
    }

    /** @test */
    public function shouldReturnAPromiseForPassedInRejectionValueWhenAlreadyResolved()
    {
        $d = new Deferred();
        $d->resolve(1);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $d->reject(2)->then($this->expectCallableNever(), $mock);
    }

    /** @test */
    public function shouldReturnSilentlyOnProgressWhenAlreadyResolved()
    {
        $d = new Deferred();
        $d->resolve(1);

        $this->assertNull($d->progress());
    }

    /** @test */
    public function shouldReturnAPromiseForPassedInResolutionValueWhenAlreadyRejected()
    {
        $d = new Deferred();
        $d->reject(1);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $d->resolve(2)->then($mock);
    }

    /** @test */
    public function shouldReturnAPromiseForPassedInRejectionValueWhenAlreadyRejected()
    {
        $d = new Deferred();
        $d->reject(1);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $d->reject(2)->then($this->expectCallableNever(), $mock);
    }

    /** @test */
    public function shouldReturnSilentlyOnProgressWhenAlreadyRejected()
    {
        $d = new Deferred();
        $d->reject(1);

        $this->assertNull($d->progress());
    }
}
<?php

namespace React\Promise;

/**
 * @group Promise
 * @group RejectedPromise
 */
class RejectedPromiseTest extends TestCase
{
    /** @test */
    public function shouldReturnAPromise()
    {
        $p = new RejectedPromise();
        $this->assertInstanceOf('React\\Promise\\PromiseInterface', $p->then());
    }

    /** @test */
    public function shouldReturnAllowNull()
    {
        $p = new RejectedPromise();
        $this->assertInstanceOf('React\\Promise\\PromiseInterface', $p->then(null, null, null));
    }

    /** @test */
    public function shouldForwardUndefinedRejectionValue()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with(null);

        $p = new RejectedPromise(1);
        $p
            ->then(
                $this->expectCallableNever(),
                function () {
                    // Presence of rejection handler is enough to switch back
                    // to resolve mode, even though it returns undefined.
                    // The ONLY way to propagate a rejection is to re-throw or
                    // return a rejected promise;
                }
            )
            ->then(
                $mock,
                $this->expectCallableNever()
            );
    }

    /** @test */
    public function shouldSwitchFromErrbacksToCallbacksWhenErrbackDoesNotExplicitlyPropagate()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $p = new RejectedPromise(1);
        $p
            ->then(
                $this->expectCallableNever(),
                function ($val) {
                    return $val + 1;
                }
            )
            ->then(
                $mock,
                $this->expectCallableNever()
            );
    }

    /** @test */
    public function shouldSwitchFromErrbacksToCallbacksWhenErrbackReturnsAResolution()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $p = new RejectedPromise(1);
        $p
            ->then(
                $this->expectCallableNever(),
                function ($val) {
                    return new FulfilledPromise($val + 1);
                }
            )
            ->then(
                $mock,
                $this->expectCallableNever()
            );
    }

    /** @test */
    public function shouldPropagateRejectionsWhenErrbackThrows()
    {
        $exception = new \Exception();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException($exception));

        $mock2 = $this->createCallableMock();
        $mock2
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        $p = new RejectedPromise(1);
        $p
            ->then(
                $this->expectCallableNever(),
                $mock
            )
            ->then(
                $this->expectCallableNever(),
                $mock2
            );
    }

    /** @test */
    public function shouldPropagateRejectionsWhenErrbackReturnsARejection()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $p = new RejectedPromise(1);
        $p
            ->then(
                $this->expectCallableNever(),
                function ($val) {
                    return new RejectedPromise($val + 1);
                }
            )
            ->then(
                $this->expectCallableNever(),
                $mock
            );
    }
}
<?php

namespace React\Promise\Stub;

class CallableStub
{
    public function __invoke()
    {
    }
}
<?php

namespace React\Promise;

/**
 * @group Promise
 * @group DeferredPromise
 */
class DeferredPromiseTest extends TestCase
{
    /** @test */
    public function shouldForwardToDeferred()
    {
        $mock = $this->getMock('React\\Promise\\Deferred');
        $mock
            ->expects($this->once())
            ->method('then')
            ->with(1, 2, 3);

        $p = new DeferredPromise($mock);
        $p->then(1, 2, 3);
    }
}
<?php

namespace React\Promise;

/**
 * @group Deferred
 * @group DeferredReject
 */
class DeferredRejectTest extends TestCase
{
    /** @test */
    public function shouldRejectWithAnImmediateValue()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d
            ->promise()
            ->then($this->expectCallableNever(), $mock);

        $d
            ->resolver()
            ->reject(1);
    }

    /** @test */
    public function shouldRejectWithFulfilledPromise()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d
            ->promise()
            ->then($this->expectCallableNever(), $mock);

        $d
            ->resolver()
            ->reject(new FulfilledPromise(1));
    }

    /** @test */
    public function shouldRejectWithRejectedPromise()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d
            ->promise()
            ->then($this->expectCallableNever(), $mock);

        $d
            ->resolver()
            ->reject(new RejectedPromise(1));
    }

    /** @test */
    public function shouldReturnAPromiseForTheRejectionValue()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d
            ->resolver()
            ->reject(1)
            ->then($this->expectCallableNever(), $mock);
    }

    /** @test */
    public function shouldInvokeNewlyAddedErrbackWhenAlreadyRejected()
    {
        $d = new Deferred();
        $d
            ->resolver()
            ->reject(1);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d
            ->promise()
            ->then($this->expectCallableNever(), $mock);
    }

    /** @test */
    public function shouldForwardReasonWhenCallbackIsNull()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d = new Deferred();
        $d
            ->then(
                $this->expectCallableNever()
            )
            ->then(
                $this->expectCallableNever(),
                $mock
            );

        $d->reject(1);
    }

    /**
     * @test
     * @dataProvider invalidCallbackDataProvider
     **/
    public function shouldIgnoreNonFunctionsAndTriggerPhpNotice($var)
    {
        $errorCollector = new ErrorCollector();
        $errorCollector->register();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d = new Deferred();
        $d
            ->then(
                null,
                $var
            )
            ->then(
                $this->expectCallableNever(),
                $mock
            );

        $d->reject(1);

        $errorCollector->assertCollectedError('Invalid $errorHandler argument passed to then(), must be null or callable.', E_USER_NOTICE);
        $errorCollector->unregister();
    }
}
<?php

namespace React\Promise;

/**
 * @group When
 * @group WhenMap
 */
class WhenMapTest extends TestCase
{
    protected function mapper()
    {
        return function ($val) {
            return $val * 2;
        };
    }

    protected function promiseMapper()
    {
        return function ($val) {
            return When::resolve($val * 2);
        };
    }

    /** @test */
    public function shouldMapInputValuesArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(2, 4, 6)));

        When::map(
            array(1, 2, 3),
            $this->mapper()
        )->then($mock);
    }

    /** @test */
    public function shouldMapInputPromisesArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(2, 4, 6)));

        When::map(
            array(When::resolve(1), When::resolve(2), When::resolve(3)),
            $this->mapper()
        )->then($mock);
    }

    /** @test */
    public function shouldMapMixedInputArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(2, 4, 6)));

        When::map(
            array(1, When::resolve(2), 3),
            $this->mapper()
        )->then($mock);
    }

    /** @test */
    public function shouldMapInputWhenMapperReturnsAPromise()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(2, 4, 6)));

        When::map(
            array(1, 2, 3),
            $this->promiseMapper()
        )->then($mock);
    }

    /** @test */
    public function shouldAcceptAPromiseForAnArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(2, 4, 6)));

        When::map(
            When::resolve(array(1, When::resolve(2), 3)),
            $this->mapper()
        )->then($mock);
    }

    /** @test */
    public function shouldResolveToEmptyArrayWhenInputPromiseDoesNotResolveToArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array()));

        When::map(
            When::resolve(1),
            $this->mapper()
        )->then($mock);
    }

    /** @test */
    public function shouldRejectWhenInputContainsRejection()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        When::map(
            array(When::resolve(1), When::reject(2), When::resolve(3)),
            $this->mapper()
        )->then($this->expectCallableNever(), $mock);
    }
}
<?php

namespace React\Promise;

/**
 * @group Util
 * @group UtilPromiseFor
 */
class UtilPromiseForTest extends TestCase
{
    /** @test */
    public function shouldResolveAnImmediateValue()
    {
        $expected = 123;

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        Util::promiseFor($expected)
            ->then(
                $mock,
                $this->expectCallableNever()
            );
    }

    /** @test */
    public function shouldResolveAFulfilledPromise()
    {
        $expected = 123;

        $resolved = new FulfilledPromise($expected);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        Util::promiseFor($resolved)
            ->then(
                $mock,
                $this->expectCallableNever()
            );
    }

    /** @test */
    public function shouldRejectARejectedPromise()
    {
        $expected = 123;

        $resolved = new RejectedPromise($expected);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        Util::promiseFor($resolved)
            ->then(
                $this->expectCallableNever(),
                $mock
            );
    }
}
<?php

namespace React\Promise;

/**
 * @group When
 * @group WhenAny
 */
class WhenAnyTest extends TestCase
{
    /** @test */
    public function shouldResolveToNullWithEmptyInputArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(null));

        When::any(array(), $mock);
    }

    /** @test */
    public function shouldResolveWithAnInputValue()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        When::any(
            array(1, 2, 3),
            $mock
        );
    }

    /** @test */
    public function shouldResolveWithAPromisedInputValue()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        When::any(
            array(When::resolve(1), When::resolve(2), When::resolve(3)),
            $mock
        );
    }

    /** @test */
    public function shouldRejectWithAllRejectedInputValuesIfAllInputsAreRejected()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(array(0 => 1, 1 => 2, 2 => 3)));

        When::any(
            array(When::reject(1), When::reject(2), When::reject(3)),
            $this->expectCallableNever(),
            $mock
        );
    }

    /** @test */
    public function shouldResolveWhenFirstInputPromiseResolves()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        When::any(
            array(When::resolve(1), When::reject(2), When::reject(3)),
            $mock
        );
    }

    /** @test */
    public function shouldAcceptAPromiseForAnArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        When::any(
            When::resolve(array(1, 2, 3)),
            $mock
        );
    }

    /** @test */
    public function shouldResolveToNullArrayWhenInputPromiseDoesNotResolveToArray()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(null));

        When::any(
            When::resolve(1),
            $mock
        );
    }

    /** @test */
    public function shouldNotRelyOnArryIndexesWhenUnwrappingToASingleResolutionValue()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $d1 = new Deferred();
        $d2 = new Deferred();

        When::any(
            array('abc' => $d1->promise(), 1 => $d2->promise()),
            $mock
        );

        $d2->resolve(2);
        $d1->resolve(1);
    }
}
<?php

namespace React\Promise;

/**
 * @group Deferred
 * @group DeferredProgress
 */
class DeferredProgressTest extends TestCase
{
    /** @test */
    public function shouldProgress()
    {
        $sentinel = new \stdClass();

        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($sentinel);

        $d
            ->promise()
            ->then($this->expectCallableNever(), $this->expectCallableNever(), $mock);

        $d
            ->resolver()
            ->progress($sentinel);
    }

    /** @test */
    public function shouldPropagateProgressToDownstreamPromises()
    {
        $sentinel = new \stdClass();

        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->returnArgument(0));

        $mock2 = $this->createCallableMock();
        $mock2
            ->expects($this->once())
            ->method('__invoke')
            ->with($sentinel);

        $d
            ->promise()
            ->then(
                $this->expectCallableNever(),
                $this->expectCallableNever(),
                $mock
            )
            ->then(
                $this->expectCallableNever(),
                $this->expectCallableNever(),
                $mock2
            );

        $d
            ->resolver()
            ->progress($sentinel);
    }

    /** @test */
    public function shouldPropagateTransformedProgressToDownstreamPromises()
    {
        $sentinel = new \stdClass();

        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($sentinel));

        $mock2 = $this->createCallableMock();
        $mock2
            ->expects($this->once())
            ->method('__invoke')
            ->with($sentinel);

        $d
            ->promise()
            ->then(
                $this->expectCallableNever(),
                $this->expectCallableNever(),
                $mock
            )
            ->then(
                $this->expectCallableNever(),
                $this->expectCallableNever(),
                $mock2
            );

        $d
            ->resolver()
            ->progress(1);
    }

    /** @test */
    public function shouldPropagateCaughtExceptionValueAsProgress()
    {
        $exception = new \Exception();

        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException($exception));

        $mock2 = $this->createCallableMock();
        $mock2
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        $d
            ->promise()
            ->then(
                $this->expectCallableNever(),
                $this->expectCallableNever(),
                $mock
            )
            ->then(
                $this->expectCallableNever(),
                $this->expectCallableNever(),
                $mock2
            );

        $d
            ->resolver()
            ->progress(1);
    }

    /** @test */
    public function shouldForwardProgressEventsWhenIntermediaryCallbackTiedToAResolvedPromiseReturnsAPromise()
    {
        $sentinel = new \stdClass();

        $d = new Deferred();
        $d2 = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($sentinel);

        // resolve $d BEFORE calling attaching progress handler
        $d
            ->resolver()
            ->resolve();

        $d
            ->promise()
            ->then(function () use ($d2) {
                return $d2->promise();
            })
            ->then(
                $this->expectCallableNever(),
                $this->expectCallableNever(),
                $mock
            );

        $d2
            ->resolver()
            ->progress($sentinel);
    }

    /** @test */
    public function shouldForwardProgressEventsWhenIntermediaryCallbackTiedToAnUnresolvedPromiseReturnsAPromise()
    {
        $sentinel = new \stdClass();

        $d = new Deferred();
        $d2 = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($sentinel);

        $d
            ->promise()
            ->then(function () use ($d2) {
                return $d2->promise();
            })
            ->then(
                $this->expectCallableNever(),
                $this->expectCallableNever(),
                $mock
            );

        // resolve $d AFTER calling attaching progress handler
        $d
            ->resolver()
            ->resolve();
        $d2
            ->resolver()
            ->progress($sentinel);
    }

    /** @test */
    public function shouldForwardProgressWhenResolvedWithAnotherPromise()
    {
        $sentinel = new \stdClass();

        $d = new Deferred();
        $d2 = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($sentinel));

        $mock2 = $this->createCallableMock();
        $mock2
            ->expects($this->once())
            ->method('__invoke')
            ->with($sentinel);

        $d
            ->promise()
            ->then(
                $this->expectCallableNever(),
                $this->expectCallableNever(),
                $mock
            )
            ->then(
                $this->expectCallableNever(),
                $this->expectCallableNever(),
                $mock2
            );

        $d
            ->resolver()
            ->resolve($d2->promise());
        $d2
            ->resolver()
            ->progress($sentinel);
    }

    /** @test */
    public function shouldAllowResolveAfterProgress()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->at(0))
            ->method('__invoke')
            ->with($this->identicalTo(1));
        $mock
            ->expects($this->at(1))
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $d
            ->promise()
            ->then(
                $mock,
                $this->expectCallableNever(),
                $mock
            );

        $d
            ->resolver()
            ->progress(1);
        $d
            ->resolver()
            ->resolve(2);
    }

    /** @test */
    public function shouldAllowRejectAfterProgress()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->at(0))
            ->method('__invoke')
            ->with($this->identicalTo(1));
        $mock
            ->expects($this->at(1))
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $d
            ->promise()
            ->then(
                $this->expectCallableNever(),
                $mock,
                $mock
            );

        $d
            ->resolver()
            ->progress(1);
        $d
            ->resolver()
            ->reject(2);
    }

    /**
     * @test
     * @dataProvider invalidCallbackDataProvider
     **/
    public function shouldIgnoreNonFunctionsAndTriggerPhpNotice($var)
    {
        $errorCollector = new ErrorCollector();
        $errorCollector->register();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d = new Deferred();
        $d
            ->then(
                null,
                null,
                $var
            )
            ->then(
                $this->expectCallableNever(),
                $this->expectCallableNever(),
                $mock
            );

        $d->progress(1);

        $errorCollector->assertCollectedError('Invalid $progressHandler argument passed to then(), must be null or callable.', E_USER_NOTICE);
        $errorCollector->unregister();
    }
}
<?php

namespace React\Promise;

/**
 * @group Deferred
 * @group DeferredResolve
 */
class DeferredResolveTest extends TestCase
{
    /** @test */
    public function shouldResolve()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d
            ->promise()
            ->then($mock);

        $d
            ->resolver()
            ->resolve(1);
    }

    /** @test */
    public function shouldResolveWithPromisedValue()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d
            ->promise()
            ->then($mock);

        $d
            ->resolver()
            ->resolve(new FulfilledPromise(1));
    }

    /** @test */
    public function shouldRejectWhenResolvedWithRejectedPromise()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d
            ->promise()
            ->then($this->expectCallableNever(), $mock);

        $d
            ->resolver()
            ->resolve(new RejectedPromise(1));
    }

    /** @test */
    public function shouldReturnAPromiseForTheResolutionValue()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d
            ->resolver()
            ->resolve(1)
            ->then($mock);
    }

    /** @test */
    public function shouldReturnAPromiseForAPromisedResolutionValue()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d
            ->resolver()
            ->resolve(When::resolve(1))
            ->then($mock);
    }

    /** @test */
    public function shouldReturnAPromiseForAPromisedRejectionValue()
    {
        $d = new Deferred();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        // Both the returned promise, and the deferred's own promise should
        // be rejected with the same value
        $d
            ->resolver()
            ->resolve(When::reject(1))
            ->then($this->expectCallableNever(), $mock);
    }

    /** @test */
    public function shouldInvokeNewlyAddedCallbackWhenAlreadyResolved()
    {
        $d = new Deferred();
        $d
            ->resolver()
            ->resolve(1);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d
            ->promise()
            ->then($mock, $this->expectCallableNever());
    }

    /** @test */
    public function shouldForwardValueWhenCallbackIsNull()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d = new Deferred();
        $d
            ->then(
                null,
                $this->expectCallableNever()
            )
            ->then(
                $mock,
                $this->expectCallableNever()
            );

        $d->resolve(1);
    }

    /**
     * @test
     * @dataProvider invalidCallbackDataProvider
     **/
    public function shouldIgnoreNonFunctionsAndTriggerPhpNotice($var)
    {
        $errorCollector = new ErrorCollector();
        $errorCollector->register();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $d = new Deferred();
        $d
            ->then(
                $var
            )
            ->then(
                $mock,
                $this->expectCallableNever()
            );

        $d->resolve(1);

        $errorCollector->assertCollectedError('Invalid $fulfilledHandler argument passed to then(), must be null or callable.', E_USER_NOTICE);
        $errorCollector->unregister();
    }
}
<?php

namespace React\Promise;

/**
 * @group Resolver
 * @group DeferredResolver
 */
class DeferredResolverTest extends TestCase
{
    /** @test */
    public function shouldForwardToDeferred()
    {
        $mock = $this->getMock('React\\Promise\\Deferred');
        $mock
            ->expects($this->once())
            ->method('resolve')
            ->with(1);
        $mock
            ->expects($this->once())
            ->method('reject')
            ->with(1);
        $mock
            ->expects($this->once())
            ->method('progress')
            ->with(1);

        $p = new DeferredResolver($mock);
        $p->resolve(1);
        $p->reject(1);
        $p->progress(1);
    }
}
<?php

namespace React\Promise;

/**
 * @group Util
 * @group UtilRejectedPromiseFor
 */
class UtilRejectedPromiseForTest extends TestCase
{
    /** @test */
    public function shouldRejectWithAnImmediateValue()
    {
        $expected = 123;

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        Util::rejectedPromiseFor($expected)
            ->then(
                $this->expectCallableNever(),
                $mock
            );
    }

    /** @test */
    public function shouldRejectWithFulfilledPromise()
    {
        $expected = 123;

        $resolved = new FulfilledPromise($expected);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        Util::rejectedPromiseFor($resolved)
            ->then(
                $this->expectCallableNever(),
                $mock
            );
    }

    /** @test */
    public function shouldRejectWithRejectedPromise()
    {
        $expected = 123;

        $resolved = new RejectedPromise($expected);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        Util::rejectedPromiseFor($resolved)
            ->then(
                $this->expectCallableNever(),
                $mock
            );
    }
}
<?php

namespace React\Promise;

/**
 * @group When
 * @group WhenResolve
 */
class WhenResolveTest extends TestCase
{
    /** @test */
    public function shouldResolveAnImmediateValue()
    {
        $expected = 123;

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        When::resolve($expected)
            ->then(
                $mock,
                $this->expectCallableNever()
            );
    }

    /** @test */
    public function shouldResolveAResolvedPromise()
    {
        $expected = 123;

        $d = new Deferred();
        $d->resolve($expected);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        When::resolve($d->promise())
            ->then(
                $mock,
                $this->expectCallableNever()
            );
    }

    /** @test */
    public function shouldRejectARejectedPromise()
    {
        $expected = 123;

        $d = new Deferred();
        $d->reject($expected);

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($expected));

        When::resolve($d->promise())
            ->then(
                $this->expectCallableNever(),
                $mock
            );
    }

    /** @test */
    public function shouldSupportDeepNestingInPromiseChains()
    {
        $d = new Deferred();
        $d->resolve(false);

        $result = When::resolve(When::resolve($d->then(function ($val) {
            $d = new Deferred();
            $d->resolve($val);

            $identity = function ($val) {
                return $val;
            };

            return When::resolve($d->then($identity))->then(
                function ($val) {
                    return !$val;
                }
            );
        })));

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(true));

        $result->then($mock);
    }
}
<?php

namespace React\Promise;

/**
 * @group Promise
 * @group FulfilledPromise
 */
class FulfilledPromiseTest extends TestCase
{
    /** @test */
    public function shouldReturnAPromise()
    {
        $p = new FulfilledPromise();
        $this->assertInstanceOf('React\\Promise\\PromiseInterface', $p->then());
    }

    /** @test */
    public function shouldReturnAllowNull()
    {
        $p = new FulfilledPromise();
        $this->assertInstanceOf('React\\Promise\\PromiseInterface', $p->then(null, null, null));
    }

    /** @test */
    public function shouldForwardResultWhenCallbackIsNull()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(1));

        $p = new FulfilledPromise(1);
        $p
            ->then(
                null,
                $this->expectCallableNever()
            )
            ->then(
                $mock,
                $this->expectCallableNever()
            );
    }

    /** @test */
    public function shouldForwardCallbackResultToNextCallback()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $p = new FulfilledPromise(1);
        $p
            ->then(
                function ($val) {
                    return $val + 1;
                },
                $this->expectCallableNever()
            )
            ->then(
                $mock,
                $this->expectCallableNever()
            );
    }

    /** @test */
    public function shouldForwardPromisedCallbackResultValueToNextCallback()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $p = new FulfilledPromise(1);
        $p
            ->then(
                function ($val) {
                    return new FulfilledPromise($val + 1);
                },
                $this->expectCallableNever()
            )
            ->then(
                $mock,
                $this->expectCallableNever()
            );
    }

    /** @test */
    public function shouldSwitchFromCallbacksToErrbacksWhenCallbackReturnsARejection()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo(2));

        $p = new FulfilledPromise(1);
        $p
            ->then(
                function ($val) {
                    return new RejectedPromise($val + 1);
                },
                $this->expectCallableNever()
            )
            ->then(
                $this->expectCallableNever(),
                $mock
            );
    }

    /** @test */
    public function shouldSwitchFromCallbacksToErrbacksWhenCallbackThrows()
    {
        $exception = new \Exception();

        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException($exception));

        $mock2 = $this->createCallableMock();
        $mock2
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        $p = new FulfilledPromise(1);
        $p
            ->then(
                $mock,
                $this->expectCallableNever()
            )
            ->then(
                $this->expectCallableNever(),
                $mock2
            );
    }
}
<?php

namespace React\Promise;

/**
 * @group When
 * @group WhenLazy
 */
class WhenLazyTest extends TestCase
{
    /** @test */
    public function shouldReturnALazyPromise()
    {
        $this->assertInstanceOf('React\\Promise\\PromiseInterface',  When::lazy(function () {}));
    }

    /** @test */
    public function shouldCallFactoryIfThenIsInvoked()
    {
        $factory = $this->createCallableMock();
        $factory
            ->expects($this->once())
            ->method('__invoke');

        When::lazy($factory)
            ->then();
    }
}
<?php

namespace React\Promise;

class TestCase extends \PHPUnit_Framework_TestCase
{
    public function expectCallableExactly($amount)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->exactly($amount))
            ->method('__invoke');

        return $mock;
    }

    public function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    public function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    public function createCallableMock()
    {
        return $this->getMock('React\\Promise\Stub\CallableStub');
    }

    public function invalidCallbackDataProvider()
    {
        return array(
            'empty string' => array(''),
            'true'         => array(true),
            'false'        => array(false),
            'object'       => array(new \stdClass),
            'truthy'       => array(1),
            'falsey'       => array(0)
        );
    }
}
<?xml version="1.0" encoding="UTF-8"?>

<phpunit backupGlobals="false"
         backupStaticAttributes="false"
         colors="true"
         convertErrorsToExceptions="true"
         convertNoticesToExceptions="false"
         convertWarningsToExceptions="true"
         processIsolation="false"
         stopOnFailure="false"
         syntaxCheck="false"
         bootstrap="tests/bootstrap.php"
>
    <testsuites>
        <testsuite name="Promise Test Suite">
            <directory>./tests/React/Promise/</directory>
        </testsuite>
    </testsuites>

    <filter>
        <whitelist>
            <directory>./src/</directory>
        </whitelist>
    </filter>
</phpunit>
CHANGELOG
=========

* 1.0.4 (2013-04-03)

  * Trigger PHP errors when invalid callback is passed.
  * Fully resolve rejection value before calling rejection handler.
  * Add When::lazy() to create lazy promises which will be initialized once a
    consumer calls the then() method.

* 1.0.3 (2012-11-17)

  * Add `PromisorInterface` for objects that have a `promise()` method.

* 1.0.2 (2012-11-14)

  * Fix bug in When::any() not correctly unwrapping to a single result value
  * $promiseOrValue argument of When::resolve() and When::reject() is now optional

* 1.0.1 (2012-11-13)

  * Prevent deep recursion which was reaching `xdebug.max_nesting_level` default of 100

* 1.0.0 (2012-11-07)

  * First tagged release
<?php

namespace React\EventLoop;

use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\EventLoop\Timer\Timers;

class StreamSelectLoop implements LoopInterface
{
    const QUANTUM_INTERVAL = 1000000;

    private $timers;
    private $running = false;
    private $readStreams = array();
    private $readListeners = array();
    private $writeStreams = array();
    private $writeListeners = array();

    public function __construct()
    {
        $this->timers = new Timers();
    }

    public function addReadStream($stream, $listener)
    {
        $id = (int) $stream;

        if (!isset($this->readStreams[$id])) {
            $this->readStreams[$id] = $stream;
            $this->readListeners[$id] = $listener;
        }
    }

    public function addWriteStream($stream, $listener)
    {
        $id = (int) $stream;

        if (!isset($this->writeStreams[$id])) {
            $this->writeStreams[$id] = $stream;
            $this->writeListeners[$id] = $listener;
        }
    }

    public function removeReadStream($stream)
    {
        $id = (int) $stream;

        unset(
            $this->readStreams[$id],
            $this->readListeners[$id]
        );
    }

    public function removeWriteStream($stream)
    {
        $id = (int) $stream;

        unset(
            $this->writeStreams[$id],
            $this->writeListeners[$id]
        );
    }

    public function removeStream($stream)
    {
        $this->removeReadStream($stream);
        $this->removeWriteStream($stream);
    }

    public function addTimer($interval, $callback)
    {
        $timer = new Timer($this, $interval, $callback, false);
        $this->timers->add($timer);

        return $timer;
    }

    public function addPeriodicTimer($interval, $callback)
    {
        $timer = new Timer($this, $interval, $callback, true);
        $this->timers->add($timer);

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer)
    {
        $this->timers->cancel($timer);
    }

    public function isTimerActive(TimerInterface $timer)
    {
        return $this->timers->contains($timer);
    }

    protected function getNextEventTimeInMicroSeconds()
    {
        $nextEvent = $this->timers->getFirst();

        if (null === $nextEvent) {
            return self::QUANTUM_INTERVAL;
        }

        $currentTime = microtime(true);
        if ($nextEvent > $currentTime) {
            return ($nextEvent - $currentTime) * 1000000;
        }

        return 0;
    }

    protected function sleepOnPendingTimers()
    {
        if ($this->timers->isEmpty()) {
            $this->running = false;
        } else {
            // We use usleep() instead of stream_select() to emulate timeouts
            // since the latter fails when there are no streams registered for
            // read / write events. Blame PHP for us needing this hack.
            usleep($this->getNextEventTimeInMicroSeconds());
        }
    }

    protected function runStreamSelect()
    {
        $read = $this->readStreams ?: null;
        $write = $this->writeStreams ?: null;
        $except = null;

        if (!$read && !$write) {
            $this->sleepOnPendingTimers();

            return;
        }

        if (stream_select($read, $write, $except, 0, $this->getNextEventTimeInMicroSeconds()) > 0) {
            if ($read) {
                foreach ($read as $stream) {
                    $listener = $this->readListeners[(int) $stream];
                    call_user_func($listener, $stream, $this);
                }
            }

            if ($write) {
                foreach ($write as $stream) {
                    if (!isset($this->writeListeners[(int) $stream])) {
                        continue;
                    }

                    $listener = $this->writeListeners[(int) $stream];
                    call_user_func($listener, $stream, $this);
                }
            }
        }
    }

    public function tick()
    {
        $this->timers->tick();
        $this->runStreamSelect();

        return $this->running;
    }

    public function run()
    {
        $this->running = true;

        while ($this->tick()) {
            // NOOP
        }
    }

    public function stop()
    {
        $this->running = false;
    }
}
# EventLoop Component

Event loop abstraction layer that libraries can use for evented I/O.

In order for async based libraries to be interoperable, they need to use the
same event loop. This component provides a common `LoopInterface` that any
library can target. This allows them to be used in the same loop, with one
single `run` call that is controlled by the user.

In addition to the interface there are some implementations provided:

* `StreamSelectLoop`: This is the only implementation which works out of the
  box with PHP. It does a simple `select` system call. It's not the most
  performant of loops, but still does the job quite well.

* `LibEventLoop`: This uses the `libevent` pecl extension. `libevent` itself
  supports a number of system-specific backends (epoll, kqueue).

* `LibEvLoop`: This uses the `libev` pecl extension
  ([github](https://github.com/m4rw3r/php-libev)). It supports the same
  backends as libevent.

All of the loops support these features:

* File descriptor polling
* One-off timers
* Periodic timers

## Usage

Here is an async HTTP server built with just the event loop.

    $loop = React\EventLoop\Factory::create();

    $server = stream_socket_server('tcp://127.0.0.1:8080');
    stream_set_blocking($server, 0);
    $loop->addReadStream($server, function ($server) use ($loop) {
        $conn = stream_socket_accept($server);
        $data = "HTTP/1.1 200 OK\r\nContent-Length: 3\r\n\r\nHi\n";
        $loop->addWriteStream($conn, function ($conn) use (&$data, $loop) {
            $written = fwrite($conn, $data);
            if ($written === strlen($data)) {
                fclose($conn);
                $loop->removeStream($conn);
            } else {
                $data = substr($data, 0, $written);
            }
        });
    });

    $loop->addPeriodicTimer(5, function () {
        $memory = memory_get_usage() / 1024;
        $formatted = number_format($memory, 3).'K';
        echo "Current memory usage: {$formatted}\n";
    });

    $loop->run();

**Note:** The factory is just for convenience. It tries to pick the best
available implementation. Libraries `SHOULD` allow the user to inject an
instance of the loop. They `MAY` use the factory when the user did not supply
a loop.
{
    "name": "react/event-loop",
    "description": "Event loop abstraction layer that libraries can use for evented I/O.",
    "keywords": ["event-loop"],
    "license": "MIT",
    "require": {
        "php": ">=5.3.3"
    },
    "suggest": {
        "ext-libevent": ">=0.0.5",
        "ext-libev": "*"
    },
    "autoload": {
        "psr-0": { "React\\EventLoop": "" }
    },
    "target-dir": "React/EventLoop",
    "extra": {
        "branch-alias": {
            "dev-master": "0.3-dev"
        }
    }
}
<?php

namespace React\EventLoop;

use React\EventLoop\StreamSelectLoop;
use React\EventLoop\LibEventLoop;

class Factory
{
    public static function create()
    {
        // @codeCoverageIgnoreStart
        if (function_exists('event_base_new')) {
            return new LibEventLoop();
        }

        return new StreamSelectLoop();
        // @codeCoverageIgnoreEnd
    }
}
<?php

namespace React\EventLoop;

use SplObjectStorage;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;

class LibEventLoop implements LoopInterface
{
    const MIN_TIMER_RESOLUTION = 0.001;

    private $base;
    private $callback;
    private $timers;

    private $events = array();
    private $flags = array();
    private $readCallbacks = array();
    private $writeCallbacks = array();

    public function __construct()
    {
        $this->base = event_base_new();
        $this->callback = $this->createLibeventCallback();
        $this->timers = new SplObjectStorage();
    }

    protected function createLibeventCallback()
    {
        $readCallbacks = &$this->readCallbacks;
        $writeCallbacks = &$this->writeCallbacks;

        return function ($stream, $flags, $loop) use (&$readCallbacks, &$writeCallbacks) {
            $id = (int) $stream;

            try {
                if (($flags & EV_READ) === EV_READ && isset($readCallbacks[$id])) {
                    call_user_func($readCallbacks[$id], $stream, $loop);
                }

                if (($flags & EV_WRITE) === EV_WRITE && isset($writeCallbacks[$id])) {
                    call_user_func($writeCallbacks[$id], $stream, $loop);
                }
            } catch (\Exception $ex) {
                // If one of the callbacks throws an exception we must stop the loop
                // otherwise libevent will swallow the exception and go berserk.
                $loop->stop();

                throw $ex;
            }
        };
    }

    public function addReadStream($stream, $listener)
    {
        $this->addStreamEvent($stream, EV_READ, 'read', $listener);
    }

    public function addWriteStream($stream, $listener)
    {
        $this->addStreamEvent($stream, EV_WRITE, 'write', $listener);
    }

    protected function addStreamEvent($stream, $eventClass, $type, $listener)
    {
        $id = (int) $stream;

        if ($existing = isset($this->events[$id])) {
            if (($this->flags[$id] & $eventClass) === $eventClass) {
                return;
            }
            $event = $this->events[$id];
            event_del($event);
        } else {
            $event = event_new();
        }

        $flags = isset($this->flags[$id]) ? $this->flags[$id] | $eventClass : $eventClass;
        event_set($event, $stream, $flags | EV_PERSIST, $this->callback, $this);

        if (!$existing) {
            // Set the base only if $event has been newly created or be ready for segfaults.
            event_base_set($event, $this->base);
        }

        event_add($event);

        $this->events[$id] = $event;
        $this->flags[$id] = $flags;
        $this->{"{$type}Callbacks"}[$id] = $listener;
    }

    public function removeReadStream($stream)
    {
        $this->removeStreamEvent($stream, EV_READ, 'read');
    }

    public function removeWriteStream($stream)
    {
        $this->removeStreamEvent($stream, EV_WRITE, 'write');
    }

    protected function removeStreamEvent($stream, $eventClass, $type)
    {
        $id = (int) $stream;

        if (isset($this->events[$id])) {
            $flags = $this->flags[$id] & ~$eventClass;

            if ($flags === 0) {
                // Remove if stream is not subscribed to any event at this point.
                return $this->removeStream($stream);
            }

            $event = $this->events[$id];

            event_del($event);
            event_free($event);
            unset($this->{"{$type}Callbacks"}[$id]);

            $event = event_new();
            event_set($event, $stream, $flags | EV_PERSIST, $this->callback, $this);
            event_base_set($event, $this->base);
            event_add($event);

            $this->events[$id] = $event;
            $this->flags[$id] = $flags;
        }
    }

    public function removeStream($stream)
    {
        $id = (int) $stream;

        if (isset($this->events[$id])) {
            $event = $this->events[$id];

            unset(
                $this->events[$id],
                $this->flags[$id],
                $this->readCallbacks[$id],
                $this->writeCallbacks[$id]
            );

            event_del($event);
            event_free($event);
        }
    }

    protected function addTimerInternal($interval, $callback, $periodic = false)
    {
        if ($interval < self::MIN_TIMER_RESOLUTION) {
            throw new \InvalidArgumentException('Timer events do not support sub-millisecond timeouts.');
        }

        $timer = new Timer($this, $interval, $callback, $periodic);
        $resource = event_new();

        $timers = $this->timers;
        $timers->attach($timer, $resource);

        $callback = function () use ($timers, $timer, &$callback) {
            if (isset($timers[$timer])) {
                call_user_func($timer->getCallback(), $timer);

                if ($timer->isPeriodic() && isset($timers[$timer])) {
                    event_add($timers[$timer], $timer->getInterval() * 1000000);
                } else {
                    $timer->cancel();
                }
            }
        };

        event_timer_set($resource, $callback);
        event_base_set($resource, $this->base);
        event_add($resource, $interval * 1000000);

        return $timer;
    }

    public function addTimer($interval, $callback)
    {
        return $this->addTimerInternal($interval, $callback);
    }

    public function addPeriodicTimer($interval, $callback)
    {
        return $this->addTimerInternal($interval, $callback, true);
    }

    public function cancelTimer(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            $resource = $this->timers[$timer];
            event_del($resource);
            event_free($resource);

            $this->timers->detach($timer);
        }
    }

    public function isTimerActive(TimerInterface $timer)
    {
        return $this->timers->contains($timer);
    }

    public function tick()
    {
        event_base_loop($this->base, EVLOOP_ONCE | EVLOOP_NONBLOCK);
    }

    public function run()
    {
        event_base_loop($this->base);
    }

    public function stop()
    {
        event_base_loopexit($this->base);
    }
}
<?php

namespace React\EventLoop;

use SplObjectStorage;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;

/**
 * @see https://github.com/m4rw3r/php-libev
 * @see https://gist.github.com/1688204
 */
class LibEvLoop implements LoopInterface
{
    private $loop;
    private $timers;
    private $readEvents = array();
    private $writeEvents = array();

    public function __construct()
    {
        $this->loop = new \libev\EventLoop();
        $this->timers = new SplObjectStorage();
    }

    public function addReadStream($stream, $listener)
    {
        $this->addStream($stream, $listener, \libev\IOEvent::READ);
    }

    public function addWriteStream($stream, $listener)
    {
        $this->addStream($stream, $listener, \libev\IOEvent::WRITE);
    }

    public function removeReadStream($stream)
    {
        $this->readEvents[(int)$stream]->stop();
        unset($this->readEvents[(int)$stream]);
    }

    public function removeWriteStream($stream)
    {
        $this->writeEvents[(int)$stream]->stop();
        unset($this->writeEvents[(int)$stream]);
    }

    public function removeStream($stream)
    {
        if (isset($this->readEvents[(int)$stream])) {
            $this->removeReadStream($stream);
        }

        if (isset($this->writeEvents[(int)$stream])) {
            $this->removeWriteStream($stream);
        }
    }

    private function addStream($stream, $listener, $flags)
    {
        $listener = $this->wrapStreamListener($stream, $listener, $flags);
        $event = new \libev\IOEvent($listener, $stream, $flags);
        $this->loop->add($event);

        if (($flags & \libev\IOEvent::READ) === $flags) {
            $this->readEvents[(int)$stream] = $event;
        } elseif (($flags & \libev\IOEvent::WRITE) === $flags) {
            $this->writeEvents[(int)$stream] = $event;
        }
    }

    private function wrapStreamListener($stream, $listener, $flags)
    {
        if (($flags & \libev\IOEvent::READ) === $flags) {
            $removeCallback = array($this, 'removeReadStream');
        } elseif (($flags & \libev\IOEvent::WRITE) === $flags) {
            $removeCallback = array($this, 'removeWriteStream');
        }

        return function ($event) use ($stream, $listener, $removeCallback) {
            if (feof($stream)) {
                call_user_func($removeCallback, $stream);

                return;
            }

            call_user_func($listener, $stream);
        };
    }

    public function addTimer($interval, $callback)
    {
        $timer = new Timer($this, $interval, $callback, false);
        $this->setupTimer($timer);

        return $timer;
    }

    public function addPeriodicTimer($interval, $callback)
    {
        $timer = new Timer($this, $interval, $callback, true);
        $this->setupTimer($timer);

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            $this->loop->remove($this->timers[$timer]);
            $this->timers->detach($timer);
        }
    }

    private function setupTimer(TimerInterface $timer)
    {
        $dummyCallback = function () {};
        $interval = $timer->getInterval();

        if ($timer->isPeriodic()) {
            $libevTimer = new \libev\TimerEvent($dummyCallback, $interval, $interval);
        } else {
            $libevTimer = new \libev\TimerEvent($dummyCallback, $interval);
        }

        $libevTimer->setCallback(function () use ($timer) {
            call_user_func($timer->getCallback(), $timer);

            if (!$timer->isPeriodic()) {
                $timer->cancel();
            }
        });

        $this->timers->attach($timer, $libevTimer);
        $this->loop->add($libevTimer);

        return $timer;
    }

    public function isTimerActive(TimerInterface $timer)
    {
        return $this->timers->contains($timer);
    }

    public function tick()
    {
        $this->loop->run(\libev\EventLoop::RUN_ONCE);
    }

    public function run()
    {
        $this->loop->run();
    }

    public function stop()
    {
        $this->loop->breakLoop();
    }
}
<?php

namespace React\EventLoop\Timer;

use SplObjectStorage;
use SplPriorityQueue;
use InvalidArgumentException;

class Timers
{
    const MIN_RESOLUTION = 0.001;

    private $time;
    private $timers;
    private $scheduler;

    public function __construct()
    {
        $this->timers = new SplObjectStorage();
        $this->scheduler = new SplPriorityQueue();
    }

    public function updateTime()
    {
        return $this->time = microtime(true);
    }

    public function getTime()
    {
        return $this->time ?: $this->updateTime();
    }

    public function add(TimerInterface $timer)
    {
        $interval = $timer->getInterval();

        if ($interval < self::MIN_RESOLUTION) {
            throw new InvalidArgumentException('Timer events do not support sub-millisecond timeouts.');
        }

        $scheduledAt = $interval + $this->getTime();

        $this->timers->attach($timer, $scheduledAt);
        $this->scheduler->insert($timer, -$scheduledAt);
    }

    public function contains(TimerInterface $timer)
    {
        return $this->timers->contains($timer);
    }

    public function cancel(TimerInterface $timer)
    {
        $this->timers->detach($timer);
    }

    public function getFirst()
    {
        if ($this->scheduler->isEmpty()) {
            return null;
        }

        $scheduledAt = $this->timers[$this->scheduler->top()];

        return $scheduledAt;
    }

    public function isEmpty()
    {
        return count($this->timers) === 0;
    }

    public function tick()
    {
        $time = $this->updateTime();
        $timers = $this->timers;
        $scheduler = $this->scheduler;

        while (!$scheduler->isEmpty()) {
            $timer = $scheduler->top();

            if (!isset($timers[$timer])) {
                $scheduler->extract();
                $timers->detach($timer);

                continue;
            }

            if ($timers[$timer] >= $time) {
                break;
            }

            $scheduler->extract();
            call_user_func($timer->getCallback(), $timer);

            if ($timer->isPeriodic() && isset($timers[$timer])) {
                $timers[$timer] = $scheduledAt = $timer->getInterval() + $time;
                $scheduler->insert($timer, -$scheduledAt);
            } else {
                $timers->detach($timer);
            }
        }
    }
}
<?php

namespace React\EventLoop\Timer;

use InvalidArgumentException;
use React\EventLoop\LoopInterface;

class Timer implements TimerInterface
{
    protected $loop;
    protected $interval;
    protected $callback;
    protected $periodic;
    protected $data;

    public function __construct(LoopInterface $loop, $interval, $callback, $periodic = false, $data = null)
    {
        if (false === is_callable($callback)) {
            throw new InvalidArgumentException('The callback argument must be a valid callable object');
        }

        $this->loop = $loop;
        $this->interval = (float) $interval;
        $this->callback = $callback;
        $this->periodic = (bool) $periodic;
        $this->data = null;
    }

    public function getLoop()
    {
        return $this->loop;
    }

    public function getInterval()
    {
        return $this->interval;
    }

    public function getCallback()
    {
        return $this->callback;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function isPeriodic()
    {
        return $this->periodic;
    }

    public function isActive()
    {
        return $this->loop->isTimerActive($this);
    }

    public function cancel()
    {
        $this->loop->cancelTimer($this);
    }
}
<?php

namespace React\EventLoop\Timer;

interface TimerInterface
{
    public function getLoop();
    public function getInterval();
    public function getCallback();
    public function setData($data);
    public function getData();
    public function isPeriodic();
    public function isActive();
    public function cancel();
}
<?php

namespace React\EventLoop;

use React\EventLoop\Timer\TimerInterface;

interface LoopInterface
{
    public function addReadStream($stream, $listener);
    public function addWriteStream($stream, $listener);

    public function removeReadStream($stream);
    public function removeWriteStream($stream);
    public function removeStream($stream);

    public function addTimer($interval, $callback);
    public function addPeriodicTimer($interval, $callback);
    public function cancelTimer(TimerInterface $timer);
    public function isTimerActive(TimerInterface $timer);

    public function tick();
    public function run();
    public function stop();
}
<?php

namespace React\Cache;

interface CacheInterface
{
    // @return React\Promise\PromiseInterface
    public function get($key);

    public function set($key, $value);

    public function remove($key);
}
<?php

namespace React\Cache;

use React\Promise\When;

class ArrayCache implements CacheInterface
{
    private $data = array();

    public function get($key)
    {
        if (!isset($this->data[$key])) {
            return When::reject();
        }

        return When::resolve($this->data[$key]);
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function remove($key)
    {
        unset($this->data[$key]);
    }
}
# Cache Component

Promised cache interface.

The cache component provides a promise-based cache interface and an in-memory
`ArrayCache` implementation of that. This allows consumers to type hint
against the interface and third parties to provide alternate implementations.

## Basic usage

### get

    $cache
        ->get('foo')
        ->then('var_dump');

This example fetches the value of the key `foo` and passes it to the
`var_dump` function. You can use any of the composition provided by
[promises](https://github.com/reactphp/promise).

If the key `foo` does not exist, the promise will be rejected.

### set

    $cache->set('foo', 'bar');

This example eventually sets the value of the key `foo` to `bar`. If it
already exists, it is overridden. No guarantees are made as to when the cache
value is set. If the cache implementation has to go over the network to store
it, it may take a while.

### remove

    $cache->remove('foo');

This example eventually removes the key `foo` from the cache. As with `set`,
this may not happen instantly.

## Common usage

### Fallback get

A common use case of caches is to attempt fetching a cached value and as a
fallback retrieve it from the original data source if not found. Here is an
example of that:

    $cache
        ->get('foo')
        ->then(null, 'getFooFromDb')
        ->then('var_dump');

First an attempt is made to retrieve the value of `foo`. A promise rejection
handler of the function `getFooFromDb` is registered. `getFooFromDb` is a
function (can be any PHP callable) that will be called if the key does not
exist in the cache.

`getFooFromDb` can handle the missing key by returning a promise for the
actual value from the database (or any other data source). As a result, this
chain will correctly fall back, and provide the value in both cases.

### Fallback get and set

To expand on the fallback get example, often you want to set the value on the
cache after fetching it from the data source.

    $cache
        ->get('foo')
        ->then(null, array($this, 'getAndCacheFooFromDb'))
        ->then('var_dump');

    public function getAndCacheFooFromDb()
    {
        return $this->db
            ->get('foo')
            ->then(array($this, 'cacheFooFromDb'));
    }

    public function cacheFooFromDb($foo)
    {
        $this->cache->set('foo', $foo);

        return $foo;
    }

By using chaining you can easily conditionally cache the value if it is
fetched from the database.
{
    "name": "react/cache",
    "description": "Async caching.",
    "keywords": ["cache"],
    "license": "MIT",
    "require": {
        "php": ">=5.3.2",
        "react/promise": "~1.0"
    },
    "autoload": {
        "psr-0": { "React\\Cache": "" }
    },
    "target-dir": "React/Cache",
    "extra": {
        "branch-alias": {
            "dev-master": "0.3-dev"
        }
    }
}
<?php

namespace React\Stream;

use Evenement\EventEmitter;

class WritableStream extends EventEmitter implements WritableStreamInterface
{
    protected $closed = false;

    public function write($data)
    {
    }

    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        $this->close();
    }

    public function isWritable()
    {
        return !$this->closed;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->emit('end', array($this));
        $this->emit('close', array($this));
        $this->removeAllListeners();
    }
}
<?php

namespace React\Stream;

use Evenement\EventEmitter;

class CompositeStream extends EventEmitter implements ReadableStreamInterface, WritableStreamInterface
{
    protected $readable;
    protected $writable;
    protected $pipeSource;

    public function __construct(ReadableStreamInterface $readable, WritableStreamInterface $writable)
    {
        $this->readable = $readable;
        $this->writable = $writable;

        Util::forwardEvents($this->readable, $this, array('data', 'end', 'error', 'close'));
        Util::forwardEvents($this->writable, $this, array('drain', 'error', 'close', 'pipe'));

        $this->readable->on('close', array($this, 'close'));
        $this->writable->on('close', array($this, 'close'));

        $this->on('pipe', array($this, 'handlePipeEvent'));
    }

    public function handlePipeEvent($source)
    {
        $this->pipeSource = $source;
    }

    public function isReadable()
    {
        return $this->readable->isReadable();
    }

    public function pause()
    {
        if ($this->pipeSource) {
            $this->pipeSource->pause();
        }

        $this->readable->pause();
    }

    public function resume()
    {
        if ($this->pipeSource) {
            $this->pipeSource->resume();
        }

        $this->readable->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function isWritable()
    {
        return $this->writable->isWritable();
    }

    public function write($data)
    {
        return $this->writable->write($data);
    }

    public function end($data = null)
    {
        $this->writable->end($data);
    }

    public function close()
    {
        $this->pipeSource = true;

        $this->readable->close();
        $this->writable->close();
    }
}
<?php

namespace React\Stream;

use Evenement\EventEmitter;

class ReadableStream extends EventEmitter implements ReadableStreamInterface
{
    protected $closed = false;

    public function isReadable()
    {
        return !$this->closed;
    }

    public function pause()
    {
    }

    public function resume()
    {
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->emit('end', array($this));
        $this->emit('close', array($this));
        $this->removeAllListeners();
    }
}
<?php

namespace React\Stream;

class ThroughStream extends CompositeStream
{
    public function __construct()
    {
        $readable = new ReadableStream();
        $writable = new WritableStream();

        parent::__construct($readable, $writable);
    }

    public function filter($data)
    {
        return $data;
    }

    public function write($data)
    {
        $this->readable->emit('data', array($this->filter($data)));
    }

    public function end($data = null)
    {
        if (null !== $data) {
            $this->readable->emit('data', array($this->filter($data)));
        }

        $this->writable->end($data);
    }
}
# Stream Component

Basic readable and writable stream interfaces that support piping.

In order to make the event loop easier to use, this component introduces the
concept of streams. They are very similar to the streams found in PHP itself,
but have an interface more suited for async I/O.

Mainly it provides interfaces for readable and writable streams, plus a file
descriptor based implementation with an in-memory write buffer.

This component depends on `événement`, which is an implementation of the
`EventEmitter`.

## Readable Streams

### EventEmitter Events

* `data`: Emitted whenever data was read from the source.
* `end`: Emitted when the source has reached the `eof`.
* `error`: Emitted when an error occurs.
* `close`: Emitted when the connection is closed.

### Methods

* `isReadable()`: Check if the stream is still in a state allowing it to be
  read from. It becomes unreadable when the connection ends, closes or an
  error occurs.
* `pause()`: Remove the data source file descriptor from the event loop. This
  allows you to throttle incoming data.
* `resume()`: Re-attach the data source after a `pause()`.
* `pipe(WritableStreamInterface $dest, array $options = [])`: Pipe this
  readable stream into a writable stream. Automatically sends all incoming
  data to the destination. Automatically throttles based on what the
  destination can handle.

## Writable Streams

### EventEmitter Events

* `drain`: Emitted if the write buffer became full previously and is now ready
  to accept more data.
* `error`: Emitted whenever an error occurs.
* `close`: Emitted whenever the connection is closed.
* `pipe`: Emitted whenever a readable stream is `pipe()`d into this stream.

### Methods

* `isWritable()`: Check if the stream can still be written to. It cannot be
  written to after an error or when it is closed.
* `write($data)`: Write some data into the stream. If the stream cannot handle
  it, it should buffer the data or emit and `error` event. If the internal
  buffer is full after adding `$data`, `write` should return false, indicating
  that the caller should stop sending data until the buffer `drain`s.
* `end($data = null)`: Optionally write some final data to the stream, empty
  the buffer, then close it.

## Usage

    $loop = React\EventLoop\Factory::create();

    $source = new React\Stream\Stream(fopen('omg.txt', 'r'), $loop);
    $dest = new React\Stream\Stream(fopen('wtf.txt', 'w'), $loop);

    $source->pipe($dest);

    $loop->run();
<?php

namespace React\Stream;

use React\Promise\Deferred;
use React\Promise\PromisorInterface;
use React\Stream\WritableStream;

class BufferedSink extends WritableStream implements PromisorInterface
{
    private $buffer = '';
    private $deferred;

    public function __construct()
    {
        $this->deferred = new Deferred();

        $this->on('pipe', array($this, 'handlePipeEvent'));
        $this->on('error', array($this, 'handleErrorEvent'));
    }

    public function handlePipeEvent($source)
    {
        Util::forwardEvents($source, $this, array('error'));
    }

    public function handleErrorEvent($e)
    {
        $this->deferred->reject($e);
    }

    public function write($data)
    {
        $this->buffer .= $data;
        $this->deferred->progress($data);
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        parent::close();
        $this->deferred->resolve($this->buffer);
    }

    public function promise()
    {
        return $this->deferred->promise();
    }

    public static function createPromise(ReadableStream $stream)
    {
        $sink = new static();
        $stream->pipe($sink);

        return $sink->promise();
    }
}
{
    "name": "react/stream",
    "description": "Basic readable and writable stream interfaces that support piping.",
    "keywords": ["stream", "pipe"],
    "license": "MIT",
    "require": {
        "php": ">=5.3.3",
        "evenement/evenement": "1.0.*"
    },
    "suggest": {
        "react/event-loop": "0.3.*",
        "react/promise": "~1.0"
    },
    "autoload": {
        "psr-0": { "React\\Stream": "" }
    },
    "target-dir": "React/Stream",
    "extra": {
        "branch-alias": {
            "dev-master": "0.3-dev"
        }
    }
}
<?php

namespace React\Stream;

// TODO: move to a trait

class Util
{
    public static function pipe(ReadableStreamInterface $source, WritableStreamInterface $dest, array $options = array())
    {
        // TODO: use stream_copy_to_stream
        // it is 4x faster than this
        // but can lose data under load with no way to recover it

        $dest->emit('pipe', array($source));

        $source->on('data', function ($data) use ($source, $dest) {
            $feedMore = $dest->write($data);

            if (false === $feedMore) {
                $source->pause();
            }
        });

        $dest->on('drain', function () use ($source) {
            $source->resume();
        });

        $end = isset($options['end']) ? $options['end'] : true;
        if ($end && $source !== $dest) {
            $source->on('end', function () use ($dest) {
                $dest->end();
            });
        }
    }

    public static function forwardEvents($source, $target, array $events)
    {
        foreach ($events as $event) {
            $source->on($event, function () use ($event, $target) {
                $target->emit($event, func_get_args());
            });
        }
    }
}
<?php

namespace React\Stream;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Stream\WritableStreamInterface;

/** @event full-drain */
class Buffer extends EventEmitter implements WritableStreamInterface
{
    public $stream;
    public $listening = false;
    public $softLimit = 2048;
    private $writable = true;
    private $loop;
    private $data = '';
    private $lastError = array(
        'number'  => 0,
        'message' => '',
        'file'    => '',
        'line'    => 0,
    );

    public function __construct($stream, LoopInterface $loop)
    {
        $this->stream = $stream;
        $this->loop = $loop;
    }

    public function isWritable()
    {
        return $this->writable;
    }

    public function write($data)
    {
        if (!$this->writable) {
            return;
        }

        $this->data .= $data;

        if (!$this->listening) {
            $this->listening = true;

            $this->loop->addWriteStream($this->stream, array($this, 'handleWrite'));
        }

        $belowSoftLimit = strlen($this->data) < $this->softLimit;

        return $belowSoftLimit;
    }

    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        $this->writable = false;

        if ($this->listening) {
            $this->on('full-drain', array($this, 'close'));
        } else {
            $this->close();
        }
    }

    public function close()
    {
        $this->writable = false;
        $this->listening = false;
        $this->data = '';

        $this->emit('close');
    }

    public function handleWrite()
    {
        if (!is_resource($this->stream) || feof($this->stream)) {
            $this->emit('error', array(new \RuntimeException('Tried to write to closed or invalid stream.')));

            return;
        }

        set_error_handler(array($this, 'errorHandler'));

        $sent = fwrite($this->stream, $this->data);

        restore_error_handler();

        if (false === $sent) {
            $this->emit('error', array(new \ErrorException(
                $this->lastError['message'],
                0,
                $this->lastError['number'],
                $this->lastError['file'],
                $this->lastError['line']
            )));

            return;
        }

        $len = strlen($this->data);
        if ($len >= $this->softLimit && $len - $sent < $this->softLimit) {
            $this->emit('drain');
        }

        $this->data = (string) substr($this->data, $sent);

        if (0 === strlen($this->data)) {
            $this->loop->removeWriteStream($this->stream);
            $this->listening = false;

            $this->emit('full-drain');
        }
    }

    private function errorHandler($errno, $errstr, $errfile, $errline)
    {
        $this->lastError['number']  = $errno;
        $this->lastError['message'] = $errstr;
        $this->lastError['file']    = $errfile;
        $this->lastError['line']    = $errline;
    }
}
<?php

namespace React\Stream;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

class Stream extends EventEmitter implements ReadableStreamInterface, WritableStreamInterface
{
    public $bufferSize = 4096;
    public $stream;
    protected $readable = true;
    protected $writable = true;
    protected $closing = false;
    protected $loop;
    protected $buffer;

    public function __construct($stream, LoopInterface $loop)
    {
        $this->stream = $stream;
        $this->loop = $loop;
        $this->buffer = new Buffer($this->stream, $this->loop);

        $that = $this;

        $this->buffer->on('error', function ($error) use ($that) {
            $that->emit('error', array($error, $that));
            $that->close();
        });

        $this->buffer->on('drain', function () use ($that) {
            $that->emit('drain');
        });

        $this->resume();
    }

    public function isReadable()
    {
        return $this->readable;
    }

    public function isWritable()
    {
        return $this->writable;
    }

    public function pause()
    {
        $this->loop->removeReadStream($this->stream);
    }

    public function resume()
    {
        $this->loop->addReadStream($this->stream, array($this, 'handleData'));
    }

    public function write($data)
    {
        if (!$this->writable) {
            return;
        }

        return $this->buffer->write($data);
    }

    public function close()
    {
        if (!$this->writable && !$this->closing) {
            return;
        }

        $this->closing = false;

        $this->readable = false;
        $this->writable = false;

        $this->emit('end', array($this));
        $this->emit('close', array($this));
        $this->loop->removeStream($this->stream);
        $this->buffer->removeAllListeners();
        $this->removeAllListeners();

        $this->handleClose();
    }

    public function end($data = null)
    {
        if (!$this->writable) {
            return;
        }

        $this->closing = true;

        $this->readable = false;
        $this->writable = false;

        $that = $this;

        $this->buffer->on('close', function () use ($that) {
            $that->close();
        });

        $this->buffer->end($data);
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function handleData($stream)
    {
        $data = fread($stream, $this->bufferSize);

        $this->emit('data', array($data, $this));

        if (!is_resource($stream) || feof($stream)) {
            $this->end();
        }
    }

    public function handleClose()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function getBuffer()
    {
        return $this->buffer;
    }
}
<?php

namespace React\Stream;

use Evenement\EventEmitterInterface;

/**
 * @event data
 * @event end
 * @event error
 * @event close
 */
interface ReadableStreamInterface extends StreamInterface
{
    public function isReadable();
    public function pause();
    public function resume();
    public function pipe(WritableStreamInterface $dest, array $options = array());
}
<?php

namespace React\Stream;

use Evenement\EventEmitterInterface;

/**
 * @event drain
 * @event error
 * @event close
 * @event pipe
 */
interface WritableStreamInterface extends StreamInterface
{
    public function isWritable();
    public function write($data);
    public function end($data = null);
}
<?php

namespace React\Stream;

use Evenement\EventEmitterInterface;

// This class exists because ReadableStreamInterface and WritableStreamInterface
//  both need close methods.
// In PHP <= 5.3.8 a class can not implement 2 interfaces with coincidental matching methods
interface StreamInterface extends EventEmitterInterface
{
    public function close();
}
<?php

namespace React\Dns\Config;

class Config
{
    public $nameservers = array();
}
<?php

namespace React\Dns\Config;

use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\When;
use React\Stream\Stream;

class FilesystemFactory
{
    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function create($filename)
    {
        return $this
            ->loadEtcResolvConf($filename)
            ->then(array($this, 'parseEtcResolvConf'));
    }

    public function parseEtcResolvConf($contents)
    {
        $nameservers = array();

        $contents = preg_replace('/^#/', '', $contents);
        $lines = preg_split('/\r?\n/is', $contents);
        foreach ($lines as $line) {
            if (preg_match('/^nameserver (.+)/', $line, $match)) {
                $nameservers[] = $match[1];
            }
        }

        $config = new Config();
        $config->nameservers = $nameservers;

        return When::resolve($config);
    }

    public function loadEtcResolvConf($filename)
    {
        if (!file_exists($filename)) {
            return When::reject(new \InvalidArgumentException("The filename for /etc/resolv.conf given does not exist: $filename"));
        }

        try {
            $deferred = new Deferred();

            $fd = fopen($filename, 'r');
            stream_set_blocking($fd, 0);

            $contents = '';

            $stream = new Stream($fd, $this->loop);
            $stream->on('data', function ($data) use (&$contents) {
                $contents .= $data;
            });
            $stream->on('end', function () use (&$contents, $deferred) {
                $deferred->resolve($contents);
            });
            $stream->on('error', function ($error) use ($deferred) {
                $deferred->reject($error);
            });

            return $deferred->promise();
        } catch (\Exception $e) {
            return When::reject($e);
        }
    }
}
<?php

namespace React\Dns\Query;

class TimeoutException extends \Exception
{
}
<?php

namespace React\Dns\Query;

use React\Dns\Model\Message;
use React\Dns\Model\Record;

class RecordBag
{
    private $records = array();

    public function set($currentTime, Record $record)
    {
        $this->records[$record->data] = array($currentTime + $record->ttl, $record);
    }

    public function all()
    {
        return array_values(array_map(
            function ($value) {
                list($expiresAt, $record) = $value;
                return $record;
            },
            $this->records
        ));
    }
}
<?php

namespace React\Dns\Query;

interface ExecutorInterface
{
    public function query($nameserver, Query $query);
}
<?php

namespace React\Dns\Query;

class Query
{
    public $name;
    public $type;
    public $class;
    public $currentTime;

    public function __construct($name, $type, $class, $currentTime)
    {
        $this->name = $name;
        $this->type = $type;
        $this->class = $class;
        $this->currentTime = $currentTime;
    }
}
<?php

namespace React\Dns\Query;

use React\Dns\BadServerException;
use React\Dns\Model\Message;
use React\Dns\Protocol\Parser;
use React\Dns\Protocol\BinaryDumper;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Socket\Connection;

class Executor implements ExecutorInterface
{
    private $loop;
    private $parser;
    private $dumper;
    private $timeout;

    public function __construct(LoopInterface $loop, Parser $parser, BinaryDumper $dumper, $timeout = 5)
    {
        $this->loop = $loop;
        $this->parser = $parser;
        $this->dumper = $dumper;
        $this->timeout = $timeout;
    }

    public function query($nameserver, Query $query)
    {
        $request = $this->prepareRequest($query);

        $queryData = $this->dumper->toBinary($request);
        $transport = strlen($queryData) > 512 ? 'tcp' : 'udp';

        return $this->doQuery($nameserver, $transport, $queryData, $query->name);
    }

    public function prepareRequest(Query $query)
    {
        $request = new Message();
        $request->header->set('id', $this->generateId());
        $request->header->set('rd', 1);
        $request->questions[] = (array) $query;
        $request->prepare();

        return $request;
    }

    public function doQuery($nameserver, $transport, $queryData, $name)
    {
        $that = $this;
        $parser = $this->parser;
        $loop = $this->loop;

        $response = new Message();
        $deferred = new Deferred();

        $retryWithTcp = function () use ($that, $nameserver, $queryData, $name) {
            return $that->doQuery($nameserver, 'tcp', $queryData, $name);
        };

        $timer = $this->loop->addTimer($this->timeout, function () use (&$conn, $name, $deferred) {
            $conn->close();
            $deferred->reject(new TimeoutException(sprintf("DNS query for %s timed out", $name)));
        });

        $conn = $this->createConnection($nameserver, $transport);
        $conn->on('data', function ($data) use ($retryWithTcp, $conn, $parser, $response, $transport, $deferred, $timer) {
            $responseReady = $parser->parseChunk($data, $response);

            if (!$responseReady) {
                return;
            }

            $timer->cancel();

            if ($response->header->isTruncated()) {
                if ('tcp' === $transport) {
                    $deferred->reject(new BadServerException('The server set the truncated bit although we issued a TCP request'));
                } else {
                    $conn->end();
                    $deferred->resolve($retryWithTcp());
                }

                return;
            }

            $conn->end();
            $deferred->resolve($response);
        });
        $conn->write($queryData);

        return $deferred->promise();
    }

    protected function generateId()
    {
        return mt_rand(0, 0xffff);
    }

    protected function createConnection($nameserver, $transport)
    {
        $fd = stream_socket_client("$transport://$nameserver");
        $conn = new Connection($fd, $this->loop);

        return $conn;
    }
}
<?php

namespace React\Dns\Query;

use React\Promise\Deferred;

class RetryExecutor implements ExecutorInterface
{
    private $executor;
    private $retries;

    public function __construct(ExecutorInterface $executor, $retries = 2)
    {
        $this->executor = $executor;
        $this->retries = $retries;
    }

    public function query($nameserver, Query $query)
    {
        $deferred = new Deferred();

        $this->tryQuery($nameserver, $query, $this->retries, $deferred->resolver());

        return $deferred->promise();
    }

    public function tryQuery($nameserver, Query $query, $retries, $resolver)
    {
        $that = $this;
        $errorback = function ($error) use ($nameserver, $query, $retries, $resolver, $that) {
            if (!$error instanceof TimeoutException) {
                $resolver->reject($error);
                return;
            }
            if (0 >= $retries) {
                $error = new \RuntimeException(
                    sprintf("DNS query for %s failed: too many retries", $query->name),
                    0,
                    $error
                );
                $resolver->reject($error);
                return;
            }
            $that->tryQuery($nameserver, $query, $retries-1, $resolver);
        };

        $this->executor
            ->query($nameserver, $query)
            ->then(array($resolver, 'resolve'), $errorback);
    }
}
<?php

namespace React\Dns\Query;

use React\Cache\CacheInterface;
use React\Dns\Model\Message;
use React\Dns\Model\Record;
use React\Promise\When;

class RecordCache
{
    private $cache;
    private $expiredAt;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function lookup(Query $query)
    {
        $id = $this->serializeQueryToIdentity($query);

        $expiredAt = $this->expiredAt;

        return $this->cache
            ->get($id)
            ->then(function ($value) use ($query, $expiredAt) {
                $recordBag = unserialize($value);

                if (null !== $expiredAt && $expiredAt <= $query->currentTime) {
                    return When::reject();
                }

                return $recordBag->all();
            });
    }

    public function storeResponseMessage($currentTime, Message $message)
    {
        foreach ($message->answers as $record) {
            $this->storeRecord($currentTime, $record);
        }
    }

    public function storeRecord($currentTime, Record $record)
    {
        $id = $this->serializeRecordToIdentity($record);

        $cache = $this->cache;

        $this->cache
            ->get($id)
            ->then(
                function ($value) {
                    return unserialize($value);
                },
                function ($e) {
                    return new RecordBag();
                }
            )
            ->then(function ($recordBag) use ($id, $currentTime, $record, $cache) {
                $recordBag->set($currentTime, $record);
                $cache->set($id, serialize($recordBag));
            });
    }

    public function expire($currentTime)
    {
        $this->expiredAt = $currentTime;
    }

    public function serializeQueryToIdentity(Query $query)
    {
        return sprintf('%s:%s:%s', $query->name, $query->type, $query->class);
    }

    public function serializeRecordToIdentity(Record $record)
    {
        return sprintf('%s:%s:%s', $record->name, $record->type, $record->class);
    }
}
<?php

namespace React\Dns\Query;

use React\Dns\Model\Message;
use React\Dns\Model\Record;
use React\Promise\When;

class CachedExecutor implements ExecutorInterface
{
    private $executor;
    private $cache;

    public function __construct(ExecutorInterface $executor, RecordCache $cache)
    {
        $this->executor = $executor;
        $this->cache = $cache;
    }

    public function query($nameserver, Query $query)
    {
        $that = $this;
        $executor = $this->executor;
        $cache = $this->cache;

        return $this->cache
            ->lookup($query)
            ->then(
                function ($cachedRecords) use ($that, $query) {
                    return $that->buildResponse($query, $cachedRecords);
                },
                function () use ($executor, $cache, $nameserver, $query) {
                    return $executor
                        ->query($nameserver, $query)
                        ->then(function ($response) use ($cache, $query) {
                            $cache->storeResponseMessage($query->currentTime, $response);
                            return $response;
                        });
                }
            );
    }

    public function buildResponse(Query $query, array $cachedRecords)
    {
        $response = new Message();

        $response->header->set('id', $this->generateId());
        $response->header->set('qr', 1);
        $response->header->set('opcode', Message::OPCODE_QUERY);
        $response->header->set('rd', 1);
        $response->header->set('rcode', Message::RCODE_OK);

        $response->questions[] = new Record($query->name, $query->type, $query->class);

        foreach ($cachedRecords as $record) {
            $response->answers[] = $record;
        }

        $response->prepare();

        return $response;
    }

    protected function generateId()
    {
        return mt_rand(0, 0xffff);
    }
}
<?php

namespace React\Dns\Protocol;

use React\Dns\Model\Message;
use React\Dns\Model\HeaderBag;

class BinaryDumper
{
    public function toBinary(Message $message)
    {
        $data = '';

        $data .= $this->headerToBinary($message->header);
        $data .= $this->questionToBinary($message->questions);

        return $data;
    }

    private function headerToBinary(HeaderBag $header)
    {
        $data = '';

        $data .= pack('n', $header->get('id'));

        $flags = 0x00;
        $flags = ($flags << 1) | $header->get('qr');
        $flags = ($flags << 4) | $header->get('opcode');
        $flags = ($flags << 1) | $header->get('aa');
        $flags = ($flags << 1) | $header->get('tc');
        $flags = ($flags << 1) | $header->get('rd');
        $flags = ($flags << 1) | $header->get('ra');
        $flags = ($flags << 3) | $header->get('z');
        $flags = ($flags << 4) | $header->get('rcode');

        $data .= pack('n', $flags);

        $data .= pack('n', $header->get('qdCount'));
        $data .= pack('n', $header->get('anCount'));
        $data .= pack('n', $header->get('nsCount'));
        $data .= pack('n', $header->get('arCount'));

        return $data;
    }

    private function questionToBinary(array $questions)
    {
        $data = '';

        foreach ($questions as $question) {
            $labels = explode('.', $question['name']);
            foreach ($labels as $label) {
                $data .= chr(strlen($label)).$label;
            }
            $data .= "\x00";

            $data .= pack('n*', $question['type'], $question['class']);
        }

        return $data;
    }
}
<?php

namespace React\Dns\Protocol;

use React\Dns\Model\Message;
use React\Dns\Model\Record;

/**
 * DNS protocol parser
 *
 * Obsolete and uncommon types and classes are not implemented.
 */
class Parser
{
    public function parseChunk($data, Message $message)
    {
        $message->data .= $data;

        if (!$message->header->get('id')) {
            if (!$this->parseHeader($message)) {
                return;
            }
        }

        if ($message->header->get('qdCount') != count($message->questions)) {
            if (!$this->parseQuestion($message)) {
                return;
            }
        }

        if ($message->header->get('anCount') != count($message->answers)) {
            if (!$this->parseAnswer($message)) {
                return;
            }
        }

        return $message;
    }

    public function parseHeader(Message $message)
    {
        if (strlen($message->data) < 12) {
            return;
        }

        $header = substr($message->data, 0, 12);
        $message->consumed += 12;

        list($id, $fields, $qdCount, $anCount, $nsCount, $arCount) = array_values(unpack('n*', $header));

        $rcode = $fields & bindec('1111');
        $z = ($fields >> 4) & bindec('111');
        $ra = ($fields >> 7) & 1;
        $rd = ($fields >> 8) & 1;
        $tc = ($fields >> 9) & 1;
        $aa = ($fields >> 10) & 1;
        $opcode = ($fields >> 11) & bindec('1111');
        $qr = ($fields >> 15) & 1;

        $vars = compact('id', 'qdCount', 'anCount', 'nsCount', 'arCount',
                            'qr', 'opcode', 'aa', 'tc', 'rd', 'ra', 'z', 'rcode');


        foreach ($vars as $name => $value) {
            $message->header->set($name, $value);
        }

        return $message;
    }

    public function parseQuestion(Message $message)
    {
        if (strlen($message->data) < 2) {
            return;
        }

        $consumed = $message->consumed;

        list($labels, $consumed) = $this->readLabels($message->data, $consumed);

        if (null === $labels) {
            return;
        }

        if (strlen($message->data) - $consumed < 4) {
            return;
        }

        list($type, $class) = array_values(unpack('n*', substr($message->data, $consumed, 4)));
        $consumed += 4;

        $message->consumed = $consumed;

        $message->questions[] = array(
            'name' => implode('.', $labels),
            'type' => $type,
            'class' => $class,
        );

        if ($message->header->get('qdCount') != count($message->questions)) {
            return $this->parseQuestion($message);
        }

        return $message;
    }

    public function parseAnswer(Message $message)
    {
        if (strlen($message->data) < 2) {
            return;
        }

        $consumed = $message->consumed;

        list($labels, $consumed) = $this->readLabels($message->data, $consumed);

        if (null === $labels) {
            return;
        }

        if (strlen($message->data) - $consumed < 10) {
            return;
        }

        list($type, $class) = array_values(unpack('n*', substr($message->data, $consumed, 4)));
        $consumed += 4;

        list($ttl) = array_values(unpack('N', substr($message->data, $consumed, 4)));
        $consumed += 4;

        list($rdLength) = array_values(unpack('n', substr($message->data, $consumed, 2)));
        $consumed += 2;

        $rdata = null;

        if (Message::TYPE_A === $type) {
            $ip = substr($message->data, $consumed, $rdLength);
            $consumed += $rdLength;

            $rdata = inet_ntop($ip);
        }

        if (Message::TYPE_CNAME === $type) {
            list($bodyLabels, $consumed) = $this->readLabels($message->data, $consumed);

            $rdata = implode('.', $bodyLabels);
        }

        $message->consumed = $consumed;

        $name = implode('.', $labels);
        $ttl = $this->signedLongToUnsignedLong($ttl);
        $record = new Record($name, $type, $class, $ttl, $rdata);

        $message->answers[] = $record;

        if ($message->header->get('anCount') != count($message->answers)) {
            return $this->parseAnswer($message);
        }

        return $message;
    }

    private function readLabels($data, $consumed)
    {
        $labels = array();

        while (true) {
            if ($this->isEndOfLabels($data, $consumed)) {
                $consumed += 1;
                break;
            }

            if ($this->isCompressedLabel($data, $consumed)) {
                list($newLabels, $consumed) = $this->getCompressedLabel($data, $consumed);
                $labels = array_merge($labels, $newLabels);
                break;
            }

            $length = ord(substr($data, $consumed, 1));
            $consumed += 1;

            if (strlen($data) - $consumed < $length) {
                return array(null, null);
            }

            $labels[] = substr($data, $consumed, $length);
            $consumed += $length;
        }

        return array($labels, $consumed);
    }

    public function isEndOfLabels($data, $consumed)
    {
        $length = ord(substr($data, $consumed, 1));
        return 0 === $length;
    }

    public function getCompressedLabel($data, $consumed)
    {
        list($nameOffset, $consumed) = $this->getCompressedLabelOffset($data, $consumed);
        list($labels) = $this->readLabels($data, $nameOffset);

        return array($labels, $consumed);
    }

    public function isCompressedLabel($data, $consumed)
    {
        $mask = 0xc000; // 1100000000000000
        list($peek) = array_values(unpack('n', substr($data, $consumed, 2)));

        return (bool) ($peek & $mask);
    }

    public function getCompressedLabelOffset($data, $consumed)
    {
        $mask = 0x3fff; // 0011111111111111
        list($peek) = array_values(unpack('n', substr($data, $consumed, 2)));

        return array($peek & $mask, $consumed + 2);
    }

    public function signedLongToUnsignedLong($i)
    {
        return $i & 0x80000000 ? $i - 0xffffffff : $i;
    }
}
<?php

namespace React\Dns\Model;

class HeaderBag
{
    public $data = '';

    public $attributes = array(
        'qdCount'   => 0,
        'anCount'   => 0,
        'nsCount'   => 0,
        'arCount'   => 0,
        'qr'        => 0,
        'opcode'    => Message::OPCODE_QUERY,
        'aa'        => 0,
        'tc'        => 0,
        'rd'        => 0,
        'ra'        => 0,
        'z'         => 0,
        'rcode'     => Message::RCODE_OK,
    );

    public function get($name)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    public function set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function isQuery()
    {
        return 0 === $this->attributes['qr'];
    }

    public function isResponse()
    {
        return 1 === $this->attributes['qr'];
    }

    public function isTruncated()
    {
        return 1 === $this->attributes['tc'];
    }

    public function populateCounts(Message $message)
    {
        $this->attributes['qdCount'] = count($message->questions);
        $this->attributes['anCount'] = count($message->answers);
        $this->attributes['nsCount'] = count($message->authority);
        $this->attributes['arCount'] = count($message->additional);
    }
}
<?php

namespace React\Dns\Model;

class Message
{
    const TYPE_A = 1;
    const TYPE_NS = 2;
    const TYPE_CNAME = 5;
    const TYPE_SOA = 6;
    const TYPE_PTR = 12;
    const TYPE_MX = 15;
    const TYPE_TXT = 16;

    const CLASS_IN = 1;

    const OPCODE_QUERY = 0;
    const OPCODE_IQUERY = 1; // inverse query
    const OPCODE_STATUS = 2;

    const RCODE_OK = 0;
    const RCODE_FORMAT_ERROR = 1;
    const RCODE_SERVER_FAILURE = 2;
    const RCODE_NAME_ERROR = 3;
    const RCODE_NOT_IMPLEMENTED = 4;
    const RCODE_REFUSED = 5;

    public $data = '';

    public $header;
    public $questions = array();
    public $answers = array();
    public $authority = array();
    public $additional = array();

    public $consumed = 0;

    public function __construct()
    {
        $this->header = new HeaderBag();
    }

    public function prepare()
    {
        $this->header->populateCounts($this);
    }
}
<?php

namespace React\Dns\Model;

class Record
{
    public $name;
    public $type;
    public $class;
    public $ttl;
    public $data;

    public function __construct($name, $type, $class, $ttl = 0, $data = null)
    {
        $this->name     = $name;
        $this->type     = $type;
        $this->class    = $class;
        $this->ttl      = $ttl;
        $this->data     = $data;
    }
}
# Dns Component

Async DNS resolver.

The main point of the DNS component is to provide async DNS resolution.
However, it is really a toolkit for working with DNS messages, and could
easily be used to create a DNS server.

## Basic usage

The most basic usage is to just create a resolver through the resolver
factory. All you need to give it is a nameserver, then you can start resolving
names, baby!

    $loop = React\EventLoop\Factory::create();
    $factory = new React\Dns\Resolver\Factory();
    $dns = $factory->create('8.8.8.8', $loop);

    $dns->resolve('igor.io')->then(function ($ip) {
        echo "Host: $ip\n";
    });

But there's more.

## Caching

You can cache results by configuring the resolver to use a `CachedExecutor`:

    $loop = React\EventLoop\Factory::create();
    $factory = new React\Dns\Resolver\Factory();
    $dns = $factory->createCached('8.8.8.8', $loop);

    $dns->resolve('igor.io')->then(function ($ip) {
        echo "Host: $ip\n";
    });

    ...

    $dns->resolve('igor.io')->then(function ($ip) {
        echo "Host: $ip\n";
    });

If the first call returns before the second, only one query will be executed.
The second result will be served from cache.

## Todo

* Implement message body parsing for types other than A and CNAME: NS, SOA, PTR, MX, TXT, AAAA
* Implement `authority` and `additional` message parts
* Respect /etc/hosts

# References

* [RFC1034](http://tools.ietf.org/html/rfc1034) Domain Names - Concepts and Facilities
* [RFC1035](http://tools.ietf.org/html/rfc1035) Domain Names - Implementation and Specification
{
    "name": "react/dns",
    "description": "Async DNS resolver.",
    "keywords": ["dns", "dns-resolver"],
    "license": "MIT",
    "require": {
        "php": ">=5.3.2",
        "react/cache": "0.3.*",
        "react/socket": "0.3.*",
        "react/promise": "~1.0"
    },
    "autoload": {
        "psr-0": { "React\\Dns": "" }
    },
    "target-dir": "React/Dns",
    "extra": {
        "branch-alias": {
            "dev-master": "0.3-dev"
        }
    }
}
<?php

namespace React\Dns;

class RecordNotFoundException extends \Exception
{
}
Network Working Group                                     P. Mockapetris
Request for Comments: 1034                                           ISI
Obsoletes: RFCs 882, 883, 973                              November 1987


                 DOMAIN NAMES - CONCEPTS AND FACILITIES



1. STATUS OF THIS MEMO

This RFC is an introduction to the Domain Name System (DNS), and omits
many details which can be found in a companion RFC, "Domain Names -
Implementation and Specification" [RFC-1035].  That RFC assumes that the
reader is familiar with the concepts discussed in this memo.

A subset of DNS functions and data types constitute an official
protocol.  The official protocol includes standard queries and their
responses and most of the Internet class data formats (e.g., host
addresses).

However, the domain system is intentionally extensible.  Researchers are
continuously proposing, implementing and experimenting with new data
types, query types, classes, functions, etc.  Thus while the components
of the official protocol are expected to stay essentially unchanged and
operate as a production service, experimental behavior should always be
expected in extensions beyond the official protocol.  Experimental or
obsolete features are clearly marked in these RFCs, and such information
should be used with caution.

The reader is especially cautioned not to depend on the values which
appear in examples to be current or complete, since their purpose is
primarily pedagogical.  Distribution of this memo is unlimited.

2. INTRODUCTION

This RFC introduces domain style names, their use for Internet mail and
host address support, and the protocols and servers used to implement
domain name facilities.

2.1. The history of domain names

The impetus for the development of the domain system was growth in the
Internet:

   - Host name to address mappings were maintained by the Network
     Information Center (NIC) in a single file (HOSTS.TXT) which
     was FTPed by all hosts [RFC-952, RFC-953].  The total network



Mockapetris                                                     [Page 1]

RFC 1034             Domain Concepts and Facilities        November 1987


     bandwidth consumed in distributing a new version by this
     scheme is proportional to the square of the number of hosts in
     the network, and even when multiple levels of FTP are used,
     the outgoing FTP load on the NIC host is considerable.
     Explosive growth in the number of hosts didn't bode well for
     the future.

   - The network population was also changing in character.  The
     timeshared hosts that made up the original ARPANET were being
     replaced with local networks of workstations.  Local
     organizations were administering their own names and
     addresses, but had to wait for the NIC to change HOSTS.TXT to
     make changes visible to the Internet at large.  Organizations
     also wanted some local structure on the name space.

   - The applications on the Internet were getting more
     sophisticated and creating a need for general purpose name
     service.


The result was several ideas about name spaces and their management
[IEN-116, RFC-799, RFC-819, RFC-830].  The proposals varied, but a
common thread was the idea of a hierarchical name space, with the
hierarchy roughly corresponding to organizational structure, and names
using "."  as the character to mark the boundary between hierarchy
levels.  A design using a distributed database and generalized resources
was described in [RFC-882, RFC-883].  Based on experience with several
implementations, the system evolved into the scheme described in this
memo.

The terms "domain" or "domain name" are used in many contexts beyond the
DNS described here.  Very often, the term domain name is used to refer
to a name with structure indicated by dots, but no relation to the DNS.
This is particularly true in mail addressing [Quarterman 86].

2.2. DNS design goals

The design goals of the DNS influence its structure.  They are:

   - The primary goal is a consistent name space which will be used
     for referring to resources.  In order to avoid the problems
     caused by ad hoc encodings, names should not be required to
     contain network identifiers, addresses, routes, or similar
     information as part of the name.

   - The sheer size of the database and frequency of updates
     suggest that it must be maintained in a distributed manner,
     with local caching to improve performance.  Approaches that



Mockapetris                                                     [Page 2]

RFC 1034             Domain Concepts and Facilities        November 1987


     attempt to collect a consistent copy of the entire database
     will become more and more expensive and difficult, and hence
     should be avoided.  The same principle holds for the structure
     of the name space, and in particular mechanisms for creating
     and deleting names; these should also be distributed.

   - Where there tradeoffs between the cost of acquiring data, the
     speed of updates, and the accuracy of caches, the source of
     the data should control the tradeoff.

   - The costs of implementing such a facility dictate that it be
     generally useful, and not restricted to a single application.
     We should be able to use names to retrieve host addresses,
     mailbox data, and other as yet undetermined information.  All
     data associated with a name is tagged with a type, and queries
     can be limited to a single type.

   - Because we want the name space to be useful in dissimilar
     networks and applications, we provide the ability to use the
     same name space with different protocol families or
     management.  For example, host address formats differ between
     protocols, though all protocols have the notion of address.
     The DNS tags all data with a class as well as the type, so
     that we can allow parallel use of different formats for data
     of type address.

   - We want name server transactions to be independent of the
     communications system that carries them.  Some systems may
     wish to use datagrams for queries and responses, and only
     establish virtual circuits for transactions that need the
     reliability (e.g., database updates, long transactions); other
     systems will use virtual circuits exclusively.

   - The system should be useful across a wide spectrum of host
     capabilities.  Both personal computers and large timeshared
     hosts should be able to use the system, though perhaps in
     different ways.

2.3. Assumptions about usage

The organization of the domain system derives from some assumptions
about the needs and usage patterns of its user community and is designed
to avoid many of the the complicated problems found in general purpose
database systems.

The assumptions are:

   - The size of the total database will initially be proportional



Mockapetris                                                     [Page 3]

RFC 1034             Domain Concepts and Facilities        November 1987


     to the number of hosts using the system, but will eventually
     grow to be proportional to the number of users on those hosts
     as mailboxes and other information are added to the domain
     system.

   - Most of the data in the system will change very slowly (e.g.,
     mailbox bindings, host addresses), but that the system should
     be able to deal with subsets that change more rapidly (on the
     order of seconds or minutes).

   - The administrative boundaries used to distribute
     responsibility for the database will usually correspond to
     organizations that have one or more hosts.  Each organization
     that has responsibility for a particular set of domains will
     provide redundant name servers, either on the organization's
     own hosts or other hosts that the organization arranges to
     use.

   - Clients of the domain system should be able to identify
     trusted name servers they prefer to use before accepting
     referrals to name servers outside of this "trusted" set.

   - Access to information is more critical than instantaneous
     updates or guarantees of consistency.  Hence the update
     process allows updates to percolate out through the users of
     the domain system rather than guaranteeing that all copies are
     simultaneously updated.  When updates are unavailable due to
     network or host failure, the usual course is to believe old
     information while continuing efforts to update it.  The
     general model is that copies are distributed with timeouts for
     refreshing.  The distributor sets the timeout value and the
     recipient of the distribution is responsible for performing
     the refresh.  In special situations, very short intervals can
     be specified, or the owner can prohibit copies.

   - In any system that has a distributed database, a particular
     name server may be presented with a query that can only be
     answered by some other server.  The two general approaches to
     dealing with this problem are "recursive", in which the first
     server pursues the query for the client at another server, and
     "iterative", in which the server refers the client to another
     server and lets the client pursue the query.  Both approaches
     have advantages and disadvantages, but the iterative approach
     is preferred for the datagram style of access.  The domain
     system requires implementation of the iterative approach, but
     allows the recursive approach as an option.





Mockapetris                                                     [Page 4]

RFC 1034             Domain Concepts and Facilities        November 1987


The domain system assumes that all data originates in master files
scattered through the hosts that use the domain system.  These master
files are updated by local system administrators.  Master files are text
files that are read by a local name server, and hence become available
through the name servers to users of the domain system.  The user
programs access name servers through standard programs called resolvers.

The standard format of master files allows them to be exchanged between
hosts (via FTP, mail, or some other mechanism); this facility is useful
when an organization wants a domain, but doesn't want to support a name
server.  The organization can maintain the master files locally using a
text editor, transfer them to a foreign host which runs a name server,
and then arrange with the system administrator of the name server to get
the files loaded.

Each host's name servers and resolvers are configured by a local system
administrator [RFC-1033].  For a name server, this configuration data
includes the identity of local master files and instructions on which
non-local master files are to be loaded from foreign servers.  The name
server uses the master files or copies to load its zones.  For
resolvers, the configuration data identifies the name servers which
should be the primary sources of information.

The domain system defines procedures for accessing the data and for
referrals to other name servers.  The domain system also defines
procedures for caching retrieved data and for periodic refreshing of
data defined by the system administrator.

The system administrators provide:

   - The definition of zone boundaries.

   - Master files of data.

   - Updates to master files.

   - Statements of the refresh policies desired.

The domain system provides:

   - Standard formats for resource data.

   - Standard methods for querying the database.

   - Standard methods for name servers to refresh local data from
     foreign name servers.





Mockapetris                                                     [Page 5]

RFC 1034             Domain Concepts and Facilities        November 1987


2.4. Elements of the DNS

The DNS has three major components:

   - The DOMAIN NAME SPACE and RESOURCE RECORDS, which are
     specifications for a tree structured name space and data
     associated with the names.  Conceptually, each node and leaf
     of the domain name space tree names a set of information, and
     query operations are attempts to extract specific types of
     information from a particular set.  A query names the domain
     name of interest and describes the type of resource
     information that is desired.  For example, the Internet
     uses some of its domain names to identify hosts; queries for
     address resources return Internet host addresses.

   - NAME SERVERS are server programs which hold information about
     the domain tree's structure and set information.  A name
     server may cache structure or set information about any part
     of the domain tree, but in general a particular name server
     has complete information about a subset of the domain space,
     and pointers to other name servers that can be used to lead to
     information from any part of the domain tree.  Name servers
     know the parts of the domain tree for which they have complete
     information; a name server is said to be an AUTHORITY for
     these parts of the name space.  Authoritative information is
     organized into units called ZONEs, and these zones can be
     automatically distributed to the name servers which provide
     redundant service for the data in a zone.

   - RESOLVERS are programs that extract information from name
     servers in response to client requests.  Resolvers must be
     able to access at least one name server and use that name
     server's information to answer a query directly, or pursue the
     query using referrals to other name servers.  A resolver will
     typically be a system routine that is directly accessible to
     user programs; hence no protocol is necessary between the
     resolver and the user program.

These three components roughly correspond to the three layers or views
of the domain system:

   - From the user's point of view, the domain system is accessed
     through a simple procedure or OS call to a local resolver.
     The domain space consists of a single tree and the user can
     request information from any section of the tree.

   - From the resolver's point of view, the domain system is
     composed of an unknown number of name servers.  Each name



Mockapetris                                                     [Page 6]

RFC 1034             Domain Concepts and Facilities        November 1987


     server has one or more pieces of the whole domain tree's data,
     but the resolver views each of these databases as essentially
     static.

   - From a name server's point of view, the domain system consists
     of separate sets of local information called zones.  The name
     server has local copies of some of the zones.  The name server
     must periodically refresh its zones from master copies in
     local files or foreign name servers.  The name server must
     concurrently process queries that arrive from resolvers.

In the interests of performance, implementations may couple these
functions.  For example, a resolver on the same machine as a name server
might share a database consisting of the the zones managed by the name
server and the cache managed by the resolver.

3. DOMAIN NAME SPACE and RESOURCE RECORDS

3.1. Name space specifications and terminology

The domain name space is a tree structure.  Each node and leaf on the
tree corresponds to a resource set (which may be empty).  The domain
system makes no distinctions between the uses of the interior nodes and
leaves, and this memo uses the term "node" to refer to both.

Each node has a label, which is zero to 63 octets in length.  Brother
nodes may not have the same label, although the same label can be used
for nodes which are not brothers.  One label is reserved, and that is
the null (i.e., zero length) label used for the root.

The domain name of a node is the list of the labels on the path from the
node to the root of the tree.  By convention, the labels that compose a
domain name are printed or read left to right, from the most specific
(lowest, farthest from the root) to the least specific (highest, closest
to the root).

Internally, programs that manipulate domain names should represent them
as sequences of labels, where each label is a length octet followed by
an octet string.  Because all domain names end at the root, which has a
null string for a label, these internal representations can use a length
byte of zero to terminate a domain name.

By convention, domain names can be stored with arbitrary case, but
domain name comparisons for all present domain functions are done in a
case-insensitive manner, assuming an ASCII character set, and a high
order zero bit.  This means that you are free to create a node with
label "A" or a node with label "a", but not both as brothers; you could
refer to either using "a" or "A".  When you receive a domain name or



Mockapetris                                                     [Page 7]

RFC 1034             Domain Concepts and Facilities        November 1987


label, you should preserve its case.  The rationale for this choice is
that we may someday need to add full binary domain names for new
services; existing services would not be changed.

When a user needs to type a domain name, the length of each label is
omitted and the labels are separated by dots (".").  Since a complete
domain name ends with the root label, this leads to a printed form which
ends in a dot.  We use this property to distinguish between:

   - a character string which represents a complete domain name
     (often called "absolute").  For example, "poneria.ISI.EDU."

   - a character string that represents the starting labels of a
     domain name which is incomplete, and should be completed by
     local software using knowledge of the local domain (often
     called "relative").  For example, "poneria" used in the
     ISI.EDU domain.

Relative names are either taken relative to a well known origin, or to a
list of domains used as a search list.  Relative names appear mostly at
the user interface, where their interpretation varies from
implementation to implementation, and in master files, where they are
relative to a single origin domain name.  The most common interpretation
uses the root "." as either the single origin or as one of the members
of the search list, so a multi-label relative name is often one where
the trailing dot has been omitted to save typing.

To simplify implementations, the total number of octets that represent a
domain name (i.e., the sum of all label octets and label lengths) is
limited to 255.

A domain is identified by a domain name, and consists of that part of
the domain name space that is at or below the domain name which
specifies the domain.  A domain is a subdomain of another domain if it
is contained within that domain.  This relationship can be tested by
seeing if the subdomain's name ends with the containing domain's name.
For example, A.B.C.D is a subdomain of B.C.D, C.D, D, and " ".

3.2. Administrative guidelines on use

As a matter of policy, the DNS technical specifications do not mandate a
particular tree structure or rules for selecting labels; its goal is to
be as general as possible, so that it can be used to build arbitrary
applications.  In particular, the system was designed so that the name
space did not have to be organized along the lines of network
boundaries, name servers, etc.  The rationale for this is not that the
name space should have no implied semantics, but rather that the choice
of implied semantics should be left open to be used for the problem at



Mockapetris                                                     [Page 8]

RFC 1034             Domain Concepts and Facilities        November 1987


hand, and that different parts of the tree can have different implied
semantics.  For example, the IN-ADDR.ARPA domain is organized and
distributed by network and host address because its role is to translate
from network or host numbers to names; NetBIOS domains [RFC-1001, RFC-
1002] are flat because that is appropriate for that application.

However, there are some guidelines that apply to the "normal" parts of
the name space used for hosts, mailboxes, etc., that will make the name
space more uniform, provide for growth, and minimize problems as
software is converted from the older host table.  The political
decisions about the top levels of the tree originated in RFC-920.
Current policy for the top levels is discussed in [RFC-1032].  MILNET
conversion issues are covered in [RFC-1031].

Lower domains which will eventually be broken into multiple zones should
provide branching at the top of the domain so that the eventual
decomposition can be done without renaming.  Node labels which use
special characters, leading digits, etc., are likely to break older
software which depends on more restrictive choices.

3.3. Technical guidelines on use

Before the DNS can be used to hold naming information for some kind of
object, two needs must be met:

   - A convention for mapping between object names and domain
     names.  This describes how information about an object is
     accessed.

   - RR types and data formats for describing the object.

These rules can be quite simple or fairly complex.  Very often, the
designer must take into account existing formats and plan for upward
compatibility for existing usage.  Multiple mappings or levels of
mapping may be required.

For hosts, the mapping depends on the existing syntax for host names
which is a subset of the usual text representation for domain names,
together with RR formats for describing host addresses, etc.  Because we
need a reliable inverse mapping from address to host name, a special
mapping for addresses into the IN-ADDR.ARPA domain is also defined.

For mailboxes, the mapping is slightly more complex.  The usual mail
address <local-part>@<mail-domain> is mapped into a domain name by
converting <local-part> into a single label (regardles of dots it
contains), converting <mail-domain> into a domain name using the usual
text format for domain names (dots denote label breaks), and
concatenating the two to form a single domain name.  Thus the mailbox



Mockapetris                                                     [Page 9]

RFC 1034             Domain Concepts and Facilities        November 1987


HOSTMASTER@SRI-NIC.ARPA is represented as a domain name by
HOSTMASTER.SRI-NIC.ARPA.  An appreciation for the reasons behind this
design also must take into account the scheme for mail exchanges [RFC-
974].

The typical user is not concerned with defining these rules, but should
understand that they usually are the result of numerous compromises
between desires for upward compatibility with old usage, interactions
between different object definitions, and the inevitable urge to add new
features when defining the rules.  The way the DNS is used to support
some object is often more crucial than the restrictions inherent in the
DNS.

3.4. Example name space

The following figure shows a part of the current domain name space, and
is used in many examples in this RFC.  Note that the tree is a very
small subset of the actual name space.

                                   |
                                   |
             +---------------------+------------------+
             |                     |                  |
            MIL                   EDU                ARPA
             |                     |                  |
             |                     |                  |
       +-----+-----+               |     +------+-----+-----+
       |     |     |               |     |      |           |
      BRL  NOSC  DARPA             |  IN-ADDR  SRI-NIC     ACC
                                   |
       +--------+------------------+---------------+--------+
       |        |                  |               |        |
      UCI      MIT                 |              UDEL     YALE
                |                 ISI
                |                  |
            +---+---+              |
            |       |              |
           LCS  ACHILLES  +--+-----+-----+--------+
            |             |  |     |     |        |
            XX            A  C   VAXA  VENERA Mockapetris

In this example, the root domain has three immediate subdomains: MIL,
EDU, and ARPA.  The LCS.MIT.EDU domain has one immediate subdomain named
XX.LCS.MIT.EDU.  All of the leaves are also domains.

3.5. Preferred name syntax

The DNS specifications attempt to be as general as possible in the rules



Mockapetris                                                    [Page 10]

RFC 1034             Domain Concepts and Facilities        November 1987


for constructing domain names.  The idea is that the name of any
existing object can be expressed as a domain name with minimal changes.
However, when assigning a domain name for an object, the prudent user
will select a name which satisfies both the rules of the domain system
and any existing rules for the object, whether these rules are published
or implied by existing programs.

For example, when naming a mail domain, the user should satisfy both the
rules of this memo and those in RFC-822.  When creating a new host name,
the old rules for HOSTS.TXT should be followed.  This avoids problems
when old software is converted to use domain names.

The following syntax will result in fewer problems with many
applications that use domain names (e.g., mail, TELNET).

<domain> ::= <subdomain> | " "

<subdomain> ::= <label> | <subdomain> "." <label>

<label> ::= <letter> [ [ <ldh-str> ] <let-dig> ]

<ldh-str> ::= <let-dig-hyp> | <let-dig-hyp> <ldh-str>

<let-dig-hyp> ::= <let-dig> | "-"

<let-dig> ::= <letter> | <digit>

<letter> ::= any one of the 52 alphabetic characters A through Z in
upper case and a through z in lower case

<digit> ::= any one of the ten digits 0 through 9

Note that while upper and lower case letters are allowed in domain
names, no significance is attached to the case.  That is, two names with
the same spelling but different case are to be treated as if identical.

The labels must follow the rules for ARPANET host names.  They must
start with a letter, end with a letter or digit, and have as interior
characters only letters, digits, and hyphen.  There are also some
restrictions on the length.  Labels must be 63 characters or less.

For example, the following strings identify hosts in the Internet:

A.ISI.EDU  XX.LCS.MIT.EDU  SRI-NIC.ARPA

3.6. Resource Records

A domain name identifies a node.  Each node has a set of resource



Mockapetris                                                    [Page 11]

RFC 1034             Domain Concepts and Facilities        November 1987


information, which may be empty.  The set of resource information
associated with a particular name is composed of separate resource
records (RRs).  The order of RRs in a set is not significant, and need
not be preserved by name servers, resolvers, or other parts of the DNS.

When we talk about a specific RR, we assume it has the following:

owner           which is the domain name where the RR is found.

type            which is an encoded 16 bit value that specifies the type
                of the resource in this resource record.  Types refer to
                abstract resources.

                This memo uses the following types:

                A               a host address

                CNAME           identifies the canonical name of an
                                alias

                HINFO           identifies the CPU and OS used by a host

                MX              identifies a mail exchange for the
                                domain.  See [RFC-974 for details.

                NS
                the authoritative name server for the domain

                PTR
                a pointer to another part of the domain name space

                SOA
                identifies the start of a zone of authority]

class           which is an encoded 16 bit value which identifies a
                protocol family or instance of a protocol.

                This memo uses the following classes:

                IN              the Internet system

                CH              the Chaos system

TTL             which is the time to live of the RR.  This field is a 32
                bit integer in units of seconds, an is primarily used by
                resolvers when they cache RRs.  The TTL describes how
                long a RR can be cached before it should be discarded.




Mockapetris                                                    [Page 12]

RFC 1034             Domain Concepts and Facilities        November 1987


RDATA           which is the type and sometimes class dependent data
                which describes the resource:

                A               For the IN class, a 32 bit IP address

                                For the CH class, a domain name followed
                                by a 16 bit octal Chaos address.

                CNAME           a domain name.

                MX              a 16 bit preference value (lower is
                                better) followed by a host name willing
                                to act as a mail exchange for the owner
                                domain.

                NS              a host name.

                PTR             a domain name.

                SOA             several fields.

The owner name is often implicit, rather than forming an integral part
of the RR.  For example, many name servers internally form tree or hash
structures for the name space, and chain RRs off nodes.  The remaining
RR parts are the fixed header (type, class, TTL) which is consistent for
all RRs, and a variable part (RDATA) that fits the needs of the resource
being described.

The meaning of the TTL field is a time limit on how long an RR can be
kept in a cache.  This limit does not apply to authoritative data in
zones; it is also timed out, but by the refreshing policies for the
zone.  The TTL is assigned by the administrator for the zone where the
data originates.  While short TTLs can be used to minimize caching, and
a zero TTL prohibits caching, the realities of Internet performance
suggest that these times should be on the order of days for the typical
host.  If a change can be anticipated, the TTL can be reduced prior to
the change to minimize inconsistency during the change, and then
increased back to its former value following the change.

The data in the RDATA section of RRs is carried as a combination of
binary strings and domain names.  The domain names are frequently used
as "pointers" to other data in the DNS.

3.6.1. Textual expression of RRs

RRs are represented in binary form in the packets of the DNS protocol,
and are usually represented in highly encoded form when stored in a name
server or resolver.  In this memo, we adopt a style similar to that used



Mockapetris                                                    [Page 13]

RFC 1034             Domain Concepts and Facilities        November 1987


in master files in order to show the contents of RRs.  In this format,
most RRs are shown on a single line, although continuation lines are
possible using parentheses.

The start of the line gives the owner of the RR.  If a line begins with
a blank, then the owner is assumed to be the same as that of the
previous RR.  Blank lines are often included for readability.

Following the owner, we list the TTL, type, and class of the RR.  Class
and type use the mnemonics defined above, and TTL is an integer before
the type field.  In order to avoid ambiguity in parsing, type and class
mnemonics are disjoint, TTLs are integers, and the type mnemonic is
always last. The IN class and TTL values are often omitted from examples
in the interests of clarity.

The resource data or RDATA section of the RR are given using knowledge
of the typical representation for the data.

For example, we might show the RRs carried in a message as:

    ISI.EDU.        MX      10 VENERA.ISI.EDU.
                    MX      10 VAXA.ISI.EDU.
    VENERA.ISI.EDU. A       128.9.0.32
                    A       10.1.0.52
    VAXA.ISI.EDU.   A       10.2.0.27
                    A       128.9.0.33

The MX RRs have an RDATA section which consists of a 16 bit number
followed by a domain name.  The address RRs use a standard IP address
format to contain a 32 bit internet address.

This example shows six RRs, with two RRs at each of three domain names.

Similarly we might see:

    XX.LCS.MIT.EDU. IN      A       10.0.0.44
                    CH      A       MIT.EDU. 2420

This example shows two addresses for XX.LCS.MIT.EDU, each of a different
class.

3.6.2. Aliases and canonical names

In existing systems, hosts and other resources often have several names
that identify the same resource.  For example, the names C.ISI.EDU and
USC-ISIC.ARPA both identify the same host.  Similarly, in the case of
mailboxes, many organizations provide many names that actually go to the
same mailbox; for example Mockapetris@C.ISI.EDU, Mockapetris@B.ISI.EDU,



Mockapetris                                                    [Page 14]

RFC 1034             Domain Concepts and Facilities        November 1987


and PVM@ISI.EDU all go to the same mailbox (although the mechanism
behind this is somewhat complicated).

Most of these systems have a notion that one of the equivalent set of
names is the canonical or primary name and all others are aliases.

The domain system provides such a feature using the canonical name
(CNAME) RR.  A CNAME RR identifies its owner name as an alias, and
specifies the corresponding canonical name in the RDATA section of the
RR.  If a CNAME RR is present at a node, no other data should be
present; this ensures that the data for a canonical name and its aliases
cannot be different.  This rule also insures that a cached CNAME can be
used without checking with an authoritative server for other RR types.

CNAME RRs cause special action in DNS software.  When a name server
fails to find a desired RR in the resource set associated with the
domain name, it checks to see if the resource set consists of a CNAME
record with a matching class.  If so, the name server includes the CNAME
record in the response and restarts the query at the domain name
specified in the data field of the CNAME record.  The one exception to
this rule is that queries which match the CNAME type are not restarted.

For example, suppose a name server was processing a query with for USC-
ISIC.ARPA, asking for type A information, and had the following resource
records:

    USC-ISIC.ARPA   IN      CNAME   C.ISI.EDU

    C.ISI.EDU       IN      A       10.0.0.52

Both of these RRs would be returned in the response to the type A query,
while a type CNAME or * query should return just the CNAME.

Domain names in RRs which point at another name should always point at
the primary name and not the alias.  This avoids extra indirections in
accessing information.  For example, the address to name RR for the
above host should be:

    52.0.0.10.IN-ADDR.ARPA  IN      PTR     C.ISI.EDU

rather than pointing at USC-ISIC.ARPA.  Of course, by the robustness
principle, domain software should not fail when presented with CNAME
chains or loops; CNAME chains should be followed and CNAME loops
signalled as an error.

3.7. Queries

Queries are messages which may be sent to a name server to provoke a



Mockapetris                                                    [Page 15]

RFC 1034             Domain Concepts and Facilities        November 1987


response.  In the Internet, queries are carried in UDP datagrams or over
TCP connections.  The response by the name server either answers the
question posed in the query, refers the requester to another set of name
servers, or signals some error condition.

In general, the user does not generate queries directly, but instead
makes a request to a resolver which in turn sends one or more queries to
name servers and deals with the error conditions and referrals that may
result.  Of course, the possible questions which can be asked in a query
does shape the kind of service a resolver can provide.

DNS queries and responses are carried in a standard message format.  The
message format has a header containing a number of fixed fields which
are always present, and four sections which carry query parameters and
RRs.

The most important field in the header is a four bit field called an
opcode which separates different queries.  Of the possible 16 values,
one (standard query) is part of the official protocol, two (inverse
query and status query) are options, one (completion) is obsolete, and
the rest are unassigned.

The four sections are:

Question        Carries the query name and other query parameters.

Answer          Carries RRs which directly answer the query.

Authority       Carries RRs which describe other authoritative servers.
                May optionally carry the SOA RR for the authoritative
                data in the answer section.

Additional      Carries RRs which may be helpful in using the RRs in the
                other sections.

Note that the content, but not the format, of these sections varies with
header opcode.

3.7.1. Standard queries

A standard query specifies a target domain name (QNAME), query type
(QTYPE), and query class (QCLASS) and asks for RRs which match.  This
type of query makes up such a vast majority of DNS queries that we use
the term "query" to mean standard query unless otherwise specified.  The
QTYPE and QCLASS fields are each 16 bits long, and are a superset of
defined types and classes.





Mockapetris                                                    [Page 16]

RFC 1034             Domain Concepts and Facilities        November 1987


The QTYPE field may contain:

<any type>      matches just that type. (e.g., A, PTR).

AXFR            special zone transfer QTYPE.

MAILB           matches all mail box related RRs (e.g. MB and MG).

*               matches all RR types.

The QCLASS field may contain:

<any class>     matches just that class (e.g., IN, CH).

*               matches aLL RR classes.

Using the query domain name, QTYPE, and QCLASS, the name server looks
for matching RRs.  In addition to relevant records, the name server may
return RRs that point toward a name server that has the desired
information or RRs that are expected to be useful in interpreting the
relevant RRs.  For example, a name server that doesn't have the
requested information may know a name server that does; a name server
that returns a domain name in a relevant RR may also return the RR that
binds that domain name to an address.

For example, a mailer tying to send mail to Mockapetris@ISI.EDU might
ask the resolver for mail information about ISI.EDU, resulting in a
query for QNAME=ISI.EDU, QTYPE=MX, QCLASS=IN.  The response's answer
section would be:

    ISI.EDU.        MX      10 VENERA.ISI.EDU.
                    MX      10 VAXA.ISI.EDU.

while the additional section might be:

    VAXA.ISI.EDU.   A       10.2.0.27
                    A       128.9.0.33
    VENERA.ISI.EDU. A       10.1.0.52
                    A       128.9.0.32

Because the server assumes that if the requester wants mail exchange
information, it will probably want the addresses of the mail exchanges
soon afterward.

Note that the QCLASS=* construct requires special interpretation
regarding authority.  Since a particular name server may not know all of
the classes available in the domain system, it can never know if it is
authoritative for all classes.  Hence responses to QCLASS=* queries can



Mockapetris                                                    [Page 17]

RFC 1034             Domain Concepts and Facilities        November 1987


never be authoritative.

3.7.2. Inverse queries (Optional)

Name servers may also support inverse queries that map a particular
resource to a domain name or domain names that have that resource.  For
example, while a standard query might map a domain name to a SOA RR, the
corresponding inverse query might map the SOA RR back to the domain
name.

Implementation of this service is optional in a name server, but all
name servers must at least be able to understand an inverse query
message and return a not-implemented error response.

The domain system cannot guarantee the completeness or uniqueness of
inverse queries because the domain system is organized by domain name
rather than by host address or any other resource type.  Inverse queries
are primarily useful for debugging and database maintenance activities.

Inverse queries may not return the proper TTL, and do not indicate cases
where the identified RR is one of a set (for example, one address for a
host having multiple addresses).  Therefore, the RRs returned in inverse
queries should never be cached.

Inverse queries are NOT an acceptable method for mapping host addresses
to host names; use the IN-ADDR.ARPA domain instead.

A detailed discussion of inverse queries is contained in [RFC-1035].

3.8. Status queries (Experimental)

To be defined.

3.9. Completion queries (Obsolete)

The optional completion services described in RFCs 882 and 883 have been
deleted.  Redesigned services may become available in the future, or the
opcodes may be reclaimed for other use.

4. NAME SERVERS

4.1. Introduction

Name servers are the repositories of information that make up the domain
database.  The database is divided up into sections called zones, which
are distributed among the name servers.  While name servers can have
several optional functions and sources of data, the essential task of a
name server is to answer queries using data in its zones.  By design,



Mockapetris                                                    [Page 18]

RFC 1034             Domain Concepts and Facilities        November 1987


name servers can answer queries in a simple manner; the response can
always be generated using only local data, and either contains the
answer to the question or a referral to other name servers "closer" to
the desired information.

A given zone will be available from several name servers to insure its
availability in spite of host or communication link failure.  By
administrative fiat, we require every zone to be available on at least
two servers, and many zones have more redundancy than that.

A given name server will typically support one or more zones, but this
gives it authoritative information about only a small section of the
domain tree.  It may also have some cached non-authoritative data about
other parts of the tree.  The name server marks its responses to queries
so that the requester can tell whether the response comes from
authoritative data or not.

4.2. How the database is divided into zones

The domain database is partitioned in two ways: by class, and by "cuts"
made in the name space between nodes.

The class partition is simple.  The database for any class is organized,
delegated, and maintained separately from all other classes.  Since, by
convention, the name spaces are the same for all classes, the separate
classes can be thought of as an array of parallel namespace trees.  Note
that the data attached to nodes will be different for these different
parallel classes.  The most common reasons for creating a new class are
the necessity for a new data format for existing types or a desire for a
separately managed version of the existing name space.

Within a class, "cuts" in the name space can be made between any two
adjacent nodes.  After all cuts are made, each group of connected name
space is a separate zone.  The zone is said to be authoritative for all
names in the connected region.  Note that the "cuts" in the name space
may be in different places for different classes, the name servers may
be different, etc.

These rules mean that every zone has at least one node, and hence domain
name, for which it is authoritative, and all of the nodes in a
particular zone are connected.  Given, the tree structure, every zone
has a highest node which is closer to the root than any other node in
the zone.  The name of this node is often used to identify the zone.

It would be possible, though not particularly useful, to partition the
name space so that each domain name was in a separate zone or so that
all nodes were in a single zone.  Instead, the database is partitioned
at points where a particular organization wants to take over control of



Mockapetris                                                    [Page 19]

RFC 1034             Domain Concepts and Facilities        November 1987


a subtree.  Once an organization controls its own zone it can
unilaterally change the data in the zone, grow new tree sections
connected to the zone, delete existing nodes, or delegate new subzones
under its zone.

If the organization has substructure, it may want to make further
internal partitions to achieve nested delegations of name space control.
In some cases, such divisions are made purely to make database
maintenance more convenient.

4.2.1. Technical considerations

The data that describes a zone has four major parts:

   - Authoritative data for all nodes within the zone.

   - Data that defines the top node of the zone (can be thought of
     as part of the authoritative data).

   - Data that describes delegated subzones, i.e., cuts around the
     bottom of the zone.

   - Data that allows access to name servers for subzones
     (sometimes called "glue" data).

All of this data is expressed in the form of RRs, so a zone can be
completely described in terms of a set of RRs.  Whole zones can be
transferred between name servers by transferring the RRs, either carried
in a series of messages or by FTPing a master file which is a textual
representation.

The authoritative data for a zone is simply all of the RRs attached to
all of the nodes from the top node of the zone down to leaf nodes or
nodes above cuts around the bottom edge of the zone.

Though logically part of the authoritative data, the RRs that describe
the top node of the zone are especially important to the zone's
management.  These RRs are of two types: name server RRs that list, one
per RR, all of the servers for the zone, and a single SOA RR that
describes zone management parameters.

The RRs that describe cuts around the bottom of the zone are NS RRs that
name the servers for the subzones.  Since the cuts are between nodes,
these RRs are NOT part of the authoritative data of the zone, and should
be exactly the same as the corresponding RRs in the top node of the
subzone.  Since name servers are always associated with zone boundaries,
NS RRs are only found at nodes which are the top node of some zone.  In
the data that makes up a zone, NS RRs are found at the top node of the



Mockapetris                                                    [Page 20]

RFC 1034             Domain Concepts and Facilities        November 1987


zone (and are authoritative) and at cuts around the bottom of the zone
(where they are not authoritative), but never in between.

One of the goals of the zone structure is that any zone have all the
data required to set up communications with the name servers for any
subzones.  That is, parent zones have all the information needed to
access servers for their children zones.  The NS RRs that name the
servers for subzones are often not enough for this task since they name
the servers, but do not give their addresses.  In particular, if the
name of the name server is itself in the subzone, we could be faced with
the situation where the NS RRs tell us that in order to learn a name
server's address, we should contact the server using the address we wish
to learn.  To fix this problem, a zone contains "glue" RRs which are not
part of the authoritative data, and are address RRs for the servers.
These RRs are only necessary if the name server's name is "below" the
cut, and are only used as part of a referral response.

4.2.2. Administrative considerations

When some organization wants to control its own domain, the first step
is to identify the proper parent zone, and get the parent zone's owners
to agree to the delegation of control.  While there are no particular
technical constraints dealing with where in the tree this can be done,
there are some administrative groupings discussed in [RFC-1032] which
deal with top level organization, and middle level zones are free to
create their own rules.  For example, one university might choose to use
a single zone, while another might choose to organize by subzones
dedicated to individual departments or schools.  [RFC-1033] catalogs
available DNS software an discusses administration procedures.

Once the proper name for the new subzone is selected, the new owners
should be required to demonstrate redundant name server support.  Note
that there is no requirement that the servers for a zone reside in a
host which has a name in that domain.  In many cases, a zone will be
more accessible to the internet at large if its servers are widely
distributed rather than being within the physical facilities controlled
by the same organization that manages the zone.  For example, in the
current DNS, one of the name servers for the United Kingdom, or UK
domain, is found in the US.  This allows US hosts to get UK data without
using limited transatlantic bandwidth.

As the last installation step, the delegation NS RRs and glue RRs
necessary to make the delegation effective should be added to the parent
zone.  The administrators of both zones should insure that the NS and
glue RRs which mark both sides of the cut are consistent and remain so.

4.3. Name server internals




Mockapetris                                                    [Page 21]

RFC 1034             Domain Concepts and Facilities        November 1987


4.3.1. Queries and responses

The principal activity of name servers is to answer standard queries.
Both the query and its response are carried in a standard message format
which is described in [RFC-1035].  The query contains a QTYPE, QCLASS,
and QNAME, which describe the types and classes of desired information
and the name of interest.

The way that the name server answers the query depends upon whether it
is operating in recursive mode or not:

   - The simplest mode for the server is non-recursive, since it
     can answer queries using only local information: the response
     contains an error, the answer, or a referral to some other
     server "closer" to the answer.  All name servers must
     implement non-recursive queries.

   - The simplest mode for the client is recursive, since in this
     mode the name server acts in the role of a resolver and
     returns either an error or the answer, but never referrals.
     This service is optional in a name server, and the name server
     may also choose to restrict the clients which can use
     recursive mode.

Recursive service is helpful in several situations:

   - a relatively simple requester that lacks the ability to use
     anything other than a direct answer to the question.

   - a request that needs to cross protocol or other boundaries and
     can be sent to a server which can act as intermediary.

   - a network where we want to concentrate the cache rather than
     having a separate cache for each client.

Non-recursive service is appropriate if the requester is capable of
pursuing referrals and interested in information which will aid future
requests.

The use of recursive mode is limited to cases where both the client and
the name server agree to its use.  The agreement is negotiated through
the use of two bits in query and response messages:

   - The recursion available, or RA bit, is set or cleared by a
     name server in all responses.  The bit is true if the name
     server is willing to provide recursive service for the client,
     regardless of whether the client requested recursive service.
     That is, RA signals availability rather than use.



Mockapetris                                                    [Page 22]

RFC 1034             Domain Concepts and Facilities        November 1987


   - Queries contain a bit called recursion desired or RD.  This
     bit specifies specifies whether the requester wants recursive
     service for this query.  Clients may request recursive service
     from any name server, though they should depend upon receiving
     it only from servers which have previously sent an RA, or
     servers which have agreed to provide service through private
     agreement or some other means outside of the DNS protocol.

The recursive mode occurs when a query with RD set arrives at a server
which is willing to provide recursive service; the client can verify
that recursive mode was used by checking that both RA and RD are set in
the reply.  Note that the name server should never perform recursive
service unless asked via RD, since this interferes with trouble shooting
of name servers and their databases.

If recursive service is requested and available, the recursive response
to a query will be one of the following:

   - The answer to the query, possibly preface by one or more CNAME
     RRs that specify aliases encountered on the way to an answer.

   - A name error indicating that the name does not exist.  This
     may include CNAME RRs that indicate that the original query
     name was an alias for a name which does not exist.

   - A temporary error indication.

If recursive service is not requested or is not available, the non-
recursive response will be one of the following:

   - An authoritative name error indicating that the name does not
     exist.

   - A temporary error indication.

   - Some combination of:

     RRs that answer the question, together with an indication
     whether the data comes from a zone or is cached.

     A referral to name servers which have zones which are closer
     ancestors to the name than the server sending the reply.

   - RRs that the name server thinks will prove useful to the
     requester.






Mockapetris                                                    [Page 23]

RFC 1034             Domain Concepts and Facilities        November 1987


4.3.2. Algorithm

The actual algorithm used by the name server will depend on the local OS
and data structures used to store RRs.  The following algorithm assumes
that the RRs are organized in several tree structures, one for each
zone, and another for the cache:

   1. Set or clear the value of recursion available in the response
      depending on whether the name server is willing to provide
      recursive service.  If recursive service is available and
      requested via the RD bit in the query, go to step 5,
      otherwise step 2.

   2. Search the available zones for the zone which is the nearest
      ancestor to QNAME.  If such a zone is found, go to step 3,
      otherwise step 4.

   3. Start matching down, label by label, in the zone.  The
      matching process can terminate several ways:

         a. If the whole of QNAME is matched, we have found the
            node.

            If the data at the node is a CNAME, and QTYPE doesn't
            match CNAME, copy the CNAME RR into the answer section
            of the response, change QNAME to the canonical name in
            the CNAME RR, and go back to step 1.

            Otherwise, copy all RRs which match QTYPE into the
            answer section and go to step 6.

         b. If a match would take us out of the authoritative data,
            we have a referral.  This happens when we encounter a
            node with NS RRs marking cuts along the bottom of a
            zone.

            Copy the NS RRs for the subzone into the authority
            section of the reply.  Put whatever addresses are
            available into the additional section, using glue RRs
            if the addresses are not available from authoritative
            data or the cache.  Go to step 4.

         c. If at some label, a match is impossible (i.e., the
            corresponding label does not exist), look to see if a
            the "*" label exists.

            If the "*" label does not exist, check whether the name
            we are looking for is the original QNAME in the query



Mockapetris                                                    [Page 24]

RFC 1034             Domain Concepts and Facilities        November 1987


            or a name we have followed due to a CNAME.  If the name
            is original, set an authoritative name error in the
            response and exit.  Otherwise just exit.

            If the "*" label does exist, match RRs at that node
            against QTYPE.  If any match, copy them into the answer
            section, but set the owner of the RR to be QNAME, and
            not the node with the "*" label.  Go to step 6.

   4. Start matching down in the cache.  If QNAME is found in the
      cache, copy all RRs attached to it that match QTYPE into the
      answer section.  If there was no delegation from
      authoritative data, look for the best one from the cache, and
      put it in the authority section.  Go to step 6.

   5. Using the local resolver or a copy of its algorithm (see
      resolver section of this memo) to answer the query.  Store
      the results, including any intermediate CNAMEs, in the answer
      section of the response.

   6. Using local data only, attempt to add other RRs which may be
      useful to the additional section of the query.  Exit.

4.3.3. Wildcards

In the previous algorithm, special treatment was given to RRs with owner
names starting with the label "*".  Such RRs are called wildcards.
Wildcard RRs can be thought of as instructions for synthesizing RRs.
When the appropriate conditions are met, the name server creates RRs
with an owner name equal to the query name and contents taken from the
wildcard RRs.

This facility is most often used to create a zone which will be used to
forward mail from the Internet to some other mail system.  The general
idea is that any name in that zone which is presented to server in a
query will be assumed to exist, with certain properties, unless explicit
evidence exists to the contrary.  Note that the use of the term zone
here, instead of domain, is intentional; such defaults do not propagate
across zone boundaries, although a subzone may choose to achieve that
appearance by setting up similar defaults.

The contents of the wildcard RRs follows the usual rules and formats for
RRs.  The wildcards in the zone have an owner name that controls the
query names they will match.  The owner name of the wildcard RRs is of
the form "*.<anydomain>", where <anydomain> is any domain name.
<anydomain> should not contain other * labels, and should be in the
authoritative data of the zone.  The wildcards potentially apply to
descendants of <anydomain>, but not to <anydomain> itself.  Another way



Mockapetris                                                    [Page 25]

RFC 1034             Domain Concepts and Facilities        November 1987


to look at this is that the "*" label always matches at least one whole
label and sometimes more, but always whole labels.

Wildcard RRs do not apply:

   - When the query is in another zone.  That is, delegation cancels
     the wildcard defaults.

   - When the query name or a name between the wildcard domain and
     the query name is know to exist.  For example, if a wildcard
     RR has an owner name of "*.X", and the zone also contains RRs
     attached to B.X, the wildcards would apply to queries for name
     Z.X (presuming there is no explicit information for Z.X), but
     not to B.X, A.B.X, or X.

A * label appearing in a query name has no special effect, but can be
used to test for wildcards in an authoritative zone; such a query is the
only way to get a response containing RRs with an owner name with * in
it.  The result of such a query should not be cached.

Note that the contents of the wildcard RRs are not modified when used to
synthesize RRs.

To illustrate the use of wildcard RRs, suppose a large company with a
large, non-IP/TCP, network wanted to create a mail gateway.  If the
company was called X.COM, and IP/TCP capable gateway machine was called
A.X.COM, the following RRs might be entered into the COM zone:

    X.COM           MX      10      A.X.COM

    *.X.COM         MX      10      A.X.COM

    A.X.COM         A       1.2.3.4
    A.X.COM         MX      10      A.X.COM

    *.A.X.COM       MX      10      A.X.COM

This would cause any MX query for any domain name ending in X.COM to
return an MX RR pointing at A.X.COM.  Two wildcard RRs are required
since the effect of the wildcard at *.X.COM is inhibited in the A.X.COM
subtree by the explicit data for A.X.COM.  Note also that the explicit
MX data at X.COM and A.X.COM is required, and that none of the RRs above
would match a query name of XX.COM.

4.3.4. Negative response caching (Optional)

The DNS provides an optional service which allows name servers to
distribute, and resolvers to cache, negative results with TTLs.  For



Mockapetris                                                    [Page 26]

RFC 1034             Domain Concepts and Facilities        November 1987


example, a name server can distribute a TTL along with a name error
indication, and a resolver receiving such information is allowed to
assume that the name does not exist during the TTL period without
consulting authoritative data.  Similarly, a resolver can make a query
with a QTYPE which matches multiple types, and cache the fact that some
of the types are not present.

This feature can be particularly important in a system which implements
naming shorthands that use search lists beacuse a popular shorthand,
which happens to require a suffix toward the end of the search list,
will generate multiple name errors whenever it is used.

The method is that a name server may add an SOA RR to the additional
section of a response when that response is authoritative.  The SOA must
be that of the zone which was the source of the authoritative data in
the answer section, or name error if applicable.  The MINIMUM field of
the SOA controls the length of time that the negative result may be
cached.

Note that in some circumstances, the answer section may contain multiple
owner names.  In this case, the SOA mechanism should only be used for
the data which matches QNAME, which is the only authoritative data in
this section.

Name servers and resolvers should never attempt to add SOAs to the
additional section of a non-authoritative response, or attempt to infer
results which are not directly stated in an authoritative response.
There are several reasons for this, including: cached information isn't
usually enough to match up RRs and their zone names, SOA RRs may be
cached due to direct SOA queries, and name servers are not required to
output the SOAs in the authority section.

This feature is optional, although a refined version is expected to
become part of the standard protocol in the future.  Name servers are
not required to add the SOA RRs in all authoritative responses, nor are
resolvers required to cache negative results.  Both are recommended.
All resolvers and recursive name servers are required to at least be
able to ignore the SOA RR when it is present in a response.

Some experiments have also been proposed which will use this feature.
The idea is that if cached data is known to come from a particular zone,
and if an authoritative copy of the zone's SOA is obtained, and if the
zone's SERIAL has not changed since the data was cached, then the TTL of
the cached data can be reset to the zone MINIMUM value if it is smaller.
This usage is mentioned for planning purposes only, and is not
recommended as yet.





Mockapetris                                                    [Page 27]

RFC 1034             Domain Concepts and Facilities        November 1987


4.3.5. Zone maintenance and transfers

Part of the job of a zone administrator is to maintain the zones at all
of the name servers which are authoritative for the zone.  When the
inevitable changes are made, they must be distributed to all of the name
servers.  While this distribution can be accomplished using FTP or some
other ad hoc procedure, the preferred method is the zone transfer part
of the DNS protocol.

The general model of automatic zone transfer or refreshing is that one
of the name servers is the master or primary for the zone.  Changes are
coordinated at the primary, typically by editing a master file for the
zone.  After editing, the administrator signals the master server to
load the new zone.  The other non-master or secondary servers for the
zone periodically check for changes (at a selectable interval) and
obtain new zone copies when changes have been made.

To detect changes, secondaries just check the SERIAL field of the SOA
for the zone.  In addition to whatever other changes are made, the
SERIAL field in the SOA of the zone is always advanced whenever any
change is made to the zone.  The advancing can be a simple increment, or
could be based on the write date and time of the master file, etc.  The
purpose is to make it possible to determine which of two copies of a
zone is more recent by comparing serial numbers.  Serial number advances
and comparisons use sequence space arithmetic, so there is a theoretic
limit on how fast a zone can be updated, basically that old copies must
die out before the serial number covers half of its 32 bit range.  In
practice, the only concern is that the compare operation deals properly
with comparisons around the boundary between the most positive and most
negative 32 bit numbers.

The periodic polling of the secondary servers is controlled by
parameters in the SOA RR for the zone, which set the minimum acceptable
polling intervals.  The parameters are called REFRESH, RETRY, and
EXPIRE.  Whenever a new zone is loaded in a secondary, the secondary
waits REFRESH seconds before checking with the primary for a new serial.
If this check cannot be completed, new checks are started every RETRY
seconds.  The check is a simple query to the primary for the SOA RR of
the zone.  If the serial field in the secondary's zone copy is equal to
the serial returned by the primary, then no changes have occurred, and
the REFRESH interval wait is restarted.  If the secondary finds it
impossible to perform a serial check for the EXPIRE interval, it must
assume that its copy of the zone is obsolete an discard it.

When the poll shows that the zone has changed, then the secondary server
must request a zone transfer via an AXFR request for the zone.  The AXFR
may cause an error, such as refused, but normally is answered by a
sequence of response messages.  The first and last messages must contain



Mockapetris                                                    [Page 28]

RFC 1034             Domain Concepts and Facilities        November 1987


the data for the top authoritative node of the zone.  Intermediate
messages carry all of the other RRs from the zone, including both
authoritative and non-authoritative RRs.  The stream of messages allows
the secondary to construct a copy of the zone.  Because accuracy is
essential, TCP or some other reliable protocol must be used for AXFR
requests.

Each secondary server is required to perform the following operations
against the master, but may also optionally perform these operations
against other secondary servers.  This strategy can improve the transfer
process when the primary is unavailable due to host downtime or network
problems, or when a secondary server has better network access to an
"intermediate" secondary than to the primary.

5. RESOLVERS

5.1. Introduction

Resolvers are programs that interface user programs to domain name
servers.  In the simplest case, a resolver receives a request from a
user program (e.g., mail programs, TELNET, FTP) in the form of a
subroutine call, system call etc., and returns the desired information
in a form compatible with the local host's data formats.

The resolver is located on the same machine as the program that requests
the resolver's services, but it may need to consult name servers on
other hosts.  Because a resolver may need to consult several name
servers, or may have the requested information in a local cache, the
amount of time that a resolver will take to complete can vary quite a
bit, from milliseconds to several seconds.

A very important goal of the resolver is to eliminate network delay and
name server load from most requests by answering them from its cache of
prior results.  It follows that caches which are shared by multiple
processes, users, machines, etc., are more efficient than non-shared
caches.

5.2. Client-resolver interface

5.2.1. Typical functions

The client interface to the resolver is influenced by the local host's
conventions, but the typical resolver-client interface has three
functions:

   1. Host name to host address translation.

      This function is often defined to mimic a previous HOSTS.TXT



Mockapetris                                                    [Page 29]

RFC 1034             Domain Concepts and Facilities        November 1987


      based function.  Given a character string, the caller wants
      one or more 32 bit IP addresses.  Under the DNS, it
      translates into a request for type A RRs.  Since the DNS does
      not preserve the order of RRs, this function may choose to
      sort the returned addresses or select the "best" address if
      the service returns only one choice to the client.  Note that
      a multiple address return is recommended, but a single
      address may be the only way to emulate prior HOSTS.TXT
      services.

   2. Host address to host name translation

      This function will often follow the form of previous
      functions.  Given a 32 bit IP address, the caller wants a
      character string.  The octets of the IP address are reversed,
      used as name components, and suffixed with "IN-ADDR.ARPA".  A
      type PTR query is used to get the RR with the primary name of
      the host.  For example, a request for the host name
      corresponding to IP address 1.2.3.4 looks for PTR RRs for
      domain name "4.3.2.1.IN-ADDR.ARPA".

   3. General lookup function

      This function retrieves arbitrary information from the DNS,
      and has no counterpart in previous systems.  The caller
      supplies a QNAME, QTYPE, and QCLASS, and wants all of the
      matching RRs.  This function will often use the DNS format
      for all RR data instead of the local host's, and returns all
      RR content (e.g., TTL) instead of a processed form with local
      quoting conventions.

When the resolver performs the indicated function, it usually has one of
the following results to pass back to the client:

   - One or more RRs giving the requested data.

     In this case the resolver returns the answer in the
     appropriate format.

   - A name error (NE).

     This happens when the referenced name does not exist.  For
     example, a user may have mistyped a host name.

   - A data not found error.

     This happens when the referenced name exists, but data of the
     appropriate type does not.  For example, a host address



Mockapetris                                                    [Page 30]

RFC 1034             Domain Concepts and Facilities        November 1987


     function applied to a mailbox name would return this error
     since the name exists, but no address RR is present.

It is important to note that the functions for translating between host
names and addresses may combine the "name error" and "data not found"
error conditions into a single type of error return, but the general
function should not.  One reason for this is that applications may ask
first for one type of information about a name followed by a second
request to the same name for some other type of information; if the two
errors are combined, then useless queries may slow the application.

5.2.2. Aliases

While attempting to resolve a particular request, the resolver may find
that the name in question is an alias.  For example, the resolver might
find that the name given for host name to address translation is an
alias when it finds the CNAME RR.  If possible, the alias condition
should be signalled back from the resolver to the client.

In most cases a resolver simply restarts the query at the new name when
it encounters a CNAME.  However, when performing the general function,
the resolver should not pursue aliases when the CNAME RR matches the
query type.  This allows queries which ask whether an alias is present.
For example, if the query type is CNAME, the user is interested in the
CNAME RR itself, and not the RRs at the name it points to.

Several special conditions can occur with aliases.  Multiple levels of
aliases should be avoided due to their lack of efficiency, but should
not be signalled as an error.  Alias loops and aliases which point to
non-existent names should be caught and an error condition passed back
to the client.

5.2.3. Temporary failures

In a less than perfect world, all resolvers will occasionally be unable
to resolve a particular request.  This condition can be caused by a
resolver which becomes separated from the rest of the network due to a
link failure or gateway problem, or less often by coincident failure or
unavailability of all servers for a particular domain.

It is essential that this sort of condition should not be signalled as a
name or data not present error to applications.  This sort of behavior
is annoying to humans, and can wreak havoc when mail systems use the
DNS.

While in some cases it is possible to deal with such a temporary problem
by blocking the request indefinitely, this is usually not a good choice,
particularly when the client is a server process that could move on to



Mockapetris                                                    [Page 31]

RFC 1034             Domain Concepts and Facilities        November 1987


other tasks.  The recommended solution is to always have temporary
failure as one of the possible results of a resolver function, even
though this may make emulation of existing HOSTS.TXT functions more
difficult.

5.3. Resolver internals

Every resolver implementation uses slightly different algorithms, and
typically spends much more logic dealing with errors of various sorts
than typical occurances.  This section outlines a recommended basic
strategy for resolver operation, but leaves details to [RFC-1035].

5.3.1. Stub resolvers

One option for implementing a resolver is to move the resolution
function out of the local machine and into a name server which supports
recursive queries.  This can provide an easy method of providing domain
service in a PC which lacks the resources to perform the resolver
function, or can centralize the cache for a whole local network or
organization.

All that the remaining stub needs is a list of name server addresses
that will perform the recursive requests.  This type of resolver
presumably needs the information in a configuration file, since it
probably lacks the sophistication to locate it in the domain database.
The user also needs to verify that the listed servers will perform the
recursive service; a name server is free to refuse to perform recursive
services for any or all clients.  The user should consult the local
system administrator to find name servers willing to perform the
service.

This type of service suffers from some drawbacks.  Since the recursive
requests may take an arbitrary amount of time to perform, the stub may
have difficulty optimizing retransmission intervals to deal with both
lost UDP packets and dead servers; the name server can be easily
overloaded by too zealous a stub if it interprets retransmissions as new
requests.  Use of TCP may be an answer, but TCP may well place burdens
on the host's capabilities which are similar to those of a real
resolver.

5.3.2. Resources

In addition to its own resources, the resolver may also have shared
access to zones maintained by a local name server.  This gives the
resolver the advantage of more rapid access, but the resolver must be
careful to never let cached information override zone data.  In this
discussion the term "local information" is meant to mean the union of
the cache and such shared zones, with the understanding that



Mockapetris                                                    [Page 32]

RFC 1034             Domain Concepts and Facilities        November 1987


authoritative data is always used in preference to cached data when both
are present.

The following resolver algorithm assumes that all functions have been
converted to a general lookup function, and uses the following data
structures to represent the state of a request in progress in the
resolver:

SNAME           the domain name we are searching for.

STYPE           the QTYPE of the search request.

SCLASS          the QCLASS of the search request.

SLIST           a structure which describes the name servers and the
                zone which the resolver is currently trying to query.
                This structure keeps track of the resolver's current
                best guess about which name servers hold the desired
                information; it is updated when arriving information
                changes the guess.  This structure includes the
                equivalent of a zone name, the known name servers for
                the zone, the known addresses for the name servers, and
                history information which can be used to suggest which
                server is likely to be the best one to try next.  The
                zone name equivalent is a match count of the number of
                labels from the root down which SNAME has in common with
                the zone being queried; this is used as a measure of how
                "close" the resolver is to SNAME.

SBELT           a "safety belt" structure of the same form as SLIST,
                which is initialized from a configuration file, and
                lists servers which should be used when the resolver
                doesn't have any local information to guide name server
                selection.  The match count will be -1 to indicate that
                no labels are known to match.

CACHE           A structure which stores the results from previous
                responses.  Since resolvers are responsible for
                discarding old RRs whose TTL has expired, most
                implementations convert the interval specified in
                arriving RRs to some sort of absolute time when the RR
                is stored in the cache.  Instead of counting the TTLs
                down individually, the resolver just ignores or discards
                old RRs when it runs across them in the course of a
                search, or discards them during periodic sweeps to
                reclaim the memory consumed by old RRs.





Mockapetris                                                    [Page 33]

RFC 1034             Domain Concepts and Facilities        November 1987


5.3.3. Algorithm

The top level algorithm has four steps:

   1. See if the answer is in local information, and if so return
      it to the client.

   2. Find the best servers to ask.

   3. Send them queries until one returns a response.

   4. Analyze the response, either:

         a. if the response answers the question or contains a name
            error, cache the data as well as returning it back to
            the client.

         b. if the response contains a better delegation to other
            servers, cache the delegation information, and go to
            step 2.

         c. if the response shows a CNAME and that is not the
            answer itself, cache the CNAME, change the SNAME to the
            canonical name in the CNAME RR and go to step 1.

         d. if the response shows a servers failure or other
            bizarre contents, delete the server from the SLIST and
            go back to step 3.

Step 1 searches the cache for the desired data. If the data is in the
cache, it is assumed to be good enough for normal use.  Some resolvers
have an option at the user interface which will force the resolver to
ignore the cached data and consult with an authoritative server.  This
is not recommended as the default.  If the resolver has direct access to
a name server's zones, it should check to see if the desired data is
present in authoritative form, and if so, use the authoritative data in
preference to cached data.

Step 2 looks for a name server to ask for the required data.  The
general strategy is to look for locally-available name server RRs,
starting at SNAME, then the parent domain name of SNAME, the
grandparent, and so on toward the root.  Thus if SNAME were
Mockapetris.ISI.EDU, this step would look for NS RRs for
Mockapetris.ISI.EDU, then ISI.EDU, then EDU, and then . (the root).
These NS RRs list the names of hosts for a zone at or above SNAME.  Copy
the names into SLIST.  Set up their addresses using local data.  It may
be the case that the addresses are not available.  The resolver has many
choices here; the best is to start parallel resolver processes looking



Mockapetris                                                    [Page 34]

RFC 1034             Domain Concepts and Facilities        November 1987


for the addresses while continuing onward with the addresses which are
available.  Obviously, the design choices and options are complicated
and a function of the local host's capabilities.  The recommended
priorities for the resolver designer are:

   1. Bound the amount of work (packets sent, parallel processes
      started) so that a request can't get into an infinite loop or
      start off a chain reaction of requests or queries with other
      implementations EVEN IF SOMEONE HAS INCORRECTLY CONFIGURED
      SOME DATA.

   2. Get back an answer if at all possible.

   3. Avoid unnecessary transmissions.

   4. Get the answer as quickly as possible.

If the search for NS RRs fails, then the resolver initializes SLIST from
the safety belt SBELT.  The basic idea is that when the resolver has no
idea what servers to ask, it should use information from a configuration
file that lists several servers which are expected to be helpful.
Although there are special situations, the usual choice is two of the
root servers and two of the servers for the host's domain.  The reason
for two of each is for redundancy.  The root servers will provide
eventual access to all of the domain space.  The two local servers will
allow the resolver to continue to resolve local names if the local
network becomes isolated from the internet due to gateway or link
failure.

In addition to the names and addresses of the servers, the SLIST data
structure can be sorted to use the best servers first, and to insure
that all addresses of all servers are used in a round-robin manner.  The
sorting can be a simple function of preferring addresses on the local
network over others, or may involve statistics from past events, such as
previous response times and batting averages.

Step 3 sends out queries until a response is received.  The strategy is
to cycle around all of the addresses for all of the servers with a
timeout between each transmission.  In practice it is important to use
all addresses of a multihomed host, and too aggressive a retransmission
policy actually slows response when used by multiple resolvers
contending for the same name server and even occasionally for a single
resolver.  SLIST typically contains data values to control the timeouts
and keep track of previous transmissions.

Step 4 involves analyzing responses.  The resolver should be highly
paranoid in its parsing of responses.  It should also check that the
response matches the query it sent using the ID field in the response.



Mockapetris                                                    [Page 35]

RFC 1034             Domain Concepts and Facilities        November 1987


The ideal answer is one from a server authoritative for the query which
either gives the required data or a name error.  The data is passed back
to the user and entered in the cache for future use if its TTL is
greater than zero.

If the response shows a delegation, the resolver should check to see
that the delegation is "closer" to the answer than the servers in SLIST
are.  This can be done by comparing the match count in SLIST with that
computed from SNAME and the NS RRs in the delegation.  If not, the reply
is bogus and should be ignored.  If the delegation is valid the NS
delegation RRs and any address RRs for the servers should be cached.
The name servers are entered in the SLIST, and the search is restarted.

If the response contains a CNAME, the search is restarted at the CNAME
unless the response has the data for the canonical name or if the CNAME
is the answer itself.

Details and implementation hints can be found in [RFC-1035].

6. A SCENARIO

In our sample domain space, suppose we wanted separate administrative
control for the root, MIL, EDU, MIT.EDU and ISI.EDU zones.  We might
allocate name servers as follows:


                                   |(C.ISI.EDU,SRI-NIC.ARPA
                                   | A.ISI.EDU)
             +---------------------+------------------+
             |                     |                  |
            MIL                   EDU                ARPA
             |(SRI-NIC.ARPA,       |(SRI-NIC.ARPA,    |
             | A.ISI.EDU           | C.ISI.EDU)       |
       +-----+-----+               |     +------+-----+-----+
       |     |     |               |     |      |           |
      BRL  NOSC  DARPA             |  IN-ADDR  SRI-NIC     ACC
                                   |
       +--------+------------------+---------------+--------+
       |        |                  |               |        |
      UCI      MIT                 |              UDEL     YALE
                |(XX.LCS.MIT.EDU, ISI
                |ACHILLES.MIT.EDU) |(VAXA.ISI.EDU,VENERA.ISI.EDU,
            +---+---+              | A.ISI.EDU)
            |       |              |
           LCS   ACHILLES +--+-----+-----+--------+
            |             |  |     |     |        |
            XX            A  C   VAXA  VENERA Mockapetris




Mockapetris                                                    [Page 36]

RFC 1034             Domain Concepts and Facilities        November 1987


In this example, the authoritative name server is shown in parentheses
at the point in the domain tree at which is assumes control.

Thus the root name servers are on C.ISI.EDU, SRI-NIC.ARPA, and
A.ISI.EDU.  The MIL domain is served by SRI-NIC.ARPA and A.ISI.EDU.  The
EDU domain is served by SRI-NIC.ARPA. and C.ISI.EDU.  Note that servers
may have zones which are contiguous or disjoint.  In this scenario,
C.ISI.EDU has contiguous zones at the root and EDU domains.  A.ISI.EDU
has contiguous zones at the root and MIL domains, but also has a non-
contiguous zone at ISI.EDU.

6.1. C.ISI.EDU name server

C.ISI.EDU is a name server for the root, MIL, and EDU domains of the IN
class, and would have zones for these domains.  The zone data for the
root domain might be:

    .       IN      SOA     SRI-NIC.ARPA. HOSTMASTER.SRI-NIC.ARPA. (
                            870611          ;serial
                            1800            ;refresh every 30 min
                            300             ;retry every 5 min
                            604800          ;expire after a week
                            86400)          ;minimum of a day
                    NS      A.ISI.EDU.
                    NS      C.ISI.EDU.
                    NS      SRI-NIC.ARPA.

    MIL.    86400   NS      SRI-NIC.ARPA.
            86400   NS      A.ISI.EDU.

    EDU.    86400   NS      SRI-NIC.ARPA.
            86400   NS      C.ISI.EDU.

    SRI-NIC.ARPA.   A       26.0.0.73
                    A       10.0.0.51
                    MX      0 SRI-NIC.ARPA.
                    HINFO   DEC-2060 TOPS20

    ACC.ARPA.       A       26.6.0.65
                    HINFO   PDP-11/70 UNIX
                    MX      10 ACC.ARPA.

    USC-ISIC.ARPA.  CNAME   C.ISI.EDU.

    73.0.0.26.IN-ADDR.ARPA.  PTR    SRI-NIC.ARPA.
    65.0.6.26.IN-ADDR.ARPA.  PTR    ACC.ARPA.
    51.0.0.10.IN-ADDR.ARPA.  PTR    SRI-NIC.ARPA.
    52.0.0.10.IN-ADDR.ARPA.  PTR    C.ISI.EDU.



Mockapetris                                                    [Page 37]

RFC 1034             Domain Concepts and Facilities        November 1987


    103.0.3.26.IN-ADDR.ARPA. PTR    A.ISI.EDU.

    A.ISI.EDU. 86400 A      26.3.0.103
    C.ISI.EDU. 86400 A      10.0.0.52

This data is represented as it would be in a master file.  Most RRs are
single line entries; the sole exception here is the SOA RR, which uses
"(" to start a multi-line RR and ")" to show the end of a multi-line RR.
Since the class of all RRs in a zone must be the same, only the first RR
in a zone need specify the class.  When a name server loads a zone, it
forces the TTL of all authoritative RRs to be at least the MINIMUM field
of the SOA, here 86400 seconds, or one day.  The NS RRs marking
delegation of the MIL and EDU domains, together with the glue RRs for
the servers host addresses, are not part of the authoritative data in
the zone, and hence have explicit TTLs.

Four RRs are attached to the root node: the SOA which describes the root
zone and the 3 NS RRs which list the name servers for the root.  The
data in the SOA RR describes the management of the zone.  The zone data
is maintained on host SRI-NIC.ARPA, and the responsible party for the
zone is HOSTMASTER@SRI-NIC.ARPA.  A key item in the SOA is the 86400
second minimum TTL, which means that all authoritative data in the zone
has at least that TTL, although higher values may be explicitly
specified.

The NS RRs for the MIL and EDU domains mark the boundary between the
root zone and the MIL and EDU zones.  Note that in this example, the
lower zones happen to be supported by name servers which also support
the root zone.

The master file for the EDU zone might be stated relative to the origin
EDU.  The zone data for the EDU domain might be:

    EDU.  IN SOA SRI-NIC.ARPA. HOSTMASTER.SRI-NIC.ARPA. (
                            870729 ;serial
                            1800 ;refresh every 30 minutes
                            300 ;retry every 5 minutes
                            604800 ;expire after a week
                            86400 ;minimum of a day
                            )
                    NS SRI-NIC.ARPA.
                    NS C.ISI.EDU.

    UCI 172800 NS ICS.UCI
                    172800 NS ROME.UCI
    ICS.UCI 172800 A 192.5.19.1
    ROME.UCI 172800 A 192.5.19.31




Mockapetris                                                    [Page 38]

RFC 1034             Domain Concepts and Facilities        November 1987


    ISI 172800 NS VAXA.ISI
                    172800 NS A.ISI
                    172800 NS VENERA.ISI.EDU.
    VAXA.ISI 172800 A 10.2.0.27
                    172800 A 128.9.0.33
    VENERA.ISI.EDU. 172800 A 10.1.0.52
                    172800 A 128.9.0.32
    A.ISI 172800 A 26.3.0.103

    UDEL.EDU.  172800 NS LOUIE.UDEL.EDU.
                    172800 NS UMN-REI-UC.ARPA.
    LOUIE.UDEL.EDU. 172800 A 10.0.0.96
                    172800 A 192.5.39.3

    YALE.EDU.  172800 NS YALE.ARPA.
    YALE.EDU.  172800 NS YALE-BULLDOG.ARPA.

    MIT.EDU.  43200 NS XX.LCS.MIT.EDU.
                      43200 NS ACHILLES.MIT.EDU.
    XX.LCS.MIT.EDU.  43200 A 10.0.0.44
    ACHILLES.MIT.EDU. 43200 A 18.72.0.8

Note the use of relative names here.  The owner name for the ISI.EDU. is
stated using a relative name, as are two of the name server RR contents.
Relative and absolute domain names may be freely intermixed in a master

6.2. Example standard queries

The following queries and responses illustrate name server behavior.
Unless otherwise noted, the queries do not have recursion desired (RD)
in the header.  Note that the answers to non-recursive queries do depend
on the server being asked, but do not depend on the identity of the
requester.


















Mockapetris                                                    [Page 39]

RFC 1034             Domain Concepts and Facilities        November 1987


6.2.1. QNAME=SRI-NIC.ARPA, QTYPE=A

The query would look like:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY                                     |
               +---------------------------------------------------+
    Question   | QNAME=SRI-NIC.ARPA., QCLASS=IN, QTYPE=A           |
               +---------------------------------------------------+
    Answer     | <empty>                                           |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+

The response from C.ISI.EDU would be:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE, AA                       |
               +---------------------------------------------------+
    Question   | QNAME=SRI-NIC.ARPA., QCLASS=IN, QTYPE=A           |
               +---------------------------------------------------+
    Answer     | SRI-NIC.ARPA. 86400 IN A 26.0.0.73                |
               |               86400 IN A 10.0.0.51                |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+

The header of the response looks like the header of the query, except
that the RESPONSE bit is set, indicating that this message is a
response, not a query, and the Authoritative Answer (AA) bit is set
indicating that the address RRs in the answer section are from
authoritative data.  The question section of the response matches the
question section of the query.














Mockapetris                                                    [Page 40]

RFC 1034             Domain Concepts and Facilities        November 1987


If the same query was sent to some other server which was not
authoritative for SRI-NIC.ARPA, the response might be:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY,RESPONSE                            |
               +---------------------------------------------------+
    Question   | QNAME=SRI-NIC.ARPA., QCLASS=IN, QTYPE=A           |
               +---------------------------------------------------+
    Answer     | SRI-NIC.ARPA. 1777 IN A 10.0.0.51                 |
               |               1777 IN A 26.0.0.73                 |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+

This response is different from the previous one in two ways: the header
does not have AA set, and the TTLs are different.  The inference is that
the data did not come from a zone, but from a cache.  The difference
between the authoritative TTL and the TTL here is due to aging of the
data in a cache.  The difference in ordering of the RRs in the answer
section is not significant.

6.2.2. QNAME=SRI-NIC.ARPA, QTYPE=*

A query similar to the previous one, but using a QTYPE of *, would
receive the following response from C.ISI.EDU:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE, AA                       |
               +---------------------------------------------------+
    Question   | QNAME=SRI-NIC.ARPA., QCLASS=IN, QTYPE=*           |
               +---------------------------------------------------+
    Answer     | SRI-NIC.ARPA. 86400 IN  A     26.0.0.73           |
               |                         A     10.0.0.51           |
               |                         MX    0 SRI-NIC.ARPA.     |
               |                         HINFO DEC-2060 TOPS20     |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+









Mockapetris                                                    [Page 41]

RFC 1034             Domain Concepts and Facilities        November 1987


If a similar query was directed to two name servers which are not
authoritative for SRI-NIC.ARPA, the responses might be:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE                           |
               +---------------------------------------------------+
    Question   | QNAME=SRI-NIC.ARPA., QCLASS=IN, QTYPE=*           |
               +---------------------------------------------------+
    Answer     | SRI-NIC.ARPA. 12345 IN     A       26.0.0.73      |
               |                            A       10.0.0.51      |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+

and

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE                           |
               +---------------------------------------------------+
    Question   | QNAME=SRI-NIC.ARPA., QCLASS=IN, QTYPE=*           |
               +---------------------------------------------------+
    Answer     | SRI-NIC.ARPA. 1290 IN HINFO  DEC-2060 TOPS20      |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+

Neither of these answers have AA set, so neither response comes from
authoritative data.  The different contents and different TTLs suggest
that the two servers cached data at different times, and that the first
server cached the response to a QTYPE=A query and the second cached the
response to a HINFO query.
















Mockapetris                                                    [Page 42]

RFC 1034             Domain Concepts and Facilities        November 1987


6.2.3. QNAME=SRI-NIC.ARPA, QTYPE=MX

This type of query might be result from a mailer trying to look up
routing information for the mail destination HOSTMASTER@SRI-NIC.ARPA.
The response from C.ISI.EDU would be:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE, AA                       |
               +---------------------------------------------------+
    Question   | QNAME=SRI-NIC.ARPA., QCLASS=IN, QTYPE=MX          |
               +---------------------------------------------------+
    Answer     | SRI-NIC.ARPA. 86400 IN     MX      0 SRI-NIC.ARPA.|
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | SRI-NIC.ARPA. 86400 IN     A       26.0.0.73      |
               |                            A       10.0.0.51      |
               +---------------------------------------------------+

This response contains the MX RR in the answer section of the response.
The additional section contains the address RRs because the name server
at C.ISI.EDU guesses that the requester will need the addresses in order
to properly use the information carried by the MX.

6.2.4. QNAME=SRI-NIC.ARPA, QTYPE=NS

C.ISI.EDU would reply to this query with:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE, AA                       |
               +---------------------------------------------------+
    Question   | QNAME=SRI-NIC.ARPA., QCLASS=IN, QTYPE=NS          |
               +---------------------------------------------------+
    Answer     | <empty>                                           |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+

The only difference between the response and the query is the AA and
RESPONSE bits in the header.  The interpretation of this response is
that the server is authoritative for the name, and the name exists, but
no RRs of type NS are present there.

6.2.5. QNAME=SIR-NIC.ARPA, QTYPE=A

If a user mistyped a host name, we might see this type of query.



Mockapetris                                                    [Page 43]

RFC 1034             Domain Concepts and Facilities        November 1987


C.ISI.EDU would answer it with:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE, AA, RCODE=NE             |
               +---------------------------------------------------+
    Question   | QNAME=SIR-NIC.ARPA., QCLASS=IN, QTYPE=A           |
               +---------------------------------------------------+
    Answer     | <empty>                                           |
               +---------------------------------------------------+
    Authority  | . SOA SRI-NIC.ARPA. HOSTMASTER.SRI-NIC.ARPA.      |
               |       870611 1800 300 604800 86400                |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+

This response states that the name does not exist.  This condition is
signalled in the response code (RCODE) section of the header.

The SOA RR in the authority section is the optional negative caching
information which allows the resolver using this response to assume that
the name will not exist for the SOA MINIMUM (86400) seconds.

6.2.6. QNAME=BRL.MIL, QTYPE=A

If this query is sent to C.ISI.EDU, the reply would be:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE                           |
               +---------------------------------------------------+
    Question   | QNAME=BRL.MIL, QCLASS=IN, QTYPE=A                 |
               +---------------------------------------------------+
    Answer     | <empty>                                           |
               +---------------------------------------------------+
    Authority  | MIL.             86400 IN NS       SRI-NIC.ARPA.  |
               |                  86400    NS       A.ISI.EDU.     |
               +---------------------------------------------------+
    Additional | A.ISI.EDU.                A        26.3.0.103     |
               | SRI-NIC.ARPA.             A        26.0.0.73      |
               |                           A        10.0.0.51      |
               +---------------------------------------------------+

This response has an empty answer section, but is not authoritative, so
it is a referral.  The name server on C.ISI.EDU, realizing that it is
not authoritative for the MIL domain, has referred the requester to
servers on A.ISI.EDU and SRI-NIC.ARPA, which it knows are authoritative
for the MIL domain.





Mockapetris                                                    [Page 44]

RFC 1034             Domain Concepts and Facilities        November 1987


6.2.7. QNAME=USC-ISIC.ARPA, QTYPE=A

The response to this query from A.ISI.EDU would be:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE, AA                       |
               +---------------------------------------------------+
    Question   | QNAME=USC-ISIC.ARPA., QCLASS=IN, QTYPE=A          |
               +---------------------------------------------------+
    Answer     | USC-ISIC.ARPA. 86400 IN CNAME      C.ISI.EDU.     |
               | C.ISI.EDU.     86400 IN A          10.0.0.52      |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+

Note that the AA bit in the header guarantees that the data matching
QNAME is authoritative, but does not say anything about whether the data
for C.ISI.EDU is authoritative.  This complete reply is possible because
A.ISI.EDU happens to be authoritative for both the ARPA domain where
USC-ISIC.ARPA is found and the ISI.EDU domain where C.ISI.EDU data is
found.

If the same query was sent to C.ISI.EDU, its response might be the same
as shown above if it had its own address in its cache, but might also
be:
























Mockapetris                                                    [Page 45]

RFC 1034             Domain Concepts and Facilities        November 1987


               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE, AA                       |
               +---------------------------------------------------+
    Question   | QNAME=USC-ISIC.ARPA., QCLASS=IN, QTYPE=A          |
               +---------------------------------------------------+
    Answer     | USC-ISIC.ARPA.   86400 IN CNAME   C.ISI.EDU.      |
               +---------------------------------------------------+
    Authority  | ISI.EDU.        172800 IN NS      VAXA.ISI.EDU.   |
               |                           NS      A.ISI.EDU.      |
               |                           NS      VENERA.ISI.EDU. |
               +---------------------------------------------------+
    Additional | VAXA.ISI.EDU.   172800    A       10.2.0.27       |
               |                 172800    A       128.9.0.33      |
               | VENERA.ISI.EDU. 172800    A       10.1.0.52       |
               |                 172800    A       128.9.0.32      |
               | A.ISI.EDU.      172800    A       26.3.0.103      |
               +---------------------------------------------------+

This reply contains an authoritative reply for the alias USC-ISIC.ARPA,
plus a referral to the name servers for ISI.EDU.  This sort of reply
isn't very likely given that the query is for the host name of the name
server being asked, but would be common for other aliases.

6.2.8. QNAME=USC-ISIC.ARPA, QTYPE=CNAME

If this query is sent to either A.ISI.EDU or C.ISI.EDU, the reply would
be:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE, AA                       |
               +---------------------------------------------------+
    Question   | QNAME=USC-ISIC.ARPA., QCLASS=IN, QTYPE=A          |
               +---------------------------------------------------+
    Answer     | USC-ISIC.ARPA. 86400 IN CNAME      C.ISI.EDU.     |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+

Because QTYPE=CNAME, the CNAME RR itself answers the query, and the name
server doesn't attempt to look up anything for C.ISI.EDU.  (Except
possibly for the additional section.)

6.3. Example resolution

The following examples illustrate the operations a resolver must perform
for its client.  We assume that the resolver is starting without a



Mockapetris                                                    [Page 46]

RFC 1034             Domain Concepts and Facilities        November 1987


cache, as might be the case after system boot.  We further assume that
the system is not one of the hosts in the data and that the host is
located somewhere on net 26, and that its safety belt (SBELT) data
structure has the following information:

    Match count = -1
    SRI-NIC.ARPA.   26.0.0.73       10.0.0.51
    A.ISI.EDU.      26.3.0.103

This information specifies servers to try, their addresses, and a match
count of -1, which says that the servers aren't very close to the
target.  Note that the -1 isn't supposed to be an accurate closeness
measure, just a value so that later stages of the algorithm will work.

The following examples illustrate the use of a cache, so each example
assumes that previous requests have completed.

6.3.1. Resolve MX for ISI.EDU.

Suppose the first request to the resolver comes from the local mailer,
which has mail for PVM@ISI.EDU.  The mailer might then ask for type MX
RRs for the domain name ISI.EDU.

The resolver would look in its cache for MX RRs at ISI.EDU, but the
empty cache wouldn't be helpful.  The resolver would recognize that it
needed to query foreign servers and try to determine the best servers to
query.  This search would look for NS RRs for the domains ISI.EDU, EDU,
and the root.  These searches of the cache would also fail.  As a last
resort, the resolver would use the information from the SBELT, copying
it into its SLIST structure.

At this point the resolver would need to pick one of the three available
addresses to try.  Given that the resolver is on net 26, it should
choose either 26.0.0.73 or 26.3.0.103 as its first choice.  It would
then send off a query of the form:
















Mockapetris                                                    [Page 47]

RFC 1034             Domain Concepts and Facilities        November 1987


               +---------------------------------------------------+
    Header     | OPCODE=SQUERY                                     |
               +---------------------------------------------------+
    Question   | QNAME=ISI.EDU., QCLASS=IN, QTYPE=MX               |
               +---------------------------------------------------+
    Answer     | <empty>                                           |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+

The resolver would then wait for a response to its query or a timeout.
If the timeout occurs, it would try different servers, then different
addresses of the same servers, lastly retrying addresses already tried.
It might eventually receive a reply from SRI-NIC.ARPA:

               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE                           |
               +---------------------------------------------------+
    Question   | QNAME=ISI.EDU., QCLASS=IN, QTYPE=MX               |
               +---------------------------------------------------+
    Answer     | <empty>                                           |
               +---------------------------------------------------+
    Authority  | ISI.EDU.        172800 IN NS       VAXA.ISI.EDU.  |
               |                           NS       A.ISI.EDU.     |
               |                           NS       VENERA.ISI.EDU.|
               +---------------------------------------------------+
    Additional | VAXA.ISI.EDU.   172800    A        10.2.0.27      |
               |                 172800    A        128.9.0.33     |
               | VENERA.ISI.EDU. 172800    A        10.1.0.52      |
               |                 172800    A        128.9.0.32     |
               | A.ISI.EDU.      172800    A        26.3.0.103     |
               +---------------------------------------------------+

The resolver would notice that the information in the response gave a
closer delegation to ISI.EDU than its existing SLIST (since it matches
three labels).  The resolver would then cache the information in this
response and use it to set up a new SLIST:

    Match count = 3
    A.ISI.EDU.      26.3.0.103
    VAXA.ISI.EDU.   10.2.0.27       128.9.0.33
    VENERA.ISI.EDU. 10.1.0.52       128.9.0.32

A.ISI.EDU appears on this list as well as the previous one, but that is
purely coincidental.  The resolver would again start transmitting and
waiting for responses.  Eventually it would get an answer:



Mockapetris                                                    [Page 48]

RFC 1034             Domain Concepts and Facilities        November 1987


               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE, AA                       |
               +---------------------------------------------------+
    Question   | QNAME=ISI.EDU., QCLASS=IN, QTYPE=MX               |
               +---------------------------------------------------+
    Answer     | ISI.EDU.                MX 10 VENERA.ISI.EDU.     |
               |                         MX 20 VAXA.ISI.EDU.       |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | VAXA.ISI.EDU.   172800  A  10.2.0.27              |
               |                 172800  A  128.9.0.33             |
               | VENERA.ISI.EDU. 172800  A  10.1.0.52              |
               |                 172800  A  128.9.0.32             |
               +---------------------------------------------------+

The resolver would add this information to its cache, and return the MX
RRs to its client.

6.3.2. Get the host name for address 26.6.0.65

The resolver would translate this into a request for PTR RRs for
65.0.6.26.IN-ADDR.ARPA.  This information is not in the cache, so the
resolver would look for foreign servers to ask.  No servers would match,
so it would use SBELT again.  (Note that the servers for the ISI.EDU
domain are in the cache, but ISI.EDU is not an ancestor of
65.0.6.26.IN-ADDR.ARPA, so the SBELT is used.)

Since this request is within the authoritative data of both servers in
SBELT, eventually one would return:





















Mockapetris                                                    [Page 49]

RFC 1034             Domain Concepts and Facilities        November 1987


               +---------------------------------------------------+
    Header     | OPCODE=SQUERY, RESPONSE, AA                       |
               +---------------------------------------------------+
    Question   | QNAME=65.0.6.26.IN-ADDR.ARPA.,QCLASS=IN,QTYPE=PTR |
               +---------------------------------------------------+
    Answer     | 65.0.6.26.IN-ADDR.ARPA.    PTR     ACC.ARPA.      |
               +---------------------------------------------------+
    Authority  | <empty>                                           |
               +---------------------------------------------------+
    Additional | <empty>                                           |
               +---------------------------------------------------+

6.3.3. Get the host address of poneria.ISI.EDU

This request would translate into a type A request for poneria.ISI.EDU.
The resolver would not find any cached data for this name, but would
find the NS RRs in the cache for ISI.EDU when it looks for foreign
servers to ask.  Using this data, it would construct a SLIST of the
form:

    Match count = 3

    A.ISI.EDU.      26.3.0.103
    VAXA.ISI.EDU.   10.2.0.27       128.9.0.33
    VENERA.ISI.EDU. 10.1.0.52

A.ISI.EDU is listed first on the assumption that the resolver orders its
choices by preference, and A.ISI.EDU is on the same network.

One of these servers would answer the query.

7. REFERENCES and BIBLIOGRAPHY

[Dyer 87]       Dyer, S., and F. Hsu, "Hesiod", Project Athena
                Technical Plan - Name Service, April 1987, version 1.9.

                Describes the fundamentals of the Hesiod name service.

[IEN-116]       J. Postel, "Internet Name Server", IEN-116,
                USC/Information Sciences Institute, August 1979.

                A name service obsoleted by the Domain Name System, but
                still in use.








Mockapetris                                                    [Page 50]

RFC 1034             Domain Concepts and Facilities        November 1987


[Quarterman 86] Quarterman, J., and J. Hoskins, "Notable Computer
                Networks",Communications of the ACM, October 1986,
                volume 29, number 10.

[RFC-742]       K. Harrenstien, "NAME/FINGER", RFC-742, Network
                Information Center, SRI International, December 1977.

[RFC-768]       J. Postel, "User Datagram Protocol", RFC-768,
                USC/Information Sciences Institute, August 1980.

[RFC-793]       J. Postel, "Transmission Control Protocol", RFC-793,
                USC/Information Sciences Institute, September 1981.

[RFC-799]       D. Mills, "Internet Name Domains", RFC-799, COMSAT,
                September 1981.

                Suggests introduction of a hierarchy in place of a flat
                name space for the Internet.

[RFC-805]       J. Postel, "Computer Mail Meeting Notes", RFC-805,
                USC/Information Sciences Institute, February 1982.

[RFC-810]       E. Feinler, K. Harrenstien, Z. Su, and V. White, "DOD
                Internet Host Table Specification", RFC-810, Network
                Information Center, SRI International, March 1982.

                Obsolete.  See RFC-952.

[RFC-811]       K. Harrenstien, V. White, and E. Feinler, "Hostnames
                Server", RFC-811, Network Information Center, SRI
                International, March 1982.

                Obsolete.  See RFC-953.

[RFC-812]       K. Harrenstien, and V. White, "NICNAME/WHOIS", RFC-812,
                Network Information Center, SRI International, March
                1982.

[RFC-819]       Z. Su, and J. Postel, "The Domain Naming Convention for
                Internet User Applications", RFC-819, Network
                Information Center, SRI International, August 1982.

                Early thoughts on the design of the domain system.
                Current implementation is completely different.

[RFC-821]       J. Postel, "Simple Mail Transfer Protocol", RFC-821,
                USC/Information Sciences Institute, August 1980.




Mockapetris                                                    [Page 51]

RFC 1034             Domain Concepts and Facilities        November 1987


[RFC-830]       Z. Su, "A Distributed System for Internet Name Service",
                RFC-830, Network Information Center, SRI International,
                October 1982.

                Early thoughts on the design of the domain system.
                Current implementation is completely different.

[RFC-882]       P. Mockapetris, "Domain names - Concepts and
                Facilities," RFC-882, USC/Information Sciences
                Institute, November 1983.

                Superceeded by this memo.

[RFC-883]       P. Mockapetris, "Domain names - Implementation and
                Specification," RFC-883, USC/Information Sciences
                Institute, November 1983.

                Superceeded by this memo.

[RFC-920]       J. Postel and J. Reynolds, "Domain Requirements",
                RFC-920, USC/Information Sciences Institute
                October 1984.

                Explains the naming scheme for top level domains.

[RFC-952]       K. Harrenstien, M. Stahl, E. Feinler, "DoD Internet Host
                Table Specification", RFC-952, SRI, October 1985.

                Specifies the format of HOSTS.TXT, the host/address
                table replaced by the DNS.

[RFC-953]       K. Harrenstien, M. Stahl, E. Feinler, "HOSTNAME Server",
                RFC-953, SRI, October 1985.

                This RFC contains the official specification of the
                hostname server protocol, which is obsoleted by the DNS.
                This TCP based protocol accesses information stored in
                the RFC-952 format, and is used to obtain copies of the
                host table.

[RFC-973]       P. Mockapetris, "Domain System Changes and
                Observations", RFC-973, USC/Information Sciences
                Institute, January 1986.

                Describes changes to RFC-882 and RFC-883 and reasons for
                them.  Now obsolete.





Mockapetris                                                    [Page 52]

RFC 1034             Domain Concepts and Facilities        November 1987


[RFC-974]       C. Partridge, "Mail routing and the domain system",
                RFC-974, CSNET CIC BBN Labs, January 1986.

                Describes the transition from HOSTS.TXT based mail
                addressing to the more powerful MX system used with the
                domain system.

[RFC-1001]      NetBIOS Working Group, "Protocol standard for a NetBIOS
                service on a TCP/UDP transport: Concepts and Methods",
                RFC-1001, March 1987.

                This RFC and RFC-1002 are a preliminary design for
                NETBIOS on top of TCP/IP which proposes to base NetBIOS
                name service on top of the DNS.

[RFC-1002]      NetBIOS Working Group, "Protocol standard for a NetBIOS
                service on a TCP/UDP transport: Detailed
                Specifications", RFC-1002, March 1987.

[RFC-1010]      J. Reynolds and J. Postel, "Assigned Numbers", RFC-1010,
                USC/Information Sciences Institute, May 1987

                Contains socket numbers and mnemonics for host names,
                operating systems, etc.

[RFC-1031]      W. Lazear, "MILNET Name Domain Transition", RFC-1031,
                November 1987.

                Describes a plan for converting the MILNET to the DNS.

[RFC-1032]      M. K. Stahl, "Establishing a Domain - Guidelines for
                Administrators", RFC-1032, November 1987.

                Describes the registration policies used by the NIC to
                administer the top level domains and delegate subzones.

[RFC-1033]      M. K. Lottor, "Domain Administrators Operations Guide",
                RFC-1033, November 1987.

                A cookbook for domain administrators.

[Solomon 82]    M. Solomon, L. Landweber, and D. Neuhengen, "The CSNET
                Name Server", Computer Networks, vol 6, nr 3, July 1982.

                Describes a name service for CSNET which is independent
                from the DNS and DNS use in the CSNET.





Mockapetris                                                    [Page 53]

RFC 1034             Domain Concepts and Facilities        November 1987


Index

          A   12
          Absolute names   8
          Aliases   14, 31
          Authority   6
          AXFR   17

          Case of characters   7
          CH   12
          CNAME   12, 13, 31
          Completion queries   18

          Domain name   6, 7

          Glue RRs   20

          HINFO   12

          IN   12
          Inverse queries   16
          Iterative   4

          Label   7

          Mailbox names   9
          MX   12

          Name error   27, 36
          Name servers   5, 17
          NE   30
          Negative caching   44
          NS   12

          Opcode   16

          PTR   12

          QCLASS   16
          QTYPE   16

          RDATA   13
          Recursive   4
          Recursive service   22
          Relative names   7
          Resolvers   6
          RR   12




Mockapetris                                                    [Page 54]

RFC 1034             Domain Concepts and Facilities        November 1987


          Safety belt   33
          Sections   16
          SOA   12
          Standard queries   22

          Status queries   18
          Stub resolvers   32

          TTL   12, 13

          Wildcards   25

          Zone transfers   28
          Zones   19





































Mockapetris                                                    [Page 55]

Network Working Group                                     P. Mockapetris
Request for Comments: 1035                                           ISI
                                                           November 1987
Obsoletes: RFCs 882, 883, 973

            DOMAIN NAMES - IMPLEMENTATION AND SPECIFICATION


1. STATUS OF THIS MEMO

This RFC describes the details of the domain system and protocol, and
assumes that the reader is familiar with the concepts discussed in a
companion RFC, "Domain Names - Concepts and Facilities" [RFC-1034].

The domain system is a mixture of functions and data types which are an
official protocol and functions and data types which are still
experimental.  Since the domain system is intentionally extensible, new
data types and experimental behavior should always be expected in parts
of the system beyond the official protocol.  The official protocol parts
include standard queries, responses and the Internet class RR data
formats (e.g., host addresses).  Since the previous RFC set, several
definitions have changed, so some previous definitions are obsolete.

Experimental or obsolete features are clearly marked in these RFCs, and
such information should be used with caution.

The reader is especially cautioned not to depend on the values which
appear in examples to be current or complete, since their purpose is
primarily pedagogical.  Distribution of this memo is unlimited.

                           Table of Contents

  1. STATUS OF THIS MEMO                                              1
  2. INTRODUCTION                                                     3
      2.1. Overview                                                   3
      2.2. Common configurations                                      4
      2.3. Conventions                                                7
          2.3.1. Preferred name syntax                                7
          2.3.2. Data Transmission Order                              8
          2.3.3. Character Case                                       9
          2.3.4. Size limits                                         10
  3. DOMAIN NAME SPACE AND RR DEFINITIONS                            10
      3.1. Name space definitions                                    10
      3.2. RR definitions                                            11
          3.2.1. Format                                              11
          3.2.2. TYPE values                                         12
          3.2.3. QTYPE values                                        12
          3.2.4. CLASS values                                        13



Mockapetris                                                     [Page 1]

RFC 1035        Domain Implementation and Specification    November 1987


          3.2.5. QCLASS values                                       13
      3.3. Standard RRs                                              13
          3.3.1. CNAME RDATA format                                  14
          3.3.2. HINFO RDATA format                                  14
          3.3.3. MB RDATA format (EXPERIMENTAL)                      14
          3.3.4. MD RDATA format (Obsolete)                          15
          3.3.5. MF RDATA format (Obsolete)                          15
          3.3.6. MG RDATA format (EXPERIMENTAL)                      16
          3.3.7. MINFO RDATA format (EXPERIMENTAL)                   16
          3.3.8. MR RDATA format (EXPERIMENTAL)                      17
          3.3.9. MX RDATA format                                     17
          3.3.10. NULL RDATA format (EXPERIMENTAL)                   17
          3.3.11. NS RDATA format                                    18
          3.3.12. PTR RDATA format                                   18
          3.3.13. SOA RDATA format                                   19
          3.3.14. TXT RDATA format                                   20
      3.4. ARPA Internet specific RRs                                20
          3.4.1. A RDATA format                                      20
          3.4.2. WKS RDATA format                                    21
      3.5. IN-ADDR.ARPA domain                                       22
      3.6. Defining new types, classes, and special namespaces       24
  4. MESSAGES                                                        25
      4.1. Format                                                    25
          4.1.1. Header section format                               26
          4.1.2. Question section format                             28
          4.1.3. Resource record format                              29
          4.1.4. Message compression                                 30
      4.2. Transport                                                 32
          4.2.1. UDP usage                                           32
          4.2.2. TCP usage                                           32
  5. MASTER FILES                                                    33
      5.1. Format                                                    33
      5.2. Use of master files to define zones                       35
      5.3. Master file example                                       36
  6. NAME SERVER IMPLEMENTATION                                      37
      6.1. Architecture                                              37
          6.1.1. Control                                             37
          6.1.2. Database                                            37
          6.1.3. Time                                                39
      6.2. Standard query processing                                 39
      6.3. Zone refresh and reload processing                        39
      6.4. Inverse queries (Optional)                                40
          6.4.1. The contents of inverse queries and responses       40
          6.4.2. Inverse query and response example                  41
          6.4.3. Inverse query processing                            42






Mockapetris                                                     [Page 2]

RFC 1035        Domain Implementation and Specification    November 1987


      6.5. Completion queries and responses                          42
  7. RESOLVER IMPLEMENTATION                                         43
      7.1. Transforming a user request into a query                  43
      7.2. Sending the queries                                       44
      7.3. Processing responses                                      46
      7.4. Using the cache                                           47
  8. MAIL SUPPORT                                                    47
      8.1. Mail exchange binding                                     48
      8.2. Mailbox binding (Experimental)                            48
  9. REFERENCES and BIBLIOGRAPHY                                     50
  Index                                                              54

2. INTRODUCTION

2.1. Overview

The goal of domain names is to provide a mechanism for naming resources
in such a way that the names are usable in different hosts, networks,
protocol families, internets, and administrative organizations.

From the user's point of view, domain names are useful as arguments to a
local agent, called a resolver, which retrieves information associated
with the domain name.  Thus a user might ask for the host address or
mail information associated with a particular domain name.  To enable
the user to request a particular type of information, an appropriate
query type is passed to the resolver with the domain name.  To the user,
the domain tree is a single information space; the resolver is
responsible for hiding the distribution of data among name servers from
the user.

From the resolver's point of view, the database that makes up the domain
space is distributed among various name servers.  Different parts of the
domain space are stored in different name servers, although a particular
data item will be stored redundantly in two or more name servers.  The
resolver starts with knowledge of at least one name server.  When the
resolver processes a user query it asks a known name server for the
information; in return, the resolver either receives the desired
information or a referral to another name server.  Using these
referrals, resolvers learn the identities and contents of other name
servers.  Resolvers are responsible for dealing with the distribution of
the domain space and dealing with the effects of name server failure by
consulting redundant databases in other servers.

Name servers manage two kinds of data.  The first kind of data held in
sets called zones; each zone is the complete database for a particular
"pruned" subtree of the domain space.  This data is called
authoritative.  A name server periodically checks to make sure that its
zones are up to date, and if not, obtains a new copy of updated zones



Mockapetris                                                     [Page 3]

RFC 1035        Domain Implementation and Specification    November 1987


from master files stored locally or in another name server.  The second
kind of data is cached data which was acquired by a local resolver.
This data may be incomplete, but improves the performance of the
retrieval process when non-local data is repeatedly accessed.  Cached
data is eventually discarded by a timeout mechanism.

This functional structure isolates the problems of user interface,
failure recovery, and distribution in the resolvers and isolates the
database update and refresh problems in the name servers.

2.2. Common configurations

A host can participate in the domain name system in a number of ways,
depending on whether the host runs programs that retrieve information
from the domain system, name servers that answer queries from other
hosts, or various combinations of both functions.  The simplest, and
perhaps most typical, configuration is shown below:

                 Local Host                        |  Foreign
                                                   |
    +---------+               +----------+         |  +--------+
    |         | user queries  |          |queries  |  |        |
    |  User   |-------------->|          |---------|->|Foreign |
    | Program |               | Resolver |         |  |  Name  |
    |         |<--------------|          |<--------|--| Server |
    |         | user responses|          |responses|  |        |
    +---------+               +----------+         |  +--------+
                                |     A            |
                cache additions |     | references |
                                V     |            |
                              +----------+         |
                              |  cache   |         |
                              +----------+         |

User programs interact with the domain name space through resolvers; the
format of user queries and user responses is specific to the host and
its operating system.  User queries will typically be operating system
calls, and the resolver and its cache will be part of the host operating
system.  Less capable hosts may choose to implement the resolver as a
subroutine to be linked in with every program that needs its services.
Resolvers answer user queries with information they acquire via queries
to foreign name servers and the local cache.

Note that the resolver may have to make several queries to several
different foreign name servers to answer a particular user query, and
hence the resolution of a user query may involve several network
accesses and an arbitrary amount of time.  The queries to foreign name
servers and the corresponding responses have a standard format described



Mockapetris                                                     [Page 4]

RFC 1035        Domain Implementation and Specification    November 1987


in this memo, and may be datagrams.

Depending on its capabilities, a name server could be a stand alone
program on a dedicated machine or a process or processes on a large
timeshared host.  A simple configuration might be:

                 Local Host                        |  Foreign
                                                   |
      +---------+                                  |
     /         /|                                  |
    +---------+ |             +----------+         |  +--------+
    |         | |             |          |responses|  |        |
    |         | |             |   Name   |---------|->|Foreign |
    |  Master |-------------->|  Server  |         |  |Resolver|
    |  files  | |             |          |<--------|--|        |
    |         |/              |          | queries |  +--------+
    +---------+               +----------+         |

Here a primary name server acquires information about one or more zones
by reading master files from its local file system, and answers queries
about those zones that arrive from foreign resolvers.

The DNS requires that all zones be redundantly supported by more than
one name server.  Designated secondary servers can acquire zones and
check for updates from the primary server using the zone transfer
protocol of the DNS.  This configuration is shown below:

                 Local Host                        |  Foreign
                                                   |
      +---------+                                  |
     /         /|                                  |
    +---------+ |             +----------+         |  +--------+
    |         | |             |          |responses|  |        |
    |         | |             |   Name   |---------|->|Foreign |
    |  Master |-------------->|  Server  |         |  |Resolver|
    |  files  | |             |          |<--------|--|        |
    |         |/              |          | queries |  +--------+
    +---------+               +----------+         |
                                A     |maintenance |  +--------+
                                |     +------------|->|        |
                                |      queries     |  |Foreign |
                                |                  |  |  Name  |
                                +------------------|--| Server |
                             maintenance responses |  +--------+

In this configuration, the name server periodically establishes a
virtual circuit to a foreign name server to acquire a copy of a zone or
to check that an existing copy has not changed.  The messages sent for



Mockapetris                                                     [Page 5]

RFC 1035        Domain Implementation and Specification    November 1987


these maintenance activities follow the same form as queries and
responses, but the message sequences are somewhat different.

The information flow in a host that supports all aspects of the domain
name system is shown below:

                 Local Host                        |  Foreign
                                                   |
    +---------+               +----------+         |  +--------+
    |         | user queries  |          |queries  |  |        |
    |  User   |-------------->|          |---------|->|Foreign |
    | Program |               | Resolver |         |  |  Name  |
    |         |<--------------|          |<--------|--| Server |
    |         | user responses|          |responses|  |        |
    +---------+               +----------+         |  +--------+
                                |     A            |
                cache additions |     | references |
                                V     |            |
                              +----------+         |
                              |  Shared  |         |
                              | database |         |
                              +----------+         |
                                A     |            |
      +---------+     refreshes |     | references |
     /         /|               |     V            |
    +---------+ |             +----------+         |  +--------+
    |         | |             |          |responses|  |        |
    |         | |             |   Name   |---------|->|Foreign |
    |  Master |-------------->|  Server  |         |  |Resolver|
    |  files  | |             |          |<--------|--|        |
    |         |/              |          | queries |  +--------+
    +---------+               +----------+         |
                                A     |maintenance |  +--------+
                                |     +------------|->|        |
                                |      queries     |  |Foreign |
                                |                  |  |  Name  |
                                +------------------|--| Server |
                             maintenance responses |  +--------+

The shared database holds domain space data for the local name server
and resolver.  The contents of the shared database will typically be a
mixture of authoritative data maintained by the periodic refresh
operations of the name server and cached data from previous resolver
requests.  The structure of the domain data and the necessity for
synchronization between name servers and resolvers imply the general
characteristics of this database, but the actual format is up to the
local implementor.




Mockapetris                                                     [Page 6]

RFC 1035        Domain Implementation and Specification    November 1987


Information flow can also be tailored so that a group of hosts act
together to optimize activities.  Sometimes this is done to offload less
capable hosts so that they do not have to implement a full resolver.
This can be appropriate for PCs or hosts which want to minimize the
amount of new network code which is required.  This scheme can also
allow a group of hosts can share a small number of caches rather than
maintaining a large number of separate caches, on the premise that the
centralized caches will have a higher hit ratio.  In either case,
resolvers are replaced with stub resolvers which act as front ends to
resolvers located in a recursive server in one or more name servers
known to perform that service:

                   Local Hosts                     |  Foreign
                                                   |
    +---------+                                    |
    |         | responses                          |
    | Stub    |<--------------------+              |
    | Resolver|                     |              |
    |         |----------------+    |              |
    +---------+ recursive      |    |              |
                queries        |    |              |
                               V    |              |
    +---------+ recursive     +----------+         |  +--------+
    |         | queries       |          |queries  |  |        |
    | Stub    |-------------->| Recursive|---------|->|Foreign |
    | Resolver|               | Server   |         |  |  Name  |
    |         |<--------------|          |<--------|--| Server |
    +---------+ responses     |          |responses|  |        |
                              +----------+         |  +--------+
                              |  Central |         |
                              |   cache  |         |
                              +----------+         |

In any case, note that domain components are always replicated for
reliability whenever possible.

2.3. Conventions

The domain system has several conventions dealing with low-level, but
fundamental, issues.  While the implementor is free to violate these
conventions WITHIN HIS OWN SYSTEM, he must observe these conventions in
ALL behavior observed from other hosts.

2.3.1. Preferred name syntax

The DNS specifications attempt to be as general as possible in the rules
for constructing domain names.  The idea is that the name of any
existing object can be expressed as a domain name with minimal changes.



Mockapetris                                                     [Page 7]

RFC 1035        Domain Implementation and Specification    November 1987


However, when assigning a domain name for an object, the prudent user
will select a name which satisfies both the rules of the domain system
and any existing rules for the object, whether these rules are published
or implied by existing programs.

For example, when naming a mail domain, the user should satisfy both the
rules of this memo and those in RFC-822.  When creating a new host name,
the old rules for HOSTS.TXT should be followed.  This avoids problems
when old software is converted to use domain names.

The following syntax will result in fewer problems with many

applications that use domain names (e.g., mail, TELNET).

<domain> ::= <subdomain> | " "

<subdomain> ::= <label> | <subdomain> "." <label>

<label> ::= <letter> [ [ <ldh-str> ] <let-dig> ]

<ldh-str> ::= <let-dig-hyp> | <let-dig-hyp> <ldh-str>

<let-dig-hyp> ::= <let-dig> | "-"

<let-dig> ::= <letter> | <digit>

<letter> ::= any one of the 52 alphabetic characters A through Z in
upper case and a through z in lower case

<digit> ::= any one of the ten digits 0 through 9

Note that while upper and lower case letters are allowed in domain
names, no significance is attached to the case.  That is, two names with
the same spelling but different case are to be treated as if identical.

The labels must follow the rules for ARPANET host names.  They must
start with a letter, end with a letter or digit, and have as interior
characters only letters, digits, and hyphen.  There are also some
restrictions on the length.  Labels must be 63 characters or less.

For example, the following strings identify hosts in the Internet:

A.ISI.EDU XX.LCS.MIT.EDU SRI-NIC.ARPA

2.3.2. Data Transmission Order

The order of transmission of the header and data described in this
document is resolved to the octet level.  Whenever a diagram shows a



Mockapetris                                                     [Page 8]

RFC 1035        Domain Implementation and Specification    November 1987


group of octets, the order of transmission of those octets is the normal
order in which they are read in English.  For example, in the following
diagram, the octets are transmitted in the order they are numbered.

     0                   1
     0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5
    +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
    |       1       |       2       |
    +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
    |       3       |       4       |
    +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
    |       5       |       6       |
    +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+

Whenever an octet represents a numeric quantity, the left most bit in
the diagram is the high order or most significant bit.  That is, the bit
labeled 0 is the most significant bit.  For example, the following
diagram represents the value 170 (decimal).

     0 1 2 3 4 5 6 7
    +-+-+-+-+-+-+-+-+
    |1 0 1 0 1 0 1 0|
    +-+-+-+-+-+-+-+-+

Similarly, whenever a multi-octet field represents a numeric quantity
the left most bit of the whole field is the most significant bit.  When
a multi-octet quantity is transmitted the most significant octet is
transmitted first.

2.3.3. Character Case

For all parts of the DNS that are part of the official protocol, all
comparisons between character strings (e.g., labels, domain names, etc.)
are done in a case-insensitive manner.  At present, this rule is in
force throughout the domain system without exception.  However, future
additions beyond current usage may need to use the full binary octet
capabilities in names, so attempts to store domain names in 7-bit ASCII
or use of special bytes to terminate labels, etc., should be avoided.

When data enters the domain system, its original case should be
preserved whenever possible.  In certain circumstances this cannot be
done.  For example, if two RRs are stored in a database, one at x.y and
one at X.Y, they are actually stored at the same place in the database,
and hence only one casing would be preserved.  The basic rule is that
case can be discarded only when data is used to define structure in a
database, and two names are identical when compared in a case
insensitive manner.




Mockapetris                                                     [Page 9]

RFC 1035        Domain Implementation and Specification    November 1987


Loss of case sensitive data must be minimized.  Thus while data for x.y
and X.Y may both be stored under a single location x.y or X.Y, data for
a.x and B.X would never be stored under A.x, A.X, b.x, or b.X.  In
general, this preserves the case of the first label of a domain name,
but forces standardization of interior node labels.

Systems administrators who enter data into the domain database should
take care to represent the data they supply to the domain system in a
case-consistent manner if their system is case-sensitive.  The data
distribution system in the domain system will ensure that consistent
representations are preserved.

2.3.4. Size limits

Various objects and parameters in the DNS have size limits.  They are
listed below.  Some could be easily changed, others are more
fundamental.

labels          63 octets or less

names           255 octets or less

TTL             positive values of a signed 32 bit number.

UDP messages    512 octets or less

3. DOMAIN NAME SPACE AND RR DEFINITIONS

3.1. Name space definitions

Domain names in messages are expressed in terms of a sequence of labels.
Each label is represented as a one octet length field followed by that
number of octets.  Since every domain name ends with the null label of
the root, a domain name is terminated by a length byte of zero.  The
high order two bits of every length octet must be zero, and the
remaining six bits of the length field limit the label to 63 octets or
less.

To simplify implementations, the total length of a domain name (i.e.,
label octets and label length octets) is restricted to 255 octets or
less.

Although labels can contain any 8 bit values in octets that make up a
label, it is strongly recommended that labels follow the preferred
syntax described elsewhere in this memo, which is compatible with
existing host naming conventions.  Name servers and resolvers must
compare labels in a case-insensitive manner (i.e., A=a), assuming ASCII
with zero parity.  Non-alphabetic codes must match exactly.



Mockapetris                                                    [Page 10]

RFC 1035        Domain Implementation and Specification    November 1987


3.2. RR definitions

3.2.1. Format

All RRs have the same top level format shown below:

                                    1  1  1  1  1  1
      0  1  2  3  4  5  6  7  8  9  0  1  2  3  4  5
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                                               |
    /                                               /
    /                      NAME                     /
    |                                               |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                      TYPE                     |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                     CLASS                     |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                      TTL                      |
    |                                               |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                   RDLENGTH                    |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--|
    /                     RDATA                     /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+


where:

NAME            an owner name, i.e., the name of the node to which this
                resource record pertains.

TYPE            two octets containing one of the RR TYPE codes.

CLASS           two octets containing one of the RR CLASS codes.

TTL             a 32 bit signed integer that specifies the time interval
                that the resource record may be cached before the source
                of the information should again be consulted.  Zero
                values are interpreted to mean that the RR can only be
                used for the transaction in progress, and should not be
                cached.  For example, SOA records are always distributed
                with a zero TTL to prohibit caching.  Zero values can
                also be used for extremely volatile data.

RDLENGTH        an unsigned 16 bit integer that specifies the length in
                octets of the RDATA field.



Mockapetris                                                    [Page 11]

RFC 1035        Domain Implementation and Specification    November 1987


RDATA           a variable length string of octets that describes the
                resource.  The format of this information varies
                according to the TYPE and CLASS of the resource record.

3.2.2. TYPE values

TYPE fields are used in resource records.  Note that these types are a
subset of QTYPEs.

TYPE            value and meaning

A               1 a host address

NS              2 an authoritative name server

MD              3 a mail destination (Obsolete - use MX)

MF              4 a mail forwarder (Obsolete - use MX)

CNAME           5 the canonical name for an alias

SOA             6 marks the start of a zone of authority

MB              7 a mailbox domain name (EXPERIMENTAL)

MG              8 a mail group member (EXPERIMENTAL)

MR              9 a mail rename domain name (EXPERIMENTAL)

NULL            10 a null RR (EXPERIMENTAL)

WKS             11 a well known service description

PTR             12 a domain name pointer

HINFO           13 host information

MINFO           14 mailbox or mail list information

MX              15 mail exchange

TXT             16 text strings

3.2.3. QTYPE values

QTYPE fields appear in the question part of a query.  QTYPES are a
superset of TYPEs, hence all TYPEs are valid QTYPEs.  In addition, the
following QTYPEs are defined:



Mockapetris                                                    [Page 12]

RFC 1035        Domain Implementation and Specification    November 1987


AXFR            252 A request for a transfer of an entire zone

MAILB           253 A request for mailbox-related records (MB, MG or MR)

MAILA           254 A request for mail agent RRs (Obsolete - see MX)

*               255 A request for all records

3.2.4. CLASS values

CLASS fields appear in resource records.  The following CLASS mnemonics
and values are defined:

IN              1 the Internet

CS              2 the CSNET class (Obsolete - used only for examples in
                some obsolete RFCs)

CH              3 the CHAOS class

HS              4 Hesiod [Dyer 87]

3.2.5. QCLASS values

QCLASS fields appear in the question section of a query.  QCLASS values
are a superset of CLASS values; every CLASS is a valid QCLASS.  In
addition to CLASS values, the following QCLASSes are defined:

*               255 any class

3.3. Standard RRs

The following RR definitions are expected to occur, at least
potentially, in all classes.  In particular, NS, SOA, CNAME, and PTR
will be used in all classes, and have the same format in all classes.
Because their RDATA format is known, all domain names in the RDATA
section of these RRs may be compressed.

<domain-name> is a domain name represented as a series of labels, and
terminated by a label with zero length.  <character-string> is a single
length octet followed by that number of characters.  <character-string>
is treated as binary information, and can be up to 256 characters in
length (including the length octet).








Mockapetris                                                    [Page 13]

RFC 1035        Domain Implementation and Specification    November 1987


3.3.1. CNAME RDATA format

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                     CNAME                     /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

CNAME           A <domain-name> which specifies the canonical or primary
                name for the owner.  The owner name is an alias.

CNAME RRs cause no additional section processing, but name servers may
choose to restart the query at the canonical name in certain cases.  See
the description of name server logic in [RFC-1034] for details.

3.3.2. HINFO RDATA format

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                      CPU                      /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                       OS                      /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

CPU             A <character-string> which specifies the CPU type.

OS              A <character-string> which specifies the operating
                system type.

Standard values for CPU and OS can be found in [RFC-1010].

HINFO records are used to acquire general information about a host.  The
main use is for protocols such as FTP that can use special procedures
when talking between machines or operating systems of the same type.

3.3.3. MB RDATA format (EXPERIMENTAL)

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                   MADNAME                     /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

MADNAME         A <domain-name> which specifies a host which has the
                specified mailbox.



Mockapetris                                                    [Page 14]

RFC 1035        Domain Implementation and Specification    November 1987


MB records cause additional section processing which looks up an A type
RRs corresponding to MADNAME.

3.3.4. MD RDATA format (Obsolete)

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                   MADNAME                     /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

MADNAME         A <domain-name> which specifies a host which has a mail
                agent for the domain which should be able to deliver
                mail for the domain.

MD records cause additional section processing which looks up an A type
record corresponding to MADNAME.

MD is obsolete.  See the definition of MX and [RFC-974] for details of
the new scheme.  The recommended policy for dealing with MD RRs found in
a master file is to reject them, or to convert them to MX RRs with a
preference of 0.

3.3.5. MF RDATA format (Obsolete)

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                   MADNAME                     /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

MADNAME         A <domain-name> which specifies a host which has a mail
                agent for the domain which will accept mail for
                forwarding to the domain.

MF records cause additional section processing which looks up an A type
record corresponding to MADNAME.

MF is obsolete.  See the definition of MX and [RFC-974] for details ofw
the new scheme.  The recommended policy for dealing with MD RRs found in
a master file is to reject them, or to convert them to MX RRs with a
preference of 10.







Mockapetris                                                    [Page 15]

RFC 1035        Domain Implementation and Specification    November 1987


3.3.6. MG RDATA format (EXPERIMENTAL)

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                   MGMNAME                     /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

MGMNAME         A <domain-name> which specifies a mailbox which is a
                member of the mail group specified by the domain name.

MG records cause no additional section processing.

3.3.7. MINFO RDATA format (EXPERIMENTAL)

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                    RMAILBX                    /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                    EMAILBX                    /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

RMAILBX         A <domain-name> which specifies a mailbox which is
                responsible for the mailing list or mailbox.  If this
                domain name names the root, the owner of the MINFO RR is
                responsible for itself.  Note that many existing mailing
                lists use a mailbox X-request for the RMAILBX field of
                mailing list X, e.g., Msgroup-request for Msgroup.  This
                field provides a more general mechanism.


EMAILBX         A <domain-name> which specifies a mailbox which is to
                receive error messages related to the mailing list or
                mailbox specified by the owner of the MINFO RR (similar
                to the ERRORS-TO: field which has been proposed).  If
                this domain name names the root, errors should be
                returned to the sender of the message.

MINFO records cause no additional section processing.  Although these
records can be associated with a simple mailbox, they are usually used
with a mailing list.








Mockapetris                                                    [Page 16]

RFC 1035        Domain Implementation and Specification    November 1987


3.3.8. MR RDATA format (EXPERIMENTAL)

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                   NEWNAME                     /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

NEWNAME         A <domain-name> which specifies a mailbox which is the
                proper rename of the specified mailbox.

MR records cause no additional section processing.  The main use for MR
is as a forwarding entry for a user who has moved to a different
mailbox.

3.3.9. MX RDATA format

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                  PREFERENCE                   |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                   EXCHANGE                    /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

PREFERENCE      A 16 bit integer which specifies the preference given to
                this RR among others at the same owner.  Lower values
                are preferred.

EXCHANGE        A <domain-name> which specifies a host willing to act as
                a mail exchange for the owner name.

MX records cause type A additional section processing for the host
specified by EXCHANGE.  The use of MX RRs is explained in detail in
[RFC-974].

3.3.10. NULL RDATA format (EXPERIMENTAL)

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                  <anything>                   /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

Anything at all may be in the RDATA field so long as it is 65535 octets
or less.




Mockapetris                                                    [Page 17]

RFC 1035        Domain Implementation and Specification    November 1987


NULL records cause no additional section processing.  NULL RRs are not
allowed in master files.  NULLs are used as placeholders in some
experimental extensions of the DNS.

3.3.11. NS RDATA format

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                   NSDNAME                     /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

NSDNAME         A <domain-name> which specifies a host which should be
                authoritative for the specified class and domain.

NS records cause both the usual additional section processing to locate
a type A record, and, when used in a referral, a special search of the
zone in which they reside for glue information.

The NS RR states that the named host should be expected to have a zone
starting at owner name of the specified class.  Note that the class may
not indicate the protocol family which should be used to communicate
with the host, although it is typically a strong hint.  For example,
hosts which are name servers for either Internet (IN) or Hesiod (HS)
class information are normally queried using IN class protocols.

3.3.12. PTR RDATA format

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                   PTRDNAME                    /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

PTRDNAME        A <domain-name> which points to some location in the
                domain name space.

PTR records cause no additional section processing.  These RRs are used
in special domains to point to some other location in the domain space.
These records are simple data, and don't imply any special processing
similar to that performed by CNAME, which identifies aliases.  See the
description of the IN-ADDR.ARPA domain for an example.








Mockapetris                                                    [Page 18]

RFC 1035        Domain Implementation and Specification    November 1987


3.3.13. SOA RDATA format

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                     MNAME                     /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                     RNAME                     /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                    SERIAL                     |
    |                                               |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                    REFRESH                    |
    |                                               |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                     RETRY                     |
    |                                               |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                    EXPIRE                     |
    |                                               |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                    MINIMUM                    |
    |                                               |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

MNAME           The <domain-name> of the name server that was the
                original or primary source of data for this zone.

RNAME           A <domain-name> which specifies the mailbox of the
                person responsible for this zone.

SERIAL          The unsigned 32 bit version number of the original copy
                of the zone.  Zone transfers preserve this value.  This
                value wraps and should be compared using sequence space
                arithmetic.

REFRESH         A 32 bit time interval before the zone should be
                refreshed.

RETRY           A 32 bit time interval that should elapse before a
                failed refresh should be retried.

EXPIRE          A 32 bit time value that specifies the upper limit on
                the time interval that can elapse before the zone is no
                longer authoritative.





Mockapetris                                                    [Page 19]

RFC 1035        Domain Implementation and Specification    November 1987


MINIMUM         The unsigned 32 bit minimum TTL field that should be
                exported with any RR from this zone.

SOA records cause no additional section processing.

All times are in units of seconds.

Most of these fields are pertinent only for name server maintenance
operations.  However, MINIMUM is used in all query operations that
retrieve RRs from a zone.  Whenever a RR is sent in a response to a
query, the TTL field is set to the maximum of the TTL field from the RR
and the MINIMUM field in the appropriate SOA.  Thus MINIMUM is a lower
bound on the TTL field for all RRs in a zone.  Note that this use of
MINIMUM should occur when the RRs are copied into the response and not
when the zone is loaded from a master file or via a zone transfer.  The
reason for this provison is to allow future dynamic update facilities to
change the SOA RR with known semantics.


3.3.14. TXT RDATA format

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    /                   TXT-DATA                    /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

TXT-DATA        One or more <character-string>s.

TXT RRs are used to hold descriptive text.  The semantics of the text
depends on the domain where it is found.

3.4. Internet specific RRs

3.4.1. A RDATA format

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                    ADDRESS                    |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

ADDRESS         A 32 bit Internet address.

Hosts that have multiple Internet addresses will have multiple A
records.





Mockapetris                                                    [Page 20]

RFC 1035        Domain Implementation and Specification    November 1987


A records cause no additional section processing.  The RDATA section of
an A line in a master file is an Internet address expressed as four
decimal numbers separated by dots without any imbedded spaces (e.g.,
"10.2.0.52" or "192.0.5.6").

3.4.2. WKS RDATA format

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                    ADDRESS                    |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |       PROTOCOL        |                       |
    +--+--+--+--+--+--+--+--+                       |
    |                                               |
    /                   <BIT MAP>                   /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

ADDRESS         An 32 bit Internet address

PROTOCOL        An 8 bit IP protocol number

<BIT MAP>       A variable length bit map.  The bit map must be a
                multiple of 8 bits long.

The WKS record is used to describe the well known services supported by
a particular protocol on a particular internet address.  The PROTOCOL
field specifies an IP protocol number, and the bit map has one bit per
port of the specified protocol.  The first bit corresponds to port 0,
the second to port 1, etc.  If the bit map does not include a bit for a
protocol of interest, that bit is assumed zero.  The appropriate values
and mnemonics for ports and protocols are specified in [RFC-1010].

For example, if PROTOCOL=TCP (6), the 26th bit corresponds to TCP port
25 (SMTP).  If this bit is set, a SMTP server should be listening on TCP
port 25; if zero, SMTP service is not supported on the specified
address.

The purpose of WKS RRs is to provide availability information for
servers for TCP and UDP.  If a server supports both TCP and UDP, or has
multiple Internet addresses, then multiple WKS RRs are used.

WKS RRs cause no additional section processing.

In master files, both ports and protocols are expressed using mnemonics
or decimal numbers.




Mockapetris                                                    [Page 21]

RFC 1035        Domain Implementation and Specification    November 1987


3.5. IN-ADDR.ARPA domain

The Internet uses a special domain to support gateway location and
Internet address to host mapping.  Other classes may employ a similar
strategy in other domains.  The intent of this domain is to provide a
guaranteed method to perform host address to host name mapping, and to
facilitate queries to locate all gateways on a particular network in the
Internet.

Note that both of these services are similar to functions that could be
performed by inverse queries; the difference is that this part of the
domain name space is structured according to address, and hence can
guarantee that the appropriate data can be located without an exhaustive
search of the domain space.

The domain begins at IN-ADDR.ARPA and has a substructure which follows
the Internet addressing structure.

Domain names in the IN-ADDR.ARPA domain are defined to have up to four
labels in addition to the IN-ADDR.ARPA suffix.  Each label represents
one octet of an Internet address, and is expressed as a character string
for a decimal value in the range 0-255 (with leading zeros omitted
except in the case of a zero octet which is represented by a single
zero).

Host addresses are represented by domain names that have all four labels
specified.  Thus data for Internet address 10.2.0.52 is located at
domain name 52.0.2.10.IN-ADDR.ARPA.  The reversal, though awkward to
read, allows zones to be delegated which are exactly one network of
address space.  For example, 10.IN-ADDR.ARPA can be a zone containing
data for the ARPANET, while 26.IN-ADDR.ARPA can be a separate zone for
MILNET.  Address nodes are used to hold pointers to primary host names
in the normal domain space.

Network numbers correspond to some non-terminal nodes at various depths
in the IN-ADDR.ARPA domain, since Internet network numbers are either 1,
2, or 3 octets.  Network nodes are used to hold pointers to the primary
host names of gateways attached to that network.  Since a gateway is, by
definition, on more than one network, it will typically have two or more
network nodes which point at it.  Gateways will also have host level
pointers at their fully qualified addresses.

Both the gateway pointers at network nodes and the normal host pointers
at full address nodes use the PTR RR to point back to the primary domain
names of the corresponding hosts.

For example, the IN-ADDR.ARPA domain will contain information about the
ISI gateway between net 10 and 26, an MIT gateway from net 10 to MIT's



Mockapetris                                                    [Page 22]

RFC 1035        Domain Implementation and Specification    November 1987


net 18, and hosts A.ISI.EDU and MULTICS.MIT.EDU.  Assuming that ISI
gateway has addresses 10.2.0.22 and 26.0.0.103, and a name MILNET-
GW.ISI.EDU, and the MIT gateway has addresses 10.0.0.77 and 18.10.0.4
and a name GW.LCS.MIT.EDU, the domain database would contain:

    10.IN-ADDR.ARPA.           PTR MILNET-GW.ISI.EDU.
    10.IN-ADDR.ARPA.           PTR GW.LCS.MIT.EDU.
    18.IN-ADDR.ARPA.           PTR GW.LCS.MIT.EDU.
    26.IN-ADDR.ARPA.           PTR MILNET-GW.ISI.EDU.
    22.0.2.10.IN-ADDR.ARPA.    PTR MILNET-GW.ISI.EDU.
    103.0.0.26.IN-ADDR.ARPA.   PTR MILNET-GW.ISI.EDU.
    77.0.0.10.IN-ADDR.ARPA.    PTR GW.LCS.MIT.EDU.
    4.0.10.18.IN-ADDR.ARPA.    PTR GW.LCS.MIT.EDU.
    103.0.3.26.IN-ADDR.ARPA.   PTR A.ISI.EDU.
    6.0.0.10.IN-ADDR.ARPA.     PTR MULTICS.MIT.EDU.

Thus a program which wanted to locate gateways on net 10 would originate
a query of the form QTYPE=PTR, QCLASS=IN, QNAME=10.IN-ADDR.ARPA.  It
would receive two RRs in response:

    10.IN-ADDR.ARPA.           PTR MILNET-GW.ISI.EDU.
    10.IN-ADDR.ARPA.           PTR GW.LCS.MIT.EDU.

The program could then originate QTYPE=A, QCLASS=IN queries for MILNET-
GW.ISI.EDU. and GW.LCS.MIT.EDU. to discover the Internet addresses of
these gateways.

A resolver which wanted to find the host name corresponding to Internet
host address 10.0.0.6 would pursue a query of the form QTYPE=PTR,
QCLASS=IN, QNAME=6.0.0.10.IN-ADDR.ARPA, and would receive:

    6.0.0.10.IN-ADDR.ARPA.     PTR MULTICS.MIT.EDU.

Several cautions apply to the use of these services:
   - Since the IN-ADDR.ARPA special domain and the normal domain
     for a particular host or gateway will be in different zones,
     the possibility exists that that the data may be inconsistent.

   - Gateways will often have two names in separate domains, only
     one of which can be primary.

   - Systems that use the domain database to initialize their
     routing tables must start with enough gateway information to
     guarantee that they can access the appropriate name server.

   - The gateway data only reflects the existence of a gateway in a
     manner equivalent to the current HOSTS.TXT file.  It doesn't
     replace the dynamic availability information from GGP or EGP.



Mockapetris                                                    [Page 23]

RFC 1035        Domain Implementation and Specification    November 1987


3.6. Defining new types, classes, and special namespaces

The previously defined types and classes are the ones in use as of the
date of this memo.  New definitions should be expected.  This section
makes some recommendations to designers considering additions to the
existing facilities.  The mailing list NAMEDROPPERS@SRI-NIC.ARPA is the
forum where general discussion of design issues takes place.

In general, a new type is appropriate when new information is to be
added to the database about an existing object, or we need new data
formats for some totally new object.  Designers should attempt to define
types and their RDATA formats that are generally applicable to all
classes, and which avoid duplication of information.  New classes are
appropriate when the DNS is to be used for a new protocol, etc which
requires new class-specific data formats, or when a copy of the existing
name space is desired, but a separate management domain is necessary.

New types and classes need mnemonics for master files; the format of the
master files requires that the mnemonics for type and class be disjoint.

TYPE and CLASS values must be a proper subset of QTYPEs and QCLASSes
respectively.

The present system uses multiple RRs to represent multiple values of a
type rather than storing multiple values in the RDATA section of a
single RR.  This is less efficient for most applications, but does keep
RRs shorter.  The multiple RRs assumption is incorporated in some
experimental work on dynamic update methods.

The present system attempts to minimize the duplication of data in the
database in order to insure consistency.  Thus, in order to find the
address of the host for a mail exchange, you map the mail domain name to
a host name, then the host name to addresses, rather than a direct
mapping to host address.  This approach is preferred because it avoids
the opportunity for inconsistency.

In defining a new type of data, multiple RR types should not be used to
create an ordering between entries or express different formats for
equivalent bindings, instead this information should be carried in the
body of the RR and a single type used.  This policy avoids problems with
caching multiple types and defining QTYPEs to match multiple types.

For example, the original form of mail exchange binding used two RR
types one to represent a "closer" exchange (MD) and one to represent a
"less close" exchange (MF).  The difficulty is that the presence of one
RR type in a cache doesn't convey any information about the other
because the query which acquired the cached information might have used
a QTYPE of MF, MD, or MAILA (which matched both).  The redesigned



Mockapetris                                                    [Page 24]

RFC 1035        Domain Implementation and Specification    November 1987


service used a single type (MX) with a "preference" value in the RDATA
section which can order different RRs.  However, if any MX RRs are found
in the cache, then all should be there.

4. MESSAGES

4.1. Format

All communications inside of the domain protocol are carried in a single
format called a message.  The top level format of message is divided
into 5 sections (some of which are empty in certain cases) shown below:

    +---------------------+
    |        Header       |
    +---------------------+
    |       Question      | the question for the name server
    +---------------------+
    |        Answer       | RRs answering the question
    +---------------------+
    |      Authority      | RRs pointing toward an authority
    +---------------------+
    |      Additional     | RRs holding additional information
    +---------------------+

The header section is always present.  The header includes fields that
specify which of the remaining sections are present, and also specify
whether the message is a query or a response, a standard query or some
other opcode, etc.

The names of the sections after the header are derived from their use in
standard queries.  The question section contains fields that describe a
question to a name server.  These fields are a query type (QTYPE), a
query class (QCLASS), and a query domain name (QNAME).  The last three
sections have the same format: a possibly empty list of concatenated
resource records (RRs).  The answer section contains RRs that answer the
question; the authority section contains RRs that point toward an
authoritative name server; the additional records section contains RRs
which relate to the query, but are not strictly answers for the
question.












Mockapetris                                                    [Page 25]

RFC 1035        Domain Implementation and Specification    November 1987


4.1.1. Header section format

The header contains the following fields:

                                    1  1  1  1  1  1
      0  1  2  3  4  5  6  7  8  9  0  1  2  3  4  5
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                      ID                       |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |QR|   Opcode  |AA|TC|RD|RA|   Z    |   RCODE   |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                    QDCOUNT                    |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                    ANCOUNT                    |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                    NSCOUNT                    |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                    ARCOUNT                    |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

ID              A 16 bit identifier assigned by the program that
                generates any kind of query.  This identifier is copied
                the corresponding reply and can be used by the requester
                to match up replies to outstanding queries.

QR              A one bit field that specifies whether this message is a
                query (0), or a response (1).

OPCODE          A four bit field that specifies kind of query in this
                message.  This value is set by the originator of a query
                and copied into the response.  The values are:

                0               a standard query (QUERY)

                1               an inverse query (IQUERY)

                2               a server status request (STATUS)

                3-15            reserved for future use

AA              Authoritative Answer - this bit is valid in responses,
                and specifies that the responding name server is an
                authority for the domain name in question section.

                Note that the contents of the answer section may have
                multiple owner names because of aliases.  The AA bit



Mockapetris                                                    [Page 26]

RFC 1035        Domain Implementation and Specification    November 1987


                corresponds to the name which matches the query name, or
                the first owner name in the answer section.

TC              TrunCation - specifies that this message was truncated
                due to length greater than that permitted on the
                transmission channel.

RD              Recursion Desired - this bit may be set in a query and
                is copied into the response.  If RD is set, it directs
                the name server to pursue the query recursively.
                Recursive query support is optional.

RA              Recursion Available - this be is set or cleared in a
                response, and denotes whether recursive query support is
                available in the name server.

Z               Reserved for future use.  Must be zero in all queries
                and responses.

RCODE           Response code - this 4 bit field is set as part of
                responses.  The values have the following
                interpretation:

                0               No error condition

                1               Format error - The name server was
                                unable to interpret the query.

                2               Server failure - The name server was
                                unable to process this query due to a
                                problem with the name server.

                3               Name Error - Meaningful only for
                                responses from an authoritative name
                                server, this code signifies that the
                                domain name referenced in the query does
                                not exist.

                4               Not Implemented - The name server does
                                not support the requested kind of query.

                5               Refused - The name server refuses to
                                perform the specified operation for
                                policy reasons.  For example, a name
                                server may not wish to provide the
                                information to the particular requester,
                                or a name server may not wish to perform
                                a particular operation (e.g., zone



Mockapetris                                                    [Page 27]

RFC 1035        Domain Implementation and Specification    November 1987


                                transfer) for particular data.

                6-15            Reserved for future use.

QDCOUNT         an unsigned 16 bit integer specifying the number of
                entries in the question section.

ANCOUNT         an unsigned 16 bit integer specifying the number of
                resource records in the answer section.

NSCOUNT         an unsigned 16 bit integer specifying the number of name
                server resource records in the authority records
                section.

ARCOUNT         an unsigned 16 bit integer specifying the number of
                resource records in the additional records section.

4.1.2. Question section format

The question section is used to carry the "question" in most queries,
i.e., the parameters that define what is being asked.  The section
contains QDCOUNT (usually 1) entries, each of the following format:

                                    1  1  1  1  1  1
      0  1  2  3  4  5  6  7  8  9  0  1  2  3  4  5
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                                               |
    /                     QNAME                     /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                     QTYPE                     |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                     QCLASS                    |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

QNAME           a domain name represented as a sequence of labels, where
                each label consists of a length octet followed by that
                number of octets.  The domain name terminates with the
                zero length octet for the null label of the root.  Note
                that this field may be an odd number of octets; no
                padding is used.

QTYPE           a two octet code which specifies the type of the query.
                The values for this field include all codes valid for a
                TYPE field, together with some more general codes which
                can match more than one type of RR.



Mockapetris                                                    [Page 28]

RFC 1035        Domain Implementation and Specification    November 1987


QCLASS          a two octet code that specifies the class of the query.
                For example, the QCLASS field is IN for the Internet.

4.1.3. Resource record format

The answer, authority, and additional sections all share the same
format: a variable number of resource records, where the number of
records is specified in the corresponding count field in the header.
Each resource record has the following format:
                                    1  1  1  1  1  1
      0  1  2  3  4  5  6  7  8  9  0  1  2  3  4  5
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                                               |
    /                                               /
    /                      NAME                     /
    |                                               |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                      TYPE                     |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                     CLASS                     |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                      TTL                      |
    |                                               |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    |                   RDLENGTH                    |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--|
    /                     RDATA                     /
    /                                               /
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

where:

NAME            a domain name to which this resource record pertains.

TYPE            two octets containing one of the RR type codes.  This
                field specifies the meaning of the data in the RDATA
                field.

CLASS           two octets which specify the class of the data in the
                RDATA field.

TTL             a 32 bit unsigned integer that specifies the time
                interval (in seconds) that the resource record may be
                cached before it should be discarded.  Zero values are
                interpreted to mean that the RR can only be used for the
                transaction in progress, and should not be cached.





Mockapetris                                                    [Page 29]

RFC 1035        Domain Implementation and Specification    November 1987


RDLENGTH        an unsigned 16 bit integer that specifies the length in
                octets of the RDATA field.

RDATA           a variable length string of octets that describes the
                resource.  The format of this information varies
                according to the TYPE and CLASS of the resource record.
                For example, the if the TYPE is A and the CLASS is IN,
                the RDATA field is a 4 octet ARPA Internet address.

4.1.4. Message compression

In order to reduce the size of messages, the domain system utilizes a
compression scheme which eliminates the repetition of domain names in a
message.  In this scheme, an entire domain name or a list of labels at
the end of a domain name is replaced with a pointer to a prior occurance
of the same name.

The pointer takes the form of a two octet sequence:

    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    | 1  1|                OFFSET                   |
    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

The first two bits are ones.  This allows a pointer to be distinguished
from a label, since the label must begin with two zero bits because
labels are restricted to 63 octets or less.  (The 10 and 01 combinations
are reserved for future use.)  The OFFSET field specifies an offset from
the start of the message (i.e., the first octet of the ID field in the
domain header).  A zero offset specifies the first byte of the ID field,
etc.

The compression scheme allows a domain name in a message to be
represented as either:

   - a sequence of labels ending in a zero octet

   - a pointer

   - a sequence of labels ending with a pointer

Pointers can only be used for occurances of a domain name where the
format is not class specific.  If this were not the case, a name server
or resolver would be required to know the format of all RRs it handled.
As yet, there are no such cases, but they may occur in future RDATA
formats.

If a domain name is contained in a part of the message subject to a
length field (such as the RDATA section of an RR), and compression is



Mockapetris                                                    [Page 30]

RFC 1035        Domain Implementation and Specification    November 1987


used, the length of the compressed name is used in the length
calculation, rather than the length of the expanded name.

Programs are free to avoid using pointers in messages they generate,
although this will reduce datagram capacity, and may cause truncation.
However all programs are required to understand arriving messages that
contain pointers.

For example, a datagram might need to use the domain names F.ISI.ARPA,
FOO.F.ISI.ARPA, ARPA, and the root.  Ignoring the other fields of the
message, these domain names might be represented as:

       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    20 |           1           |           F           |
       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    22 |           3           |           I           |
       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    24 |           S           |           I           |
       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    26 |           4           |           A           |
       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    28 |           R           |           P           |
       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    30 |           A           |           0           |
       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    40 |           3           |           F           |
       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    42 |           O           |           O           |
       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    44 | 1  1|                20                       |
       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    64 | 1  1|                26                       |
       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
    92 |           0           |                       |
       +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+

The domain name for F.ISI.ARPA is shown at offset 20.  The domain name
FOO.F.ISI.ARPA is shown at offset 40; this definition uses a pointer to
concatenate a label for FOO to the previously defined F.ISI.ARPA.  The
domain name ARPA is defined at offset 64 using a pointer to the ARPA
component of the name F.ISI.ARPA at 20; note that this pointer relies on
ARPA being the last label in the string at 20.  The root domain name is



Mockapetris                                                    [Page 31]

RFC 1035        Domain Implementation and Specification    November 1987


defined by a single octet of zeros at 92; the root domain name has no
labels.

4.2. Transport

The DNS assumes that messages will be transmitted as datagrams or in a
byte stream carried by a virtual circuit.  While virtual circuits can be
used for any DNS activity, datagrams are preferred for queries due to
their lower overhead and better performance.  Zone refresh activities
must use virtual circuits because of the need for reliable transfer.

The Internet supports name server access using TCP [RFC-793] on server
port 53 (decimal) as well as datagram access using UDP [RFC-768] on UDP
port 53 (decimal).

4.2.1. UDP usage

Messages sent using UDP user server port 53 (decimal).

Messages carried by UDP are restricted to 512 bytes (not counting the IP
or UDP headers).  Longer messages are truncated and the TC bit is set in
the header.

UDP is not acceptable for zone transfers, but is the recommended method
for standard queries in the Internet.  Queries sent using UDP may be
lost, and hence a retransmission strategy is required.  Queries or their
responses may be reordered by the network, or by processing in name
servers, so resolvers should not depend on them being returned in order.

The optimal UDP retransmission policy will vary with performance of the
Internet and the needs of the client, but the following are recommended:

   - The client should try other servers and server addresses
     before repeating a query to a specific address of a server.

   - The retransmission interval should be based on prior
     statistics if possible.  Too aggressive retransmission can
     easily slow responses for the community at large.  Depending
     on how well connected the client is to its expected servers,
     the minimum retransmission interval should be 2-5 seconds.

More suggestions on server selection and retransmission policy can be
found in the resolver section of this memo.

4.2.2. TCP usage

Messages sent over TCP connections use server port 53 (decimal).  The
message is prefixed with a two byte length field which gives the message



Mockapetris                                                    [Page 32]

RFC 1035        Domain Implementation and Specification    November 1987


length, excluding the two byte length field.  This length field allows
the low-level processing to assemble a complete message before beginning
to parse it.

Several connection management policies are recommended:

   - The server should not block other activities waiting for TCP
     data.

   - The server should support multiple connections.

   - The server should assume that the client will initiate
     connection closing, and should delay closing its end of the
     connection until all outstanding client requests have been
     satisfied.

   - If the server needs to close a dormant connection to reclaim
     resources, it should wait until the connection has been idle
     for a period on the order of two minutes.  In particular, the
     server should allow the SOA and AXFR request sequence (which
     begins a refresh operation) to be made on a single connection.
     Since the server would be unable to answer queries anyway, a
     unilateral close or reset may be used instead of a graceful
     close.

5. MASTER FILES

Master files are text files that contain RRs in text form.  Since the
contents of a zone can be expressed in the form of a list of RRs a
master file is most often used to define a zone, though it can be used
to list a cache's contents.  Hence, this section first discusses the
format of RRs in a master file, and then the special considerations when
a master file is used to create a zone in some name server.

5.1. Format

The format of these files is a sequence of entries.  Entries are
predominantly line-oriented, though parentheses can be used to continue
a list of items across a line boundary, and text literals can contain
CRLF within the text.  Any combination of tabs and spaces act as a
delimiter between the separate items that make up an entry.  The end of
any line in the master file can end with a comment.  The comment starts
with a ";" (semicolon).

The following entries are defined:

    <blank>[<comment>]




Mockapetris                                                    [Page 33]

RFC 1035        Domain Implementation and Specification    November 1987


    $ORIGIN <domain-name> [<comment>]

    $INCLUDE <file-name> [<domain-name>] [<comment>]

    <domain-name><rr> [<comment>]

    <blank><rr> [<comment>]

Blank lines, with or without comments, are allowed anywhere in the file.

Two control entries are defined: $ORIGIN and $INCLUDE.  $ORIGIN is
followed by a domain name, and resets the current origin for relative
domain names to the stated name.  $INCLUDE inserts the named file into
the current file, and may optionally specify a domain name that sets the
relative domain name origin for the included file.  $INCLUDE may also
have a comment.  Note that a $INCLUDE entry never changes the relative
origin of the parent file, regardless of changes to the relative origin
made within the included file.

The last two forms represent RRs.  If an entry for an RR begins with a
blank, then the RR is assumed to be owned by the last stated owner.  If
an RR entry begins with a <domain-name>, then the owner name is reset.

<rr> contents take one of the following forms:

    [<TTL>] [<class>] <type> <RDATA>

    [<class>] [<TTL>] <type> <RDATA>

The RR begins with optional TTL and class fields, followed by a type and
RDATA field appropriate to the type and class.  Class and type use the
standard mnemonics, TTL is a decimal integer.  Omitted class and TTL
values are default to the last explicitly stated values.  Since type and
class mnemonics are disjoint, the parse is unique.  (Note that this
order is different from the order used in examples and the order used in
the actual RRs; the given order allows easier parsing and defaulting.)

<domain-name>s make up a large share of the data in the master file.
The labels in the domain name are expressed as character strings and
separated by dots.  Quoting conventions allow arbitrary characters to be
stored in domain names.  Domain names that end in a dot are called
absolute, and are taken as complete.  Domain names which do not end in a
dot are called relative; the actual domain name is the concatenation of
the relative part with an origin specified in a $ORIGIN, $INCLUDE, or as
an argument to the master file loading routine.  A relative name is an
error when no origin is available.





Mockapetris                                                    [Page 34]

RFC 1035        Domain Implementation and Specification    November 1987


<character-string> is expressed in one or two ways: as a contiguous set
of characters without interior spaces, or as a string beginning with a "
and ending with a ".  Inside a " delimited string any character can
occur, except for a " itself, which must be quoted using \ (back slash).

Because these files are text files several special encodings are
necessary to allow arbitrary data to be loaded.  In particular:

                of the root.

@               A free standing @ is used to denote the current origin.

\X              where X is any character other than a digit (0-9), is
                used to quote that character so that its special meaning
                does not apply.  For example, "\." can be used to place
                a dot character in a label.

\DDD            where each D is a digit is the octet corresponding to
                the decimal number described by DDD.  The resulting
                octet is assumed to be text and is not checked for
                special meaning.

( )             Parentheses are used to group data that crosses a line
                boundary.  In effect, line terminations are not
                recognized within parentheses.

;               Semicolon is used to start a comment; the remainder of
                the line is ignored.

5.2. Use of master files to define zones

When a master file is used to load a zone, the operation should be
suppressed if any errors are encountered in the master file.  The
rationale for this is that a single error can have widespread
consequences.  For example, suppose that the RRs defining a delegation
have syntax errors; then the server will return authoritative name
errors for all names in the subzone (except in the case where the
subzone is also present on the server).

Several other validity checks that should be performed in addition to
insuring that the file is syntactically correct:

   1. All RRs in the file should have the same class.

   2. Exactly one SOA RR should be present at the top of the zone.

   3. If delegations are present and glue information is required,
      it should be present.



Mockapetris                                                    [Page 35]

RFC 1035        Domain Implementation and Specification    November 1987


   4. Information present outside of the authoritative nodes in the
      zone should be glue information, rather than the result of an
      origin or similar error.

5.3. Master file example

The following is an example file which might be used to define the
ISI.EDU zone.and is loaded with an origin of ISI.EDU:

@   IN  SOA     VENERA      Action\.domains (
                                 20     ; SERIAL
                                 7200   ; REFRESH
                                 600    ; RETRY
                                 3600000; EXPIRE
                                 60)    ; MINIMUM

        NS      A.ISI.EDU.
        NS      VENERA
        NS      VAXA
        MX      10      VENERA
        MX      20      VAXA

A       A       26.3.0.103

VENERA  A       10.1.0.52
        A       128.9.0.32

VAXA    A       10.2.0.27
        A       128.9.0.33


$INCLUDE <SUBSYS>ISI-MAILBOXES.TXT

Where the file <SUBSYS>ISI-MAILBOXES.TXT is:

    MOE     MB      A.ISI.EDU.
    LARRY   MB      A.ISI.EDU.
    CURLEY  MB      A.ISI.EDU.
    STOOGES MG      MOE
            MG      LARRY
            MG      CURLEY

Note the use of the \ character in the SOA RR to specify the responsible
person mailbox "Action.domains@E.ISI.EDU".







Mockapetris                                                    [Page 36]

RFC 1035        Domain Implementation and Specification    November 1987


6. NAME SERVER IMPLEMENTATION

6.1. Architecture

The optimal structure for the name server will depend on the host
operating system and whether the name server is integrated with resolver
operations, either by supporting recursive service, or by sharing its
database with a resolver.  This section discusses implementation
considerations for a name server which shares a database with a
resolver, but most of these concerns are present in any name server.

6.1.1. Control

A name server must employ multiple concurrent activities, whether they
are implemented as separate tasks in the host's OS or multiplexing
inside a single name server program.  It is simply not acceptable for a
name server to block the service of UDP requests while it waits for TCP
data for refreshing or query activities.  Similarly, a name server
should not attempt to provide recursive service without processing such
requests in parallel, though it may choose to serialize requests from a
single client, or to regard identical requests from the same client as
duplicates.  A name server should not substantially delay requests while
it reloads a zone from master files or while it incorporates a newly
refreshed zone into its database.

6.1.2. Database

While name server implementations are free to use any internal data
structures they choose, the suggested structure consists of three major
parts:

   - A "catalog" data structure which lists the zones available to
     this server, and a "pointer" to the zone data structure.  The
     main purpose of this structure is to find the nearest ancestor
     zone, if any, for arriving standard queries.

   - Separate data structures for each of the zones held by the
     name server.

   - A data structure for cached data. (or perhaps separate caches
     for different classes)

All of these data structures can be implemented an identical tree
structure format, with different data chained off the nodes in different
parts: in the catalog the data is pointers to zones, while in the zone
and cache data structures, the data will be RRs.  In designing the tree
framework the designer should recognize that query processing will need
to traverse the tree using case-insensitive label comparisons; and that



Mockapetris                                                    [Page 37]

RFC 1035        Domain Implementation and Specification    November 1987


in real data, a few nodes have a very high branching factor (100-1000 or
more), but the vast majority have a very low branching factor (0-1).

One way to solve the case problem is to store the labels for each node
in two pieces: a standardized-case representation of the label where all
ASCII characters are in a single case, together with a bit mask that
denotes which characters are actually of a different case.  The
branching factor diversity can be handled using a simple linked list for
a node until the branching factor exceeds some threshold, and
transitioning to a hash structure after the threshold is exceeded.  In
any case, hash structures used to store tree sections must insure that
hash functions and procedures preserve the casing conventions of the
DNS.

The use of separate structures for the different parts of the database
is motivated by several factors:

   - The catalog structure can be an almost static structure that
     need change only when the system administrator changes the
     zones supported by the server.  This structure can also be
     used to store parameters used to control refreshing
     activities.

   - The individual data structures for zones allow a zone to be
     replaced simply by changing a pointer in the catalog.  Zone
     refresh operations can build a new structure and, when
     complete, splice it into the database via a simple pointer
     replacement.  It is very important that when a zone is
     refreshed, queries should not use old and new data
     simultaneously.

   - With the proper search procedures, authoritative data in zones
     will always "hide", and hence take precedence over, cached
     data.

   - Errors in zone definitions that cause overlapping zones, etc.,
     may cause erroneous responses to queries, but problem
     determination is simplified, and the contents of one "bad"
     zone can't corrupt another.

   - Since the cache is most frequently updated, it is most
     vulnerable to corruption during system restarts.  It can also
     become full of expired RR data.  In either case, it can easily
     be discarded without disturbing zone data.

A major aspect of database design is selecting a structure which allows
the name server to deal with crashes of the name server's host.  State
information which a name server should save across system crashes



Mockapetris                                                    [Page 38]

RFC 1035        Domain Implementation and Specification    November 1987


includes the catalog structure (including the state of refreshing for
each zone) and the zone data itself.

6.1.3. Time

Both the TTL data for RRs and the timing data for refreshing activities
depends on 32 bit timers in units of seconds.  Inside the database,
refresh timers and TTLs for cached data conceptually "count down", while
data in the zone stays with constant TTLs.

A recommended implementation strategy is to store time in two ways:  as
a relative increment and as an absolute time.  One way to do this is to
use positive 32 bit numbers for one type and negative numbers for the
other.  The RRs in zones use relative times; the refresh timers and
cache data use absolute times.  Absolute numbers are taken with respect
to some known origin and converted to relative values when placed in the
response to a query.  When an absolute TTL is negative after conversion
to relative, then the data is expired and should be ignored.

6.2. Standard query processing

The major algorithm for standard query processing is presented in
[RFC-1034].

When processing queries with QCLASS=*, or some other QCLASS which
matches multiple classes, the response should never be authoritative
unless the server can guarantee that the response covers all classes.

When composing a response, RRs which are to be inserted in the
additional section, but duplicate RRs in the answer or authority
sections, may be omitted from the additional section.

When a response is so long that truncation is required, the truncation
should start at the end of the response and work forward in the
datagram.  Thus if there is any data for the authority section, the
answer section is guaranteed to be unique.

The MINIMUM value in the SOA should be used to set a floor on the TTL of
data distributed from a zone.  This floor function should be done when
the data is copied into a response.  This will allow future dynamic
update protocols to change the SOA MINIMUM field without ambiguous
semantics.

6.3. Zone refresh and reload processing

In spite of a server's best efforts, it may be unable to load zone data
from a master file due to syntax errors, etc., or be unable to refresh a
zone within the its expiration parameter.  In this case, the name server



Mockapetris                                                    [Page 39]

RFC 1035        Domain Implementation and Specification    November 1987


should answer queries as if it were not supposed to possess the zone.

If a master is sending a zone out via AXFR, and a new version is created
during the transfer, the master should continue to send the old version
if possible.  In any case, it should never send part of one version and
part of another.  If completion is not possible, the master should reset
the connection on which the zone transfer is taking place.

6.4. Inverse queries (Optional)

Inverse queries are an optional part of the DNS.  Name servers are not
required to support any form of inverse queries.  If a name server
receives an inverse query that it does not support, it returns an error
response with the "Not Implemented" error set in the header.  While
inverse query support is optional, all name servers must be at least
able to return the error response.

6.4.1. The contents of inverse queries and responses          Inverse
queries reverse the mappings performed by standard query operations;
while a standard query maps a domain name to a resource, an inverse
query maps a resource to a domain name.  For example, a standard query
might bind a domain name to a host address; the corresponding inverse
query binds the host address to a domain name.

Inverse queries take the form of a single RR in the answer section of
the message, with an empty question section.  The owner name of the
query RR and its TTL are not significant.  The response carries
questions in the question section which identify all names possessing
the query RR WHICH THE NAME SERVER KNOWS.  Since no name server knows
about all of the domain name space, the response can never be assumed to
be complete.  Thus inverse queries are primarily useful for database
management and debugging activities.  Inverse queries are NOT an
acceptable method of mapping host addresses to host names; use the IN-
ADDR.ARPA domain instead.

Where possible, name servers should provide case-insensitive comparisons
for inverse queries.  Thus an inverse query asking for an MX RR of
"Venera.isi.edu" should get the same response as a query for
"VENERA.ISI.EDU"; an inverse query for HINFO RR "IBM-PC UNIX" should
produce the same result as an inverse query for "IBM-pc unix".  However,
this cannot be guaranteed because name servers may possess RRs that
contain character strings but the name server does not know that the
data is character.

When a name server processes an inverse query, it either returns:

   1. zero, one, or multiple domain names for the specified
      resource as QNAMEs in the question section



Mockapetris                                                    [Page 40]

RFC 1035        Domain Implementation and Specification    November 1987


   2. an error code indicating that the name server doesn't support
      inverse mapping of the specified resource type.

When the response to an inverse query contains one or more QNAMEs, the
owner name and TTL of the RR in the answer section which defines the
inverse query is modified to exactly match an RR found at the first
QNAME.

RRs returned in the inverse queries cannot be cached using the same
mechanism as is used for the replies to standard queries.  One reason
for this is that a name might have multiple RRs of the same type, and
only one would appear.  For example, an inverse query for a single
address of a multiply homed host might create the impression that only
one address existed.

6.4.2. Inverse query and response example          The overall structure
of an inverse query for retrieving the domain name that corresponds to
Internet address 10.1.0.52 is shown below:

                         +-----------------------------------------+
           Header        |          OPCODE=IQUERY, ID=997          |
                         +-----------------------------------------+
          Question       |                 <empty>                 |
                         +-----------------------------------------+
           Answer        |        <anyname> A IN 10.1.0.52         |
                         +-----------------------------------------+
          Authority      |                 <empty>                 |
                         +-----------------------------------------+
         Additional      |                 <empty>                 |
                         +-----------------------------------------+

This query asks for a question whose answer is the Internet style
address 10.1.0.52.  Since the owner name is not known, any domain name
can be used as a placeholder (and is ignored).  A single octet of zero,
signifying the root, is usually used because it minimizes the length of
the message.  The TTL of the RR is not significant.  The response to
this query might be:














Mockapetris                                                    [Page 41]

RFC 1035        Domain Implementation and Specification    November 1987


                         +-----------------------------------------+
           Header        |         OPCODE=RESPONSE, ID=997         |
                         +-----------------------------------------+
          Question       |QTYPE=A, QCLASS=IN, QNAME=VENERA.ISI.EDU |
                         +-----------------------------------------+
           Answer        |  VENERA.ISI.EDU  A IN 10.1.0.52         |
                         +-----------------------------------------+
          Authority      |                 <empty>                 |
                         +-----------------------------------------+
         Additional      |                 <empty>                 |
                         +-----------------------------------------+

Note that the QTYPE in a response to an inverse query is the same as the
TYPE field in the answer section of the inverse query.  Responses to
inverse queries may contain multiple questions when the inverse is not
unique.  If the question section in the response is not empty, then the
RR in the answer section is modified to correspond to be an exact copy
of an RR at the first QNAME.

6.4.3. Inverse query processing

Name servers that support inverse queries can support these operations
through exhaustive searches of their databases, but this becomes
impractical as the size of the database increases.  An alternative
approach is to invert the database according to the search key.

For name servers that support multiple zones and a large amount of data,
the recommended approach is separate inversions for each zone.  When a
particular zone is changed during a refresh, only its inversions need to
be redone.

Support for transfer of this type of inversion may be included in future
versions of the domain system, but is not supported in this version.

6.5. Completion queries and responses

The optional completion services described in RFC-882 and RFC-883 have
been deleted.  Redesigned services may become available in the future.













Mockapetris                                                    [Page 42]

RFC 1035        Domain Implementation and Specification    November 1987


7. RESOLVER IMPLEMENTATION

The top levels of the recommended resolver algorithm are discussed in
[RFC-1034].  This section discusses implementation details assuming the
database structure suggested in the name server implementation section
of this memo.

7.1. Transforming a user request into a query

The first step a resolver takes is to transform the client's request,
stated in a format suitable to the local OS, into a search specification
for RRs at a specific name which match a specific QTYPE and QCLASS.
Where possible, the QTYPE and QCLASS should correspond to a single type
and a single class, because this makes the use of cached data much
simpler.  The reason for this is that the presence of data of one type
in a cache doesn't confirm the existence or non-existence of data of
other types, hence the only way to be sure is to consult an
authoritative source.  If QCLASS=* is used, then authoritative answers
won't be available.

Since a resolver must be able to multiplex multiple requests if it is to
perform its function efficiently, each pending request is usually
represented in some block of state information.  This state block will
typically contain:

   - A timestamp indicating the time the request began.
     The timestamp is used to decide whether RRs in the database
     can be used or are out of date.  This timestamp uses the
     absolute time format previously discussed for RR storage in
     zones and caches.  Note that when an RRs TTL indicates a
     relative time, the RR must be timely, since it is part of a
     zone.  When the RR has an absolute time, it is part of a
     cache, and the TTL of the RR is compared against the timestamp
     for the start of the request.

     Note that using the timestamp is superior to using a current
     time, since it allows RRs with TTLs of zero to be entered in
     the cache in the usual manner, but still used by the current
     request, even after intervals of many seconds due to system
     load, query retransmission timeouts, etc.

   - Some sort of parameters to limit the amount of work which will
     be performed for this request.

     The amount of work which a resolver will do in response to a
     client request must be limited to guard against errors in the
     database, such as circular CNAME references, and operational
     problems, such as network partition which prevents the



Mockapetris                                                    [Page 43]

RFC 1035        Domain Implementation and Specification    November 1987


     resolver from accessing the name servers it needs.  While
     local limits on the number of times a resolver will retransmit
     a particular query to a particular name server address are
     essential, the resolver should have a global per-request
     counter to limit work on a single request.  The counter should
     be set to some initial value and decremented whenever the
     resolver performs any action (retransmission timeout,
     retransmission, etc.)  If the counter passes zero, the request
     is terminated with a temporary error.

     Note that if the resolver structure allows one request to
     start others in parallel, such as when the need to access a
     name server for one request causes a parallel resolve for the
     name server's addresses, the spawned request should be started
     with a lower counter.  This prevents circular references in
     the database from starting a chain reaction of resolver
     activity.

   - The SLIST data structure discussed in [RFC-1034].

     This structure keeps track of the state of a request if it
     must wait for answers from foreign name servers.

7.2. Sending the queries

As described in [RFC-1034], the basic task of the resolver is to
formulate a query which will answer the client's request and direct that
query to name servers which can provide the information.  The resolver
will usually only have very strong hints about which servers to ask, in
the form of NS RRs, and may have to revise the query, in response to
CNAMEs, or revise the set of name servers the resolver is asking, in
response to delegation responses which point the resolver to name
servers closer to the desired information.  In addition to the
information requested by the client, the resolver may have to call upon
its own services to determine the address of name servers it wishes to
contact.

In any case, the model used in this memo assumes that the resolver is
multiplexing attention between multiple requests, some from the client,
and some internally generated.  Each request is represented by some
state information, and the desired behavior is that the resolver
transmit queries to name servers in a way that maximizes the probability
that the request is answered, minimizes the time that the request takes,
and avoids excessive transmissions.  The key algorithm uses the state
information of the request to select the next name server address to
query, and also computes a timeout which will cause the next action
should a response not arrive.  The next action will usually be a
transmission to some other server, but may be a temporary error to the



Mockapetris                                                    [Page 44]

RFC 1035        Domain Implementation and Specification    November 1987


client.

The resolver always starts with a list of server names to query (SLIST).
This list will be all NS RRs which correspond to the nearest ancestor
zone that the resolver knows about.  To avoid startup problems, the
resolver should have a set of default servers which it will ask should
it have no current NS RRs which are appropriate.  The resolver then adds
to SLIST all of the known addresses for the name servers, and may start
parallel requests to acquire the addresses of the servers when the
resolver has the name, but no addresses, for the name servers.

To complete initialization of SLIST, the resolver attaches whatever
history information it has to the each address in SLIST.  This will
usually consist of some sort of weighted averages for the response time
of the address, and the batting average of the address (i.e., how often
the address responded at all to the request).  Note that this
information should be kept on a per address basis, rather than on a per
name server basis, because the response time and batting average of a
particular server may vary considerably from address to address.  Note
also that this information is actually specific to a resolver address /
server address pair, so a resolver with multiple addresses may wish to
keep separate histories for each of its addresses.  Part of this step
must deal with addresses which have no such history; in this case an
expected round trip time of 5-10 seconds should be the worst case, with
lower estimates for the same local network, etc.

Note that whenever a delegation is followed, the resolver algorithm
reinitializes SLIST.

The information establishes a partial ranking of the available name
server addresses.  Each time an address is chosen and the state should
be altered to prevent its selection again until all other addresses have
been tried.  The timeout for each transmission should be 50-100% greater
than the average predicted value to allow for variance in response.

Some fine points:

   - The resolver may encounter a situation where no addresses are
     available for any of the name servers named in SLIST, and
     where the servers in the list are precisely those which would
     normally be used to look up their own addresses.  This
     situation typically occurs when the glue address RRs have a
     smaller TTL than the NS RRs marking delegation, or when the
     resolver caches the result of a NS search.  The resolver
     should detect this condition and restart the search at the
     next ancestor zone, or alternatively at the root.





Mockapetris                                                    [Page 45]

RFC 1035        Domain Implementation and Specification    November 1987


   - If a resolver gets a server error or other bizarre response
     from a name server, it should remove it from SLIST, and may
     wish to schedule an immediate transmission to the next
     candidate server address.

7.3. Processing responses

The first step in processing arriving response datagrams is to parse the
response.  This procedure should include:

   - Check the header for reasonableness.  Discard datagrams which
     are queries when responses are expected.

   - Parse the sections of the message, and insure that all RRs are
     correctly formatted.

   - As an optional step, check the TTLs of arriving data looking
     for RRs with excessively long TTLs.  If a RR has an
     excessively long TTL, say greater than 1 week, either discard
     the whole response, or limit all TTLs in the response to 1
     week.

The next step is to match the response to a current resolver request.
The recommended strategy is to do a preliminary matching using the ID
field in the domain header, and then to verify that the question section
corresponds to the information currently desired.  This requires that
the transmission algorithm devote several bits of the domain ID field to
a request identifier of some sort.  This step has several fine points:

   - Some name servers send their responses from different
     addresses than the one used to receive the query.  That is, a
     resolver cannot rely that a response will come from the same
     address which it sent the corresponding query to.  This name
     server bug is typically encountered in UNIX systems.

   - If the resolver retransmits a particular request to a name
     server it should be able to use a response from any of the
     transmissions.  However, if it is using the response to sample
     the round trip time to access the name server, it must be able
     to determine which transmission matches the response (and keep
     transmission times for each outgoing message), or only
     calculate round trip times based on initial transmissions.

   - A name server will occasionally not have a current copy of a
     zone which it should have according to some NS RRs.  The
     resolver should simply remove the name server from the current
     SLIST, and continue.




Mockapetris                                                    [Page 46]

RFC 1035        Domain Implementation and Specification    November 1987


7.4. Using the cache

In general, we expect a resolver to cache all data which it receives in
responses since it may be useful in answering future client requests.
However, there are several types of data which should not be cached:

   - When several RRs of the same type are available for a
     particular owner name, the resolver should either cache them
     all or none at all.  When a response is truncated, and a
     resolver doesn't know whether it has a complete set, it should
     not cache a possibly partial set of RRs.

   - Cached data should never be used in preference to
     authoritative data, so if caching would cause this to happen
     the data should not be cached.

   - The results of an inverse query should not be cached.

   - The results of standard queries where the QNAME contains "*"
     labels if the data might be used to construct wildcards.  The
     reason is that the cache does not necessarily contain existing
     RRs or zone boundary information which is necessary to
     restrict the application of the wildcard RRs.

   - RR data in responses of dubious reliability.  When a resolver
     receives unsolicited responses or RR data other than that
     requested, it should discard it without caching it.  The basic
     implication is that all sanity checks on a packet should be
     performed before any of it is cached.

In a similar vein, when a resolver has a set of RRs for some name in a
response, and wants to cache the RRs, it should check its cache for
already existing RRs.  Depending on the circumstances, either the data
in the response or the cache is preferred, but the two should never be
combined.  If the data in the response is from authoritative data in the
answer section, it is always preferred.

8. MAIL SUPPORT

The domain system defines a standard for mapping mailboxes into domain
names, and two methods for using the mailbox information to derive mail
routing information.  The first method is called mail exchange binding
and the other method is mailbox binding.  The mailbox encoding standard
and mail exchange binding are part of the DNS official protocol, and are
the recommended method for mail routing in the Internet.  Mailbox
binding is an experimental feature which is still under development and
subject to change.




Mockapetris                                                    [Page 47]

RFC 1035        Domain Implementation and Specification    November 1987


The mailbox encoding standard assumes a mailbox name of the form
"<local-part>@<mail-domain>".  While the syntax allowed in each of these
sections varies substantially between the various mail internets, the
preferred syntax for the ARPA Internet is given in [RFC-822].

The DNS encodes the <local-part> as a single label, and encodes the
<mail-domain> as a domain name.  The single label from the <local-part>
is prefaced to the domain name from <mail-domain> to form the domain
name corresponding to the mailbox.  Thus the mailbox HOSTMASTER@SRI-
NIC.ARPA is mapped into the domain name HOSTMASTER.SRI-NIC.ARPA.  If the
<local-part> contains dots or other special characters, its
representation in a master file will require the use of backslash
quoting to ensure that the domain name is properly encoded.  For
example, the mailbox Action.domains@ISI.EDU would be represented as
Action\.domains.ISI.EDU.

8.1. Mail exchange binding

Mail exchange binding uses the <mail-domain> part of a mailbox
specification to determine where mail should be sent.  The <local-part>
is not even consulted.  [RFC-974] specifies this method in detail, and
should be consulted before attempting to use mail exchange support.

One of the advantages of this method is that it decouples mail
destination naming from the hosts used to support mail service, at the
cost of another layer of indirection in the lookup function.  However,
the addition layer should eliminate the need for complicated "%", "!",
etc encodings in <local-part>.

The essence of the method is that the <mail-domain> is used as a domain
name to locate type MX RRs which list hosts willing to accept mail for
<mail-domain>, together with preference values which rank the hosts
according to an order specified by the administrators for <mail-domain>.

In this memo, the <mail-domain> ISI.EDU is used in examples, together
with the hosts VENERA.ISI.EDU and VAXA.ISI.EDU as mail exchanges for
ISI.EDU.  If a mailer had a message for Mockapetris@ISI.EDU, it would
route it by looking up MX RRs for ISI.EDU.  The MX RRs at ISI.EDU name
VENERA.ISI.EDU and VAXA.ISI.EDU, and type A queries can find the host
addresses.

8.2. Mailbox binding (Experimental)

In mailbox binding, the mailer uses the entire mail destination
specification to construct a domain name.  The encoded domain name for
the mailbox is used as the QNAME field in a QTYPE=MAILB query.

Several outcomes are possible for this query:



Mockapetris                                                    [Page 48]

RFC 1035        Domain Implementation and Specification    November 1987


   1. The query can return a name error indicating that the mailbox
      does not exist as a domain name.

      In the long term, this would indicate that the specified
      mailbox doesn't exist.  However, until the use of mailbox
      binding is universal, this error condition should be
      interpreted to mean that the organization identified by the
      global part does not support mailbox binding.  The
      appropriate procedure is to revert to exchange binding at
      this point.

   2. The query can return a Mail Rename (MR) RR.

      The MR RR carries new mailbox specification in its RDATA
      field.  The mailer should replace the old mailbox with the
      new one and retry the operation.

   3. The query can return a MB RR.

      The MB RR carries a domain name for a host in its RDATA
      field.  The mailer should deliver the message to that host
      via whatever protocol is applicable, e.g., b,SMTP.

   4. The query can return one or more Mail Group (MG) RRs.

      This condition means that the mailbox was actually a mailing
      list or mail group, rather than a single mailbox.  Each MG RR
      has a RDATA field that identifies a mailbox that is a member
      of the group.  The mailer should deliver a copy of the
      message to each member.

   5. The query can return a MB RR as well as one or more MG RRs.

      This condition means the the mailbox was actually a mailing
      list.  The mailer can either deliver the message to the host
      specified by the MB RR, which will in turn do the delivery to
      all members, or the mailer can use the MG RRs to do the
      expansion itself.

In any of these cases, the response may include a Mail Information
(MINFO) RR.  This RR is usually associated with a mail group, but is
legal with a MB.  The MINFO RR identifies two mailboxes.  One of these
identifies a responsible person for the original mailbox name.  This
mailbox should be used for requests to be added to a mail group, etc.
The second mailbox name in the MINFO RR identifies a mailbox that should
receive error messages for mail failures.  This is particularly
appropriate for mailing lists when errors in member names should be
reported to a person other than the one who sends a message to the list.



Mockapetris                                                    [Page 49]

RFC 1035        Domain Implementation and Specification    November 1987


New fields may be added to this RR in the future.


9. REFERENCES and BIBLIOGRAPHY

[Dyer 87]       S. Dyer, F. Hsu, "Hesiod", Project Athena
                Technical Plan - Name Service, April 1987, version 1.9.

                Describes the fundamentals of the Hesiod name service.

[IEN-116]       J. Postel, "Internet Name Server", IEN-116,
                USC/Information Sciences Institute, August 1979.

                A name service obsoleted by the Domain Name System, but
                still in use.

[Quarterman 86] J. Quarterman, and J. Hoskins, "Notable Computer Networks",
                Communications of the ACM, October 1986, volume 29, number
                10.

[RFC-742]       K. Harrenstien, "NAME/FINGER", RFC-742, Network
                Information Center, SRI International, December 1977.

[RFC-768]       J. Postel, "User Datagram Protocol", RFC-768,
                USC/Information Sciences Institute, August 1980.

[RFC-793]       J. Postel, "Transmission Control Protocol", RFC-793,
                USC/Information Sciences Institute, September 1981.

[RFC-799]       D. Mills, "Internet Name Domains", RFC-799, COMSAT,
                September 1981.

                Suggests introduction of a hierarchy in place of a flat
                name space for the Internet.

[RFC-805]       J. Postel, "Computer Mail Meeting Notes", RFC-805,
                USC/Information Sciences Institute, February 1982.

[RFC-810]       E. Feinler, K. Harrenstien, Z. Su, and V. White, "DOD
                Internet Host Table Specification", RFC-810, Network
                Information Center, SRI International, March 1982.

                Obsolete.  See RFC-952.

[RFC-811]       K. Harrenstien, V. White, and E. Feinler, "Hostnames
                Server", RFC-811, Network Information Center, SRI
                International, March 1982.




Mockapetris                                                    [Page 50]

RFC 1035        Domain Implementation and Specification    November 1987


                Obsolete.  See RFC-953.

[RFC-812]       K. Harrenstien, and V. White, "NICNAME/WHOIS", RFC-812,
                Network Information Center, SRI International, March
                1982.

[RFC-819]       Z. Su, and J. Postel, "The Domain Naming Convention for
                Internet User Applications", RFC-819, Network
                Information Center, SRI International, August 1982.

                Early thoughts on the design of the domain system.
                Current implementation is completely different.

[RFC-821]       J. Postel, "Simple Mail Transfer Protocol", RFC-821,
                USC/Information Sciences Institute, August 1980.

[RFC-830]       Z. Su, "A Distributed System for Internet Name Service",
                RFC-830, Network Information Center, SRI International,
                October 1982.

                Early thoughts on the design of the domain system.
                Current implementation is completely different.

[RFC-882]       P. Mockapetris, "Domain names - Concepts and
                Facilities," RFC-882, USC/Information Sciences
                Institute, November 1983.

                Superceeded by this memo.

[RFC-883]       P. Mockapetris, "Domain names - Implementation and
                Specification," RFC-883, USC/Information Sciences
                Institute, November 1983.

                Superceeded by this memo.

[RFC-920]       J. Postel and J. Reynolds, "Domain Requirements",
                RFC-920, USC/Information Sciences Institute,
                October 1984.

                Explains the naming scheme for top level domains.

[RFC-952]       K. Harrenstien, M. Stahl, E. Feinler, "DoD Internet Host
                Table Specification", RFC-952, SRI, October 1985.

                Specifies the format of HOSTS.TXT, the host/address
                table replaced by the DNS.





Mockapetris                                                    [Page 51]

RFC 1035        Domain Implementation and Specification    November 1987


[RFC-953]       K. Harrenstien, M. Stahl, E. Feinler, "HOSTNAME Server",
                RFC-953, SRI, October 1985.

                This RFC contains the official specification of the
                hostname server protocol, which is obsoleted by the DNS.
                This TCP based protocol accesses information stored in
                the RFC-952 format, and is used to obtain copies of the
                host table.

[RFC-973]       P. Mockapetris, "Domain System Changes and
                Observations", RFC-973, USC/Information Sciences
                Institute, January 1986.

                Describes changes to RFC-882 and RFC-883 and reasons for
                them.

[RFC-974]       C. Partridge, "Mail routing and the domain system",
                RFC-974, CSNET CIC BBN Labs, January 1986.

                Describes the transition from HOSTS.TXT based mail
                addressing to the more powerful MX system used with the
                domain system.

[RFC-1001]      NetBIOS Working Group, "Protocol standard for a NetBIOS
                service on a TCP/UDP transport: Concepts and Methods",
                RFC-1001, March 1987.

                This RFC and RFC-1002 are a preliminary design for
                NETBIOS on top of TCP/IP which proposes to base NetBIOS
                name service on top of the DNS.

[RFC-1002]      NetBIOS Working Group, "Protocol standard for a NetBIOS
                service on a TCP/UDP transport: Detailed
                Specifications", RFC-1002, March 1987.

[RFC-1010]      J. Reynolds, and J. Postel, "Assigned Numbers", RFC-1010,
                USC/Information Sciences Institute, May 1987.

                Contains socket numbers and mnemonics for host names,
                operating systems, etc.

[RFC-1031]      W. Lazear, "MILNET Name Domain Transition", RFC-1031,
                November 1987.

                Describes a plan for converting the MILNET to the DNS.

[RFC-1032]      M. Stahl, "Establishing a Domain - Guidelines for
                Administrators", RFC-1032, November 1987.



Mockapetris                                                    [Page 52]

RFC 1035        Domain Implementation and Specification    November 1987


                Describes the registration policies used by the NIC to
                administer the top level domains and delegate subzones.

[RFC-1033]      M. Lottor, "Domain Administrators Operations Guide",
                RFC-1033, November 1987.

                A cookbook for domain administrators.

[Solomon 82]    M. Solomon, L. Landweber, and D. Neuhengen, "The CSNET
                Name Server", Computer Networks, vol 6, nr 3, July 1982.

                Describes a name service for CSNET which is independent
                from the DNS and DNS use in the CSNET.






































Mockapetris                                                    [Page 53]

RFC 1035        Domain Implementation and Specification    November 1987


Index

          *   13

          ;   33, 35

          <character-string>   35
          <domain-name>   34

          @   35

          \   35

          A   12

          Byte order   8

          CH   13
          Character case   9
          CLASS   11
          CNAME   12
          Completion   42
          CS   13

          Hesiod   13
          HINFO   12
          HS   13

          IN   13
          IN-ADDR.ARPA domain   22
          Inverse queries   40

          Mailbox names   47
          MB   12
          MD   12
          MF   12
          MG   12
          MINFO   12
          MINIMUM   20
          MR   12
          MX   12

          NS   12
          NULL   12

          Port numbers   32
          Primary server   5
          PTR   12, 18



Mockapetris                                                    [Page 54]

RFC 1035        Domain Implementation and Specification    November 1987


          QCLASS   13
          QTYPE   12

          RDATA   12
          RDLENGTH  11

          Secondary server   5
          SOA   12
          Stub resolvers   7

          TCP   32
          TXT   12
          TYPE   11

          UDP   32

          WKS   12


































Mockapetris                                                    [Page 55]

<?php

namespace React\Dns\Resolver;

use React\Dns\Query\ExecutorInterface;
use React\Dns\Query\Query;
use React\Dns\RecordNotFoundException;
use React\Dns\Model\Message;

class Resolver
{
    private $nameserver;
    private $executor;

    public function __construct($nameserver, ExecutorInterface $executor)
    {
        $this->nameserver = $nameserver;
        $this->executor = $executor;
    }

    public function resolve($domain)
    {
        $that = $this;

        $query = new Query($domain, Message::TYPE_A, Message::CLASS_IN, time());

        return $this->executor
            ->query($this->nameserver, $query)
            ->then(function (Message $response) use ($that) {
                return $that->extractAddress($response, Message::TYPE_A);
            });
    }

    public function extractAddress(Message $response, $type)
    {
        $answer = $this->pickRandomAnswerOfType($response, $type);
        $address = $answer->data;
        return $address;
    }

    public function pickRandomAnswerOfType(Message $response, $type)
    {
        // TODO: filter by name to make sure domain matches
        // TODO: resolve CNAME aliases

        $filteredAnswers = array_filter($response->answers, function ($answer) use ($type) {
            return $type === $answer->type;
        });

        if (0 === count($filteredAnswers)) {
            $message = sprintf('DNS Request did not return valid answer. Received answers: %s', json_encode($response->answers));
            throw new RecordNotFoundException($message);
        }

        $answer = $filteredAnswers[array_rand($filteredAnswers)];

        return $answer;
    }
}
<?php

namespace React\Dns\Resolver;

use React\Cache\ArrayCache;
use React\Dns\Query\Executor;
use React\Dns\Query\CachedExecutor;
use React\Dns\Query\RecordCache;
use React\Dns\Protocol\Parser;
use React\Dns\Protocol\BinaryDumper;
use React\EventLoop\LoopInterface;
use React\Dns\Query\RetryExecutor;

class Factory
{
    public function create($nameserver, LoopInterface $loop)
    {
        $nameserver = $this->addPortToServerIfMissing($nameserver);
        $executor = $this->createRetryExecutor($loop);

        return new Resolver($nameserver, $executor);
    }

    public function createCached($nameserver, LoopInterface $loop)
    {
        $nameserver = $this->addPortToServerIfMissing($nameserver);
        $executor = $this->createCachedExecutor($loop);

        return new Resolver($nameserver, $executor);
    }

    protected function createExecutor(LoopInterface $loop)
    {
        return new Executor($loop, new Parser(), new BinaryDumper());
    }

    protected function createRetryExecutor(LoopInterface $loop)
    {
        return new RetryExecutor($this->createExecutor($loop));
    }

    protected function createCachedExecutor(LoopInterface $loop)
    {
        return new CachedExecutor($this->createRetryExecutor($loop), new RecordCache(new ArrayCache()));
    }

    protected function addPortToServerIfMissing($nameserver)
    {
        return false === strpos($nameserver, ':') ? "$nameserver:53" : $nameserver;
    }
}
<?php

namespace React\Dns;

class BadServerException extends \Exception
{
}
<?php

namespace React\Socket;

class ConnectionException extends \ErrorException
{
}
<?php

namespace React\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

/** @event connection */
class Server extends EventEmitter implements ServerInterface
{
    public $master;
    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function listen($port, $host = '127.0.0.1')
    {
        $this->master = @stream_socket_server("tcp://$host:$port", $errno, $errstr);
        if (false === $this->master) {
            $message = "Could not bind to tcp://$host:$port: $errstr";
            throw new ConnectionException($message, $errno);
        }
        stream_set_blocking($this->master, 0);

        $that = $this;

        $this->loop->addReadStream($this->master, function ($master) use ($that) {
            $newSocket = stream_socket_accept($master);
            if (false === $newSocket) {
                $that->emit('error', array(new \RuntimeException('Error accepting new connection')));

                return;
            }
            $that->handleConnection($newSocket);
        });
    }

    public function handleConnection($socket)
    {
        stream_set_blocking($socket, 0);

        $client = $this->createConnection($socket);

        $this->emit('connection', array($client));
    }

    public function getPort()
    {
        $name = stream_socket_get_name($this->master, false);

        return (int) substr(strrchr($name, ':'), 1);
    }

    public function shutdown()
    {
        $this->loop->removeStream($this->master);
        fclose($this->master);
        $this->removeAllListeners();
    }

    public function createConnection($socket)
    {
        return new Connection($socket, $this->loop);
    }
}
# Socket Component

Library for building an evented socket server.

The socket component provides a more usable interface for a socket-layer
server or client based on the `EventLoop` and `Stream` components.

## Server

The server can listen on a port and will emit a `connection` event whenever a
client connects.

## Connection

The connection is a readable and writable stream. It can be used in a server
or in a client context.

## Usage

Here is a server that closes the connection if you send it anything.

    $loop = React\EventLoop\Factory::create();

    $socket = new React\Socket\Server($loop);
    $socket->on('connection', function ($conn) {
        $conn->write("Hello there!\n");
        $conn->write("Welcome to this amazing server!\n");
        $conn->write("Here's a tip: don't say anything.\n");

        $conn->on('data', function ($data) use ($conn) {
            $conn->close();
        });
    });
    $socket->listen(1337);

    $loop->run();

Here's a client that outputs the output of said server and then attempts to
send it a string.

    $loop = React\EventLoop\Factory::create();

    $client = stream_socket_client('tcp://127.0.0.1:1337');
    $conn = new React\Socket\Connection($client, $loop);
    $conn->pipe(new React\Stream\Stream(STDOUT, $loop));
    $conn->write("Hello World!\n");

    $loop->run();
{
    "name": "react/socket",
    "description": "Library for building an evented socket server.",
    "keywords": ["socket"],
    "license": "MIT",
    "require": {
        "php": ">=5.3.3",
        "evenement/evenement": "1.0.*",
        "react/event-loop": "0.3.*",
        "react/stream": "0.3.*"
    },
    "autoload": {
        "psr-0": { "React\\Socket": "" }
    },
    "target-dir": "React/Socket",
    "extra": {
        "branch-alias": {
            "dev-master": "0.3-dev"
        }
    }
}
<?php

namespace React\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Buffer;
use React\Stream\Stream;
use React\Stream\Util;

class Connection extends Stream implements ConnectionInterface
{
    public function handleData($stream)
    {
        $data = stream_socket_recvfrom($stream, $this->bufferSize);
        if ('' === $data || false === $data) {
            $this->end();
        } else {
            $this->emit('data', array($data, $this));
        }
    }

    public function handleClose()
    {
        if (is_resource($this->stream)) {
            stream_socket_shutdown($this->stream, STREAM_SHUT_RDWR);
            fclose($this->stream);
        }
    }

    public function getRemoteAddress()
    {
        return $this->parseAddress(stream_socket_get_name($this->stream, true));
    }

    private function parseAddress($address)
    {
        return trim(substr($address, 0, strrpos($address, ':')), '[]');
    }
}
<?php

namespace React\Socket;

use Evenement\EventEmitterInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;

interface ConnectionInterface extends ReadableStreamInterface, WritableStreamInterface
{
    public function getRemoteAddress();
}
<?php

namespace React\Socket;

use Evenement\EventEmitterInterface;

/** @event connection */
interface ServerInterface extends EventEmitterInterface
{
    public function listen($port, $host = '127.0.0.1');
    public function getPort();
    public function shutdown();
}
<?php

namespace React\SocketClient;

class ConnectionException extends \RuntimeException
{
}
<?php

namespace React\SocketClient;

interface ConnectorInterface
{
    public function create($host, $port);
}
<?php

namespace React\SocketClient;

use React\Promise\ResolverInterface;
use React\Promise\Deferred;
use React\Stream\Stream;
use React\EventLoop\LoopInterface;
use UnexpectedValueException;

/**
 * This class is considered internal and its API should not be relied upon
 * outside of SocketClient
 */
class StreamEncryption
{
    private $loop;
    private $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;

    private $errstr;
    private $errno;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function enable(Stream $stream)
    {
        return $this->toggle($stream, true);
    }

    public function disable(Stream $stream)
    {
        return $this->toggle($stream, false);
    }

    public function toggle(Stream $stream, $toggle)
    {
        // pause actual stream instance to continue operation on raw stream socket
        $stream->pause();

        // TODO: add write() event to make sure we're not sending any excessive data

        $deferred = new Deferred();

        // get actual stream socket from stream instance
        $socket = $stream->stream;

        $that = $this;
        $toggleCrypto = function () use ($that, $socket, $deferred, $toggle) {
            $that->toggleCrypto($socket, $deferred, $toggle);
        };

        $this->loop->addWriteStream($socket, $toggleCrypto);
        $this->loop->addReadStream($socket, $toggleCrypto);
        $toggleCrypto();

        return $deferred->then(function () use ($stream) {
            $stream->resume();
            return $stream;
        }, function($error) use ($stream) {
            $stream->resume();
            throw $error;
        });
    }

    public function toggleCrypto($socket, ResolverInterface $resolver, $toggle)
    {
        set_error_handler(array($this, 'handleError'));
        $result = stream_socket_enable_crypto($socket, $toggle, $this->method);
        restore_error_handler();

        if (true === $result) {
            $this->loop->removeWriteStream($socket);
            $this->loop->removeReadStream($socket);

            $resolver->resolve();
        } else if (false === $result) {
            $this->loop->removeWriteStream($socket);
            $this->loop->removeReadStream($socket);

            $resolver->reject(new UnexpectedValueException(
                sprintf("Unable to complete SSL/TLS handshake: %s", $this->errstr),
                $this->errno
            ));
        } else {
            // need more data, will retry
        }
    }

    public function handleError($errno, $errstr)
    {
        $this->errstr = str_replace(array("\r", "\n"), ' ', $errstr);
        $this->errno  = $errno;
    }
}
<?php

namespace React\SocketClient;

use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Resolver;
use React\Stream\Stream;
use React\Promise\When;
use React\Promise\Deferred;

class Connector implements ConnectorInterface
{
    private $loop;
    private $resolver;

    public function __construct(LoopInterface $loop, Resolver $resolver)
    {
        $this->loop = $loop;
        $this->resolver = $resolver;
    }

    public function create($host, $port)
    {
        $that = $this;

        return $this
            ->resolveHostname($host)
            ->then(function ($address) use ($port, $that) {
                return $that->createSocketForAddress($address, $port);
            });
    }

    public function createSocketForAddress($address, $port)
    {
        $url = $this->getSocketUrl($address, $port);

        $socket = stream_socket_client($url, $errno, $errstr, 0, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);

        if (!$socket) {
            return When::reject(new \RuntimeException(
                sprintf("connection to %s:%d failed: %s", $address, $port, $errstr),
                $errno
            ));
        }

        stream_set_blocking($socket, 0);

        // wait for connection

        return $this
            ->waitForStreamOnce($socket)
            ->then(array($this, 'checkConnectedSocket'))
            ->then(array($this, 'handleConnectedSocket'));
    }

    protected function waitForStreamOnce($stream)
    {
        $deferred = new Deferred();

        $loop = $this->loop;

        $this->loop->addWriteStream($stream, function ($stream) use ($loop, $deferred) {
            $loop->removeWriteStream($stream);

            $deferred->resolve($stream);
        });

        return $deferred->promise();
    }

    public function checkConnectedSocket($socket)
    {
        // The following hack looks like the only way to
        // detect connection refused errors with PHP's stream sockets.
        if (false === stream_socket_get_name($socket, true)) {
            return When::reject(new ConnectionException('Connection refused'));
        }

        return When::resolve($socket);
    }

    public function handleConnectedSocket($socket)
    {
        return new Stream($socket, $this->loop);
    }

    protected function getSocketUrl($host, $port)
    {
        if (strpos($host, ':') !== false) {
            // enclose IPv6 addresses in square brackets before appending port
            $host = '[' . $host . ']';
        }
        return sprintf('tcp://%s:%s', $host, $port);
    }

    protected function resolveHostname($host)
    {
        if (false !== filter_var($host, FILTER_VALIDATE_IP)) {
            return When::resolve($host);
        }

        return $this->resolver->resolve($host);
    }
}
# SocketClient Component

Async Connector to open TCP/IP and SSL/TLS based connections.

## Introduction

Think of this library as an async version of
[`fsockopen()`](http://php.net/manual/en/function.fsockopen.php) or
[`stream_socket_client()`](http://php.net/manual/en/function.stream-socket-
client.php).

Before you can actually transmit and receive data to/from a remote server, you
have to establish a connection to the remote end. Establishing this connection
through the internet/network takes some time as it requires several steps in
order to complete:

1. Resolve remote target hostname via DNS (+cache)
2. Complete TCP handshake (2 roundtrips) with remote target IP:port
3. Optionally enable SSL/TLS on the new resulting connection

## Usage

In order to use this project, you'll need the following react boilerplate code
to initialize the main loop and select your DNS server if you have not already
set it up anyway.

```php
$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);
```

### Async TCP/IP connections

The `React\SocketClient\Connector` provides a single promise-based
`create($host, $ip)` method which resolves as soon as the connection
succeeds or fails.

```php
$connector = new React\SocketClient\Connector($loop, $dns);

$connector->create('www.google.com', 80)->then(function (React\Stream\Stream $stream) {
    $stream->write('...');
    $stream->close();
});
```

### Async SSL/TLS connections

The `SecureConnector` class decorates a given `Connector` instance by enabling
SSL/TLS encryption as soon as the raw TCP/IP connection succeeds. It provides
the same promise- based `create($host, $ip)` method which resolves with
a `Stream` instance that can be used just like any non-encrypted stream.

```php
$secureConnector = new React\SocketClient\SecureConnector($connector, $loop);

$secureConnector->create('www.google.com', 443)->then(function (React\Stream\Stream $stream) {
    $stream->write("GET / HTTP/1.0\r\nHost: www.google.com\r\n\r\n");
    ...
});
```
{
    "name": "react/socket-client",
    "description": "Async connector to open TCP/IP and SSL/TLS based connections.",
    "keywords": ["socket"],
    "license": "MIT",
    "require": {
        "php": ">=5.3.3",
        "react/dns": "0.3.*",
        "react/event-loop": "0.3.*",
        "react/promise": "~1.0"
    },
    "autoload": {
        "psr-0": { "React\\SocketClient": "" }
    },
    "target-dir": "React/SocketClient",
    "extra": {
        "branch-alias": {
            "dev-master": "0.3-dev"
        }
    }
}
<?php

namespace React\SocketClient;

use React\EventLoop\LoopInterface;
use React\Stream\Stream;
use React\Promise\When;

class SecureConnector implements ConnectorInterface
{
    private $connector;
    private $streamEncryption;

    public function __construct(ConnectorInterface $connector, LoopInterface $loop)
    {
        $this->connector = $connector;
        $this->streamEncryption = new StreamEncryption($loop);
    }

    public function create($host, $port)
    {
        $streamEncryption = $this->streamEncryption;
        return $this->connector->create($host, $port)->then(function (Stream $stream) use ($streamEncryption) {
            // (unencrypted) connection succeeded => try to enable encryption
            return $streamEncryption->enable($stream)->then(null, function ($error) use ($stream) {
                // establishing encryption failed => close invalid connection and return error
                $stream->close();
                throw $error;
            });
        });
    }
}
<?php

// autoload_classmap.php @generated by Composer

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
);
<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit60e4997d55dafbc5fcd090bf544b3006
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInit60e4997d55dafbc5fcd090bf544b3006', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader();
        spl_autoload_unregister(array('ComposerAutoloaderInit60e4997d55dafbc5fcd090bf544b3006', 'loadClassLoader'));

        $map = require __DIR__ . '/autoload_namespaces.php';
        foreach ($map as $namespace => $path) {
            $loader->set($namespace, $path);
        }

        $map = require __DIR__ . '/autoload_psr4.php';
        foreach ($map as $namespace => $path) {
            $loader->setPsr4($namespace, $path);
        }

        $classMap = require __DIR__ . '/autoload_classmap.php';
        if ($classMap) {
            $loader->addClassMap($classMap);
        }

        $loader->register(true);

        return $loader;
    }
}

function composerRequire60e4997d55dafbc5fcd090bf544b3006($file)
{
    require $file;
}
<?php

// autoload_namespaces.php @generated by Composer

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
    'React\\Stream' => array($vendorDir . '/react/stream'),
    'React\\SocketClient' => array($vendorDir . '/react/socket-client'),
    'React\\Socket' => array($vendorDir . '/react/socket'),
    'React\\Promise' => array($vendorDir . '/react/promise/src'),
    'React\\EventLoop' => array($vendorDir . '/react/event-loop'),
    'React\\Dns' => array($vendorDir . '/react/dns'),
    'React\\Cache' => array($vendorDir . '/react/cache'),
    'Evenement' => array($vendorDir . '/evenement/evenement/src'),
);
<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Autoload;

/**
 * ClassLoader implements a PSR-0 class loader
 *
 * See https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
 *
 *     $loader = new \Composer\Autoload\ClassLoader();
 *
 *     // register classes with namespaces
 *     $loader->add('Symfony\Component', __DIR__.'/component');
 *     $loader->add('Symfony',           __DIR__.'/framework');
 *
 *     // activate the autoloader
 *     $loader->register();
 *
 *     // to enable searching the include path (eg. for PEAR packages)
 *     $loader->setUseIncludePath(true);
 *
 * In this example, if you try to use a class in the Symfony\Component
 * namespace or one of its children (Symfony\Component\Console for instance),
 * the autoloader will first look for the class under the component/
 * directory, and it will then fallback to the framework/ directory if not
 * found before giving up.
 *
 * This class is loosely based on the Symfony UniversalClassLoader.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class ClassLoader
{
    // PSR-4
    private $prefixLengthsPsr4 = array();
    private $prefixDirsPsr4 = array();
    private $fallbackDirsPsr4 = array();

    // PSR-0
    private $prefixesPsr0 = array();
    private $fallbackDirsPsr0 = array();

    private $useIncludePath = false;
    private $classMap = array();

    public function getPrefixes()
    {
        return call_user_func_array('array_merge', $this->prefixesPsr0);
    }

    public function getPrefixesPsr4()
    {
        return $this->prefixDirsPsr4;
    }

    public function getFallbackDirs()
    {
        return $this->fallbackDirsPsr0;
    }

    public function getFallbackDirsPsr4()
    {
        return $this->fallbackDirsPsr4;
    }

    public function getClassMap()
    {
        return $this->classMap;
    }

    /**
     * @param array $classMap Class to filename map
     */
    public function addClassMap(array $classMap)
    {
        if ($this->classMap) {
            $this->classMap = array_merge($this->classMap, $classMap);
        } else {
            $this->classMap = $classMap;
        }
    }

    /**
     * Registers a set of PSR-0 directories for a given prefix, either
     * appending or prepending to the ones previously set for this prefix.
     *
     * @param string       $prefix  The prefix
     * @param array|string $paths   The PSR-0 root directories
     * @param bool         $prepend Whether to prepend the directories
     */
    public function add($prefix, $paths, $prepend = false)
    {
        if (!$prefix) {
            if ($prepend) {
                $this->fallbackDirsPsr0 = array_merge(
                    (array) $paths,
                    $this->fallbackDirsPsr0
                );
            } else {
                $this->fallbackDirsPsr0 = array_merge(
                    $this->fallbackDirsPsr0,
                    (array) $paths
                );
            }

            return;
        }

        $first = $prefix[0];
        if (!isset($this->prefixesPsr0[$first][$prefix])) {
            $this->prefixesPsr0[$first][$prefix] = (array) $paths;

            return;
        }
        if ($prepend) {
            $this->prefixesPsr0[$first][$prefix] = array_merge(
                (array) $paths,
                $this->prefixesPsr0[$first][$prefix]
            );
        } else {
            $this->prefixesPsr0[$first][$prefix] = array_merge(
                $this->prefixesPsr0[$first][$prefix],
                (array) $paths
            );
        }
    }

    /**
     * Registers a set of PSR-4 directories for a given namespace, either
     * appending or prepending to the ones previously set for this namespace.
     *
     * @param string       $prefix  The prefix/namespace, with trailing '\\'
     * @param array|string $paths   The PSR-0 base directories
     * @param bool         $prepend Whether to prepend the directories
     *
     * @throws \InvalidArgumentException
     */
    public function addPsr4($prefix, $paths, $prepend = false)
    {
        if (!$prefix) {
            // Register directories for the root namespace.
            if ($prepend) {
                $this->fallbackDirsPsr4 = array_merge(
                    (array) $paths,
                    $this->fallbackDirsPsr4
                );
            } else {
                $this->fallbackDirsPsr4 = array_merge(
                    $this->fallbackDirsPsr4,
                    (array) $paths
                );
            }
        } elseif (!isset($this->prefixDirsPsr4[$prefix])) {
            // Register directories for a new namespace.
            $length = strlen($prefix);
            if ('\\' !== $prefix[$length - 1]) {
                throw new \InvalidArgumentException("A non-empty PSR-4 prefix must end with a namespace separator.");
            }
            $this->prefixLengthsPsr4[$prefix[0]][$prefix] = $length;
            $this->prefixDirsPsr4[$prefix] = (array) $paths;
        } elseif ($prepend) {
            // Prepend directories for an already registered namespace.
            $this->prefixDirsPsr4[$prefix] = array_merge(
                (array) $paths,
                $this->prefixDirsPsr4[$prefix]
            );
        } else {
            // Append directories for an already registered namespace.
            $this->prefixDirsPsr4[$prefix] = array_merge(
                $this->prefixDirsPsr4[$prefix],
                (array) $paths
            );
        }
    }

    /**
     * Registers a set of PSR-0 directories for a given prefix,
     * replacing any others previously set for this prefix.
     *
     * @param string       $prefix The prefix
     * @param array|string $paths  The PSR-0 base directories
     */
    public function set($prefix, $paths)
    {
        if (!$prefix) {
            $this->fallbackDirsPsr0 = (array) $paths;
        } else {
            $this->prefixesPsr0[$prefix[0]][$prefix] = (array) $paths;
        }
    }

    /**
     * Registers a set of PSR-4 directories for a given namespace,
     * replacing any others previously set for this namespace.
     *
     * @param string       $prefix The prefix/namespace, with trailing '\\'
     * @param array|string $paths  The PSR-4 base directories
     *
     * @throws \InvalidArgumentException
     */
    public function setPsr4($prefix, $paths)
    {
        if (!$prefix) {
            $this->fallbackDirsPsr4 = (array) $paths;
        } else {
            $length = strlen($prefix);
            if ('\\' !== $prefix[$length - 1]) {
                throw new \InvalidArgumentException("A non-empty PSR-4 prefix must end with a namespace separator.");
            }
            $this->prefixLengthsPsr4[$prefix[0]][$prefix] = $length;
            $this->prefixDirsPsr4[$prefix] = (array) $paths;
        }
    }

    /**
     * Turns on searching the include path for class files.
     *
     * @param bool $useIncludePath
     */
    public function setUseIncludePath($useIncludePath)
    {
        $this->useIncludePath = $useIncludePath;
    }

    /**
     * Can be used to check if the autoloader uses the include path to check
     * for classes.
     *
     * @return bool
     */
    public function getUseIncludePath()
    {
        return $this->useIncludePath;
    }

    /**
     * Registers this instance as an autoloader.
     *
     * @param bool $prepend Whether to prepend the autoloader or not
     */
    public function register($prepend = false)
    {
        spl_autoload_register(array($this, 'loadClass'), true, $prepend);
    }

    /**
     * Unregisters this instance as an autoloader.
     */
    public function unregister()
    {
        spl_autoload_unregister(array($this, 'loadClass'));
    }

    /**
     * Loads the given class or interface.
     *
     * @param  string    $class The name of the class
     * @return bool|null True if loaded, null otherwise
     */
    public function loadClass($class)
    {
        if ($file = $this->findFile($class)) {
            includeFile($file);

            return true;
        }
    }

    /**
     * Finds the path to the file where the class is defined.
     *
     * @param string $class The name of the class
     *
     * @return string|false The path if found, false otherwise
     */
    public function findFile($class)
    {
        // work around for PHP 5.3.0 - 5.3.2 https://bugs.php.net/50731
        if ('\\' == $class[0]) {
            $class = substr($class, 1);
        }

        // class map lookup
        if (isset($this->classMap[$class])) {
            return $this->classMap[$class];
        }

        $file = $this->findFileWithExtension($class, '.php');

        // Search for Hack files if we are running on HHVM
        if ($file === null && defined('HHVM_VERSION')) {
            $file = $this->findFileWithExtension($class, '.hh');
        }

        if ($file === null) {
            // Remember that this class does not exist.
            return $this->classMap[$class] = false;
        }

        return $file;
    }

    private function findFileWithExtension($class, $ext)
    {
        // PSR-4 lookup
        $logicalPathPsr4 = strtr($class, '\\', DIRECTORY_SEPARATOR) . $ext;

        $first = $class[0];
        if (isset($this->prefixLengthsPsr4[$first])) {
            foreach ($this->prefixLengthsPsr4[$first] as $prefix => $length) {
                if (0 === strpos($class, $prefix)) {
                    foreach ($this->prefixDirsPsr4[$prefix] as $dir) {
                        if (file_exists($file = $dir . DIRECTORY_SEPARATOR . substr($logicalPathPsr4, $length))) {
                            return $file;
                        }
                    }
                }
            }
        }

        // PSR-4 fallback dirs
        foreach ($this->fallbackDirsPsr4 as $dir) {
            if (file_exists($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr4)) {
                return $file;
            }
        }

        // PSR-0 lookup
        if (false !== $pos = strrpos($class, '\\')) {
            // namespaced class name
            $logicalPathPsr0 = substr($logicalPathPsr4, 0, $pos + 1)
                . strtr(substr($logicalPathPsr4, $pos + 1), '_', DIRECTORY_SEPARATOR);
        } else {
            // PEAR-like class name
            $logicalPathPsr0 = strtr($class, '_', DIRECTORY_SEPARATOR) . $ext;
        }

        if (isset($this->prefixesPsr0[$first])) {
            foreach ($this->prefixesPsr0[$first] as $prefix => $dirs) {
                if (0 === strpos($class, $prefix)) {
                    foreach ($dirs as $dir) {
                        if (file_exists($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr0)) {
                            return $file;
                        }
                    }
                }
            }
        }

        // PSR-0 fallback dirs
        foreach ($this->fallbackDirsPsr0 as $dir) {
            if (file_exists($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr0)) {
                return $file;
            }
        }

        // PSR-0 include paths.
        if ($this->useIncludePath && $file = stream_resolve_include_path($logicalPathPsr0)) {
            return $file;
        }
    }
}

/**
 * Scope isolated include.
 *
 * Prevents access to $this/self from included files.
 */
function includeFile($file)
{
    include $file;
}
[
    {
        "name": "react/promise",
        "version": "v1.0.4",
        "version_normalized": "1.0.4.0",
        "source": {
            "type": "git",
            "url": "https://github.com/reactphp/promise.git",
            "reference": "v1.0.4"
        },
        "dist": {
            "type": "zip",
            "url": "https://api.github.com/repos/reactphp/promise/zipball/v1.0.4",
            "reference": "v1.0.4",
            "shasum": ""
        },
        "require": {
            "php": ">=5.3.3"
        },
        "time": "2013-04-03 14:05:55",
        "type": "library",
        "extra": {
            "branch-alias": {
                "dev-master": "1.0-dev"
            }
        },
        "installation-source": "source",
        "autoload": {
            "psr-0": {
                "React\\Promise": "src/"
            }
        },
        "notification-url": "https://packagist.org/downloads/",
        "license": [
            "MIT"
        ],
        "authors": [
            {
                "name": "Jan Sorgalla",
                "email": "jsorgalla@googlemail.com",
                "homepage": "http://sorgalla.com",
                "role": "maintainer"
            }
        ],
        "description": "A lightweight implementation of CommonJS Promises/A for PHP"
    },
    {
        "name": "evenement/evenement",
        "version": "v1.0.0",
        "version_normalized": "1.0.0.0",
        "source": {
            "type": "git",
            "url": "https://github.com/igorw/evenement",
            "reference": "v1.0.0"
        },
        "dist": {
            "type": "zip",
            "url": "https://github.com/igorw/evenement/zipball/v1.0.0",
            "reference": "v1.0.0",
            "shasum": ""
        },
        "require": {
            "php": ">=5.3.0"
        },
        "time": "2012-05-30 08:01:08",
        "type": "library",
        "installation-source": "source",
        "autoload": {
            "psr-0": {
                "Evenement": "src"
            }
        },
        "notification-url": "https://packagist.org/downloads/",
        "license": [
            "MIT"
        ],
        "authors": [
            {
                "name": "Igor Wiedler",
                "email": "igor@wiedler.ch",
                "homepage": "http://wiedler.ch/igor/"
            }
        ],
        "description": "Événement is a very simple event dispatching library for PHP 5.3",
        "keywords": [
            "event-dispatcher"
        ]
    },
    {
        "name": "react/cache",
        "version": "v0.3.2",
        "version_normalized": "0.3.2.0",
        "target-dir": "React/Cache",
        "source": {
            "type": "git",
            "url": "https://github.com/reactphp/cache.git",
            "reference": "v0.3.2"
        },
        "dist": {
            "type": "zip",
            "url": "https://api.github.com/repos/reactphp/cache/zipball/v0.3.2",
            "reference": "v0.3.2",
            "shasum": ""
        },
        "require": {
            "php": ">=5.3.2",
            "react/promise": ">=1.0,<2.0"
        },
        "time": "2013-04-24 08:33:43",
        "type": "library",
        "extra": {
            "branch-alias": {
                "dev-master": "0.3-dev"
            }
        },
        "installation-source": "dist",
        "autoload": {
            "psr-0": {
                "React\\Cache": ""
            }
        },
        "notification-url": "https://packagist.org/downloads/",
        "license": [
            "MIT"
        ],
        "description": "Async caching.",
        "keywords": [
            "cache"
        ]
    },
    {
        "name": "react/stream",
        "version": "v0.3.0",
        "version_normalized": "0.3.0.0",
        "target-dir": "React/Stream",
        "source": {
            "type": "git",
            "url": "https://github.com/reactphp/stream.git",
            "reference": "20cc0458ad93e8f1f00ef15408e759436ce36d68"
        },
        "dist": {
            "type": "zip",
            "url": "https://api.github.com/repos/reactphp/stream/zipball/20cc0458ad93e8f1f00ef15408e759436ce36d68",
            "reference": "20cc0458ad93e8f1f00ef15408e759436ce36d68",
            "shasum": ""
        },
        "require": {
            "evenement/evenement": "1.0.*",
            "php": ">=5.3.3"
        },
        "suggest": {
            "react/event-loop": "0.3.*",
            "react/promise": "~1.0"
        },
        "time": "2013-04-14 02:10:39",
        "type": "library",
        "extra": {
            "branch-alias": {
                "dev-master": "0.3-dev"
            }
        },
        "installation-source": "source",
        "autoload": {
            "psr-0": {
                "React\\Stream": ""
            }
        },
        "notification-url": "https://packagist.org/downloads/",
        "license": [
            "MIT"
        ],
        "description": "Basic readable and writable stream interfaces that support piping.",
        "keywords": [
            "pipe",
            "stream"
        ]
    },
    {
        "name": "react/event-loop",
        "version": "v0.3.0",
        "version_normalized": "0.3.0.0",
        "target-dir": "React/EventLoop",
        "source": {
            "type": "git",
            "url": "https://github.com/reactphp/event-loop.git",
            "reference": "798a43b2e9bd3cedaf7c5de6a39e6d03e4db8e32"
        },
        "dist": {
            "type": "zip",
            "url": "https://api.github.com/repos/reactphp/event-loop/zipball/798a43b2e9bd3cedaf7c5de6a39e6d03e4db8e32",
            "reference": "798a43b2e9bd3cedaf7c5de6a39e6d03e4db8e32",
            "shasum": ""
        },
        "require": {
            "php": ">=5.3.3"
        },
        "suggest": {
            "ext-libev": "*",
            "ext-libevent": ">=0.0.5"
        },
        "time": "2013-01-14 23:11:47",
        "type": "library",
        "extra": {
            "branch-alias": {
                "dev-master": "0.3-dev"
            }
        },
        "installation-source": "source",
        "autoload": {
            "psr-0": {
                "React\\EventLoop": ""
            }
        },
        "notification-url": "https://packagist.org/downloads/",
        "license": [
            "MIT"
        ],
        "description": "Event loop abstraction layer that libraries can use for evented I/O.",
        "keywords": [
            "event-loop"
        ]
    },
    {
        "name": "react/socket",
        "version": "v0.3.0",
        "version_normalized": "0.3.0.0",
        "target-dir": "React/Socket",
        "source": {
            "type": "git",
            "url": "https://github.com/reactphp/socket.git",
            "reference": "e549b1e39daefebc2f2290c6afdfc6ba5d12e51f"
        },
        "dist": {
            "type": "zip",
            "url": "https://api.github.com/repos/reactphp/socket/zipball/e549b1e39daefebc2f2290c6afdfc6ba5d12e51f",
            "reference": "e549b1e39daefebc2f2290c6afdfc6ba5d12e51f",
            "shasum": ""
        },
        "require": {
            "evenement/evenement": "1.0.*",
            "php": ">=5.3.3",
            "react/event-loop": "0.3.*",
            "react/stream": "0.3.*"
        },
        "time": "2013-01-21 04:20:49",
        "type": "library",
        "extra": {
            "branch-alias": {
                "dev-master": "0.3-dev"
            }
        },
        "installation-source": "source",
        "autoload": {
            "psr-0": {
                "React\\Socket": ""
            }
        },
        "notification-url": "https://packagist.org/downloads/",
        "license": [
            "MIT"
        ],
        "description": "Library for building an evented socket server.",
        "keywords": [
            "Socket"
        ]
    },
    {
        "name": "react/dns",
        "version": "v0.3.0",
        "version_normalized": "0.3.0.0",
        "target-dir": "React/Dns",
        "source": {
            "type": "git",
            "url": "https://github.com/reactphp/dns.git",
            "reference": "3011d27e9e39f83e702b0e7e469192d36fb21205"
        },
        "dist": {
            "type": "zip",
            "url": "https://api.github.com/repos/reactphp/dns/zipball/3011d27e9e39f83e702b0e7e469192d36fb21205",
            "reference": "3011d27e9e39f83e702b0e7e469192d36fb21205",
            "shasum": ""
        },
        "require": {
            "php": ">=5.3.2",
            "react/cache": "0.3.*",
            "react/promise": "~1.0",
            "react/socket": "0.3.*"
        },
        "time": "2013-01-20 19:13:14",
        "type": "library",
        "extra": {
            "branch-alias": {
                "dev-master": "0.3-dev"
            }
        },
        "installation-source": "source",
        "autoload": {
            "psr-0": {
                "React\\Dns": ""
            }
        },
        "notification-url": "https://packagist.org/downloads/",
        "license": [
            "MIT"
        ],
        "description": "Async DNS resolver.",
        "keywords": [
            "dns",
            "dns-resolver"
        ]
    },
    {
        "name": "clue/connection-manager-extra",
        "version": "v0.3.1",
        "version_normalized": "0.3.1.0",
        "source": {
            "type": "git",
            "url": "https://github.com/clue/php-connection-manager-extra.git",
            "reference": "f8fc2ec784db7974e649c158c826c014296bcf01"
        },
        "dist": {
            "type": "zip",
            "url": "https://api.github.com/repos/clue/php-connection-manager-extra/zipball/f8fc2ec784db7974e649c158c826c014296bcf01",
            "reference": "f8fc2ec784db7974e649c158c826c014296bcf01",
            "shasum": ""
        },
        "require": {
            "php": ">=5.3",
            "react/event-loop": "0.3.*|0.4.*",
            "react/promise": "~1.0|~2.0",
            "react/socket-client": "0.3.*|0.4.*"
        },
        "time": "2014-09-27 23:03:41",
        "type": "library",
        "installation-source": "source",
        "autoload": {
            "psr-4": {
                "ConnectionManager\\Extra\\": "src"
            }
        },
        "notification-url": "https://packagist.org/downloads/",
        "license": [
            "MIT"
        ],
        "authors": [
            {
                "name": "Christian Lück",
                "email": "christian@lueck.tv"
            }
        ],
        "description": "Extra decorators for creating async TCP/IP connections built upon react/socket-client",
        "homepage": "https://github.com/clue/php-connection-manager-extra",
        "keywords": [
            "Connection",
            "SocketClient",
            "acl",
            "delay",
            "firewall",
            "network",
            "random",
            "reject",
            "repeat",
            "retry",
            "timeout"
        ]
    },
    {
        "name": "clue/socks-react",
        "version": "v0.2.0",
        "version_normalized": "0.2.0.0",
        "source": {
            "type": "git",
            "url": "https://github.com/clue/php-socks-react.git",
            "reference": "14a98b639a9f9ff45e7dda4ed4ee995ff6c46dcd"
        },
        "dist": {
            "type": "zip",
            "url": "https://api.github.com/repos/clue/php-socks-react/zipball/14a98b639a9f9ff45e7dda4ed4ee995ff6c46dcd",
            "reference": "14a98b639a9f9ff45e7dda4ed4ee995ff6c46dcd",
            "shasum": ""
        },
        "require": {
            "evenement/evenement": "~1.0",
            "php": ">=5.3",
            "react/dns": "0.3.*",
            "react/event-loop": "0.3.*",
            "react/promise": "~1.0",
            "react/socket": "0.3.*",
            "react/socket-client": "0.3.*",
            "react/stream": "0.3.*"
        },
        "time": "2014-09-27 15:32:30",
        "type": "library",
        "installation-source": "dist",
        "autoload": {
            "psr-4": {
                "Clue\\React\\Socks\\": "src"
            }
        },
        "notification-url": "https://packagist.org/downloads/",
        "license": [
            "MIT"
        ],
        "authors": [
            {
                "name": "Christian Lück",
                "email": "christian@lueck.tv"
            }
        ],
        "description": "Async SOCKS proxy client and server (SOCKS4, SOCKS4a and SOCKS5)",
        "homepage": "https://github.com/clue/php-socks-react",
        "keywords": [
            "async",
            "react",
            "socks client",
            "socks protocol",
            "socks server",
            "tcp tunnel"
        ]
    },
    {
        "name": "react/socket-client",
        "version": "v0.3.1",
        "version_normalized": "0.3.1.0",
        "target-dir": "React/SocketClient",
        "source": {
            "type": "git",
            "url": "https://github.com/reactphp/socket-client.git",
            "reference": "87935a0223362c36cd30cf215cbec33377d31ca4"
        },
        "dist": {
            "type": "zip",
            "url": "https://api.github.com/repos/reactphp/socket-client/zipball/87935a0223362c36cd30cf215cbec33377d31ca4",
            "reference": "87935a0223362c36cd30cf215cbec33377d31ca4",
            "shasum": ""
        },
        "require": {
            "php": ">=5.3.3",
            "react/dns": "0.3.*",
            "react/event-loop": "0.3.*",
            "react/promise": "~1.0"
        },
        "time": "2013-04-20 14:55:59",
        "type": "library",
        "extra": {
            "branch-alias": {
                "dev-master": "0.3-dev"
            }
        },
        "installation-source": "dist",
        "autoload": {
            "psr-0": {
                "React\\SocketClient": ""
            }
        },
        "notification-url": "https://packagist.org/downloads/",
        "license": [
            "MIT"
        ],
        "description": "Async connector to open TCP/IP and SSL/TLS based connections.",
        "keywords": [
            "Socket"
        ]
    }
]
<?php

// autoload_psr4.php @generated by Composer

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
    'ConnectionManager\\Extra\\' => array($vendorDir . '/clue/connection-manager-extra/src'),
    'Clue\\React\\Socks\\' => array($vendorDir . '/clue/socks-react/src'),
    'Clue\\Psocksd\\' => array($baseDir . '/src'),
);
The MIT License (MIT)

Copyright (c) 2011 Christian Lück

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
<?php

namespace Clue\React\Socks;

use Evenement\EventEmitter;
use React\Socket\ServerInterface;
use React\Promise\When;
use React\Promise\PromiseInterface;
use React\Stream\Stream;
use React\Dns\Resolver\Factory as DnsFactory;
use React\SocketClient\Connector as TcpConnector;
use React\SocketClient\ConnectorInterface;
use React\Socket\Connection;
use React\EventLoop\LoopInterface;
use \UnexpectedValueException;
use \InvalidArgumentException;
use \Exception;

class Server extends EventEmitter
{
    protected $loop;

    private $connector;

    private $auth = null;

    private $protocolVersion = null;

    public function __construct(LoopInterface $loop, ServerInterface $serverInterface, ConnectorInterface $connector = null)
    {
        if ($connector === null) {
            // default to using Google's public DNS server
            $dnsResolverFactory = new DnsFactory();
            $resolver = $dnsResolverFactory->createCached('8.8.8.8', $loop);
            $connector = new TcpConnector($loop, $resolver);
        }

        $this->loop = $loop;
        $this->connector = $connector;

        $that = $this;
        $serverInterface->on('connection', function ($connection) use ($that) {
            $that->emit('connection', array($connection));
            $that->onConnection($connection);
        });
    }

    public function setProtocolVersion($version)
    {
        if ($version !== null) {
            $version = (string)$version;
            if (!in_array($version, array('4', '4a', '5'), true)) {
                throw new InvalidArgumentException('Invalid protocol version given');
            }
            if ($version !== '5' && $this->auth !== null){
                throw new UnexpectedValueException('Unable to change protocol version to anything but SOCKS5 while authentication is used. Consider removing authentication info or sticking to SOCKS5');
            }
        }
        $this->protocolVersion = $version;
    }

    public function setAuth($auth)
    {
        if (!is_callable($auth)) {
            throw new InvalidArgumentException('Given authenticator is not a valid callable');
        }
        if ($this->protocolVersion !== null && $this->protocolVersion !== '5') {
            throw new UnexpectedValueException('Authentication requires SOCKS5. Consider using protocol version 5 or waive authentication');
        }
        // wrap authentication callback in order to cast its return value to a promise
        $this->auth = function($username, $password) use ($auth) {
            $ret = call_user_func($auth, $username, $password);
            if ($ret instanceof PromiseInterface) {
                return $ret;
            }
            return $ret ? When::resolve() : When::reject();
        };
    }

    public function setAuthArray(array $login)
    {
        $this->setAuth(function ($username, $password) use ($login) {
            return (isset($login[$username]) && (string)$login[$username] === $password);
        });
    }

    public function unsetAuth()
    {
        $this->auth = null;
    }

    public function onConnection(Connection $connection)
    {
        $that = $this;
        $this->handleSocks($connection)->then(function($remote) use ($connection){
            $connection->emit('ready',array($remote));
        }, function ($error) use ($connection, $that) {
            if (!($error instanceof \Exception)) {
                $error = new \Exception($error);
            }
            $connection->emit('error', array($error));
            $that->endConnection($connection);
        });
    }

    /**
     * gracefully shutdown connection by flushing all remaining data and closing stream
     *
     * @param Stream $stream
     */
    public function endConnection(Stream $stream)
    {
        $tid = true;
        $loop = $this->loop;

        // cancel below timer in case connection is closed in time
        $stream->once('close', function () use (&$tid, $loop) {
            // close event called before the timer was set up, so everything is okay
            if ($tid === true) {
                // make sure to not start a useless timer
                $tid = false;
            } else {
                $loop->cancelTimer($tid);
            }
        });

        // shut down connection by pausing input data, flushing outgoing buffer and then exit
        $stream->pause();
        $stream->end();

        // check if connection is not already closed
        if ($tid === true) {
            // fall back to forcefully close connection in 3 seconds if buffer can not be flushed
            $tid = $loop->addTimer(3.0, array($stream,'close'));
        }
    }

    private function handleSocks(Stream $stream)
    {
        $reader = new StreamReader();
        $stream->on('data', array($reader, 'write'));

        $that = $this;
        $that = $this;

        $auth = $this->auth;
        $protocolVersion = $this->protocolVersion;

        // authentication requires SOCKS5
        if ($auth !== null) {
        	$protocolVersion = '5';
        }

        return $reader->readByte()->then(function ($version) use ($stream, $that, $protocolVersion, $auth, $reader){
            if ($version === 0x04) {
                if ($protocolVersion === '5') {
                    throw new UnexpectedValueException('SOCKS4 not allowed due to configuration');
                }
                return $that->handleSocks4($stream, $protocolVersion, $reader);
            } else if ($version === 0x05) {
                if ($protocolVersion !== null && $protocolVersion !== '5') {
                    throw new UnexpectedValueException('SOCKS5 not allowed due to configuration');
                }
                return $that->handleSocks5($stream, $auth, $reader);
            }
            throw new UnexpectedValueException('Unexpected/unknown version number');
        });
    }

    public function handleSocks4(Stream $stream, $protocolVersion, StreamReader $reader)
    {
        // suppliying hostnames is only allowed for SOCKS4a (or automatically detected version)
        $supportsHostname = ($protocolVersion === null || $protocolVersion === '4a');

        $that = $this;
        return $reader->readByteAssert(0x01)->then(function () use ($reader) {
            return $reader->readBinary(array(
                'port'   => 'n',
                'ipLong' => 'N',
                'null'   => 'C'
            ));
        })->then(function ($data) use ($reader, $supportsHostname) {
            if ($data['null'] !== 0x00) {
                throw new Exception('Not a null byte');
            }
            if ($data['ipLong'] === 0) {
                throw new Exception('Invalid IP');
            }
            if ($data['port'] === 0) {
                throw new Exception('Invalid port');
            }
            if ($data['ipLong'] < 256 && $supportsHostname) {
                // invalid IP => probably a SOCKS4a request which appends the hostname
                return $reader->readStringNull()->then(function ($string) use ($data){
                    return array($string, $data['port']);
                });
            } else {
                $ip = long2ip($data['ipLong']);
                return array($ip, $data['port']);
            }
        })->then(function ($target) use ($stream, $that) {
            return $that->connectTarget($stream, $target)->then(function (Stream $remote) use ($stream){
                $stream->write(pack('C8', 0x00, 0x5a, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00));

                return $remote;
            }, function($error) use ($stream){
                $stream->end(pack('C8', 0x00, 0x5b, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00));

                throw $error;
            });
        }, function($error) {
            throw new UnexpectedValueException('SOCKS4 protocol error',0,$error);
        });
    }

    public function handleSocks5(Stream $stream, $auth=null, StreamReader $reader)
    {
        $that = $this;
        return $reader->readByte()->then(function ($num) use ($reader) {
            // $num different authentication mechanisms offered
            return $reader->readLength($num);
        })->then(function ($methods) use ($reader, $stream, $auth) {
            if ($auth === null && strpos($methods,"\x00") !== false) {
                // accept "no authentication"
                $stream->write(pack('C2', 0x05, 0x00));
                return 0x00;
            } else if ($auth !== null && strpos($methods,"\x02") !== false) {
                // username/password authentication (RFC 1929) sub negotiation
                $stream->write(pack('C2', 0x05, 0x02));
                return $reader->readByteAssert(0x01)->then(function () use ($reader) {
                    return $reader->readByte();
                })->then(function ($length) use ($reader) {
                    return $reader->readLength($length);
                })->then(function ($username) use ($reader, $auth, $stream) {
                    return $reader->readByte()->then(function ($length) use ($reader) {
                        return $reader->readLength($length);
                    })->then(function ($password) use ($username, $auth, $stream) {
                        // username and password known => authenticate
                        // echo 'auth: ' . $username.' : ' . $password . PHP_EOL;
                        return $auth($username, $password)->then(function () use ($stream, $username) {
                            // accept
                            $stream->emit('auth', array($username));
                            $stream->write(pack('C2', 0x01, 0x00));
                        }, function() use ($stream) {
                            // reject => send any code but 0x00
                            $stream->end(pack('C2', 0x01, 0xFF));
                            throw new UnexpectedValueException('Unable to authenticate');
                        });
                    });
                });
            } else {
                // reject all offered authentication methods
                $stream->end(pack('C2', 0x05, 0xFF));
                throw new UnexpectedValueException('No acceptable authentication mechanism found');
            }
        })->then(function ($method) use ($reader, $stream) {
            return $reader->readBinary(array(
                'version' => 'C',
                'command' => 'C',
                'null'    => 'C',
                'type'    => 'C'
            ));
        })->then(function ($data) use ($reader) {
            if ($data['version'] !== 0x05) {
                throw new UnexpectedValueException('Invalid SOCKS version');
            }
            if ($data['command'] !== 0x01) {
                throw new UnexpectedValueException('Only CONNECT requests supported');
            }
//             if ($data['null'] !== 0x00) {
//                 throw new UnexpectedValueException('Reserved byte has to be NULL');
//             }
            if ($data['type'] === 0x03) {
                // target hostname string
                return $reader->readByte()->then(function ($len) use ($reader) {
                    return $reader->readLength($len);
                });
            } else if ($data['type'] === 0x01) {
                // target IPv4
                return $reader->readLength(4)->then(function ($addr) {
                    return inet_ntop($addr);
                });
            } else if ($data['type'] === 0x04) {
                // target IPv6
                return $reader->readLength(16)->then(function ($addr) {
                    return inet_ntop($addr);
                });
            } else {
                throw new UnexpectedValueException('Invalid target type');
            }
        })->then(function ($host) use ($reader) {
            return $reader->readBinary(array('port'=>'n'))->then(function ($data) use ($host) {
                return array($host, $data['port']);
            });
        })->then(function ($target) use ($that, $stream) {
            return $that->connectTarget($stream, $target);
        }, function($error) use ($stream) {
            throw new UnexpectedValueException('SOCKS5 protocol error',0,$error);
        })->then(function (Stream $remote) use ($stream) {
            $stream->write(pack('C4Nn', 0x05, 0x00, 0x00, 0x01, 0, 0));

            return $remote;
        }, function(Exception $error) use ($stream){
            $code = 0x01;
            $stream->end(pack('C4Nn', 0x05, $code, 0x00, 0x01, 0, 0));

            throw $error;
        });
    }

    public function connectTarget(Stream $stream, array $target)
    {
        $stream->emit('target', $target);
        $that = $this;
        return $this->connector->create($target[0], $target[1])->then(function (Stream $remote) use ($stream, $that) {
            if (!$stream->isWritable()) {
                $remote->close();
                throw new UnexpectedValueException('Remote connection successfully established after client connection closed');
            }

            $stream->pipe($remote, array('end'=>false));
            $remote->pipe($stream, array('end'=>false));

            // remote end closes connection => stop reading from local end, try to flush buffer to local and disconnect local
            $remote->on('end', function() use ($stream, $that) {
                $stream->emit('shutdown', array('remote', null));
                $that->endConnection($stream);
            });

            // local end closes connection => stop reading from remote end, try to flush buffer to remote and disconnect remote
            $stream->on('end', function() use ($remote, $that) {
                $that->endConnection($remote);
            });

            // set bigger buffer size of 100k to improve performance
            $stream->bufferSize = $remote->bufferSize = 100 * 1024 * 1024;

            return $remote;
        }, function(Exception $error) {
            throw new UnexpectedValueException('Unable to connect to remote target', 0, $error);
        });
    }
}
<?php

namespace Clue\React\Socks;

use React\Promise\Deferred;
use React\Stream\Stream;
use \InvalidArgumentException;
use \UnexpectedValueException;

class StreamReader
{
    const RET_DONE = true;
    const RET_INCOMPLETE = null;

    private $buffer = '';
    private $queue = array();

    public function write($data)
    {
        $this->buffer .= $data;

        do {
            $current = reset($this->queue);

            if ($current === false) {
                break;
            }

            /* @var $current Closure */

            $ret = $current($this->buffer);

            if ($ret === self::RET_INCOMPLETE) {
                // current is incomplete, so wait for further data to arrive
                break;
            } else {
                // current is done, remove from list and continue with next
                array_shift($this->queue);
            }
        } while (true);
    }

    public function readBinary($structure)
    {
        $length = 0;
        $unpack = '';
        foreach ($structure as $name=>$format) {
            if ($length !== 0) {
                $unpack .= '/';
            }
            $unpack .= $format . $name;

            if ($format === 'C') {
                ++$length;
            } else if ($format === 'n') {
                $length += 2;
            } else if ($format === 'N') {
                $length += 4;
            } else {
                throw new InvalidArgumentException('Invalid format given');
            }
        }

        return $this->readLength($length)->then(function ($response) use ($unpack) {
            return unpack($unpack, $response);
        });
    }

    public function readLength($bytes)
    {
        $deferred = new Deferred();

        $this->readBufferCallback(function (&$buffer) use ($bytes, $deferred) {
            if (strlen($buffer) >= $bytes) {
                $deferred->resolve(substr($buffer, 0, $bytes));
                $buffer = (string)substr($buffer, $bytes);

                return StreamReader::RET_DONE;
            }
        });

        return $deferred->promise();
    }

    public function readByte()
    {
        return $this->readBinary(array(
            'byte' => 'C'
        ))->then(function ($data) {
            return $data['byte'];
        });
    }

    public function readByteAssert($expect)
    {
        return $this->readByte()->then(function ($byte) use ($expect) {
            if ($byte !== $expect) {
                throw new UnexpectedValueException('Unexpected byte encountered');
            }
            return $byte;
        });
    }

    public function readStringNull()
    {
        $deferred = new Deferred();
        $string = '';

        $that = $this;
        $readOne = function () use (&$readOne, $that, $deferred, &$string) {
            $that->readByte()->then(function ($byte) use ($deferred, &$string, $readOne) {
                if ($byte === 0x00) {
                    $deferred->resolve($string);
                } else {
                    $string .= chr($byte);
                    $readOne();
                }
            });
        };
        $readOne();

        return $deferred->promise();
    }

    public function readBufferCallback(/* callable */ $callable)
    {
        if (!is_callable($callable)) {
            throw new InvalidArgumentException('Given function must be callable');
        }

        if ($this->queue) {
            $this->queue []= $callable;
        } else {
            $this->queue = array($callable);

            if ($this->buffer !== '') {
                // this is the first element in the queue and the buffer is filled => trigger write procedure
                $this->write('');
            }
        }
    }

    public function getBuffer()
    {
        return $this->buffer;
    }
}
<?php

namespace Clue\React\Socks;

use React\SocketClient\ConnectorInterface;
use Clue\React\Socks\Client;

class Connector implements ConnectorInterface
{
    private $client;

    public function __construct(Client $socksClient)
    {
        $this->client = $socksClient;
    }

    public function create($host, $port)
    {
        return $this->client->getConnection($host, $port);
    }
}
<?php

namespace Clue\React\Socks;

use React\Promise\When;
use React\Promise\Deferred;
use React\Dns\Resolver\Factory as DnsFactory;
use React\Dns\Resolver\Resolver;
use React\SocketClient\Connector as TcpConnector;
use React\Stream\Stream;
use React\EventLoop\LoopInterface;
use React\SocketClient\ConnectorInterface;
use React\SocketClient\SecureConnector;
use Clue\React\Socks\Connector;
use \Exception;
use \InvalidArgumentException;
use \UnexpectedValueException;

class Client
{
    /**
     *
     * @var ConnectorInterface
     */
    private $connector;

    /**
     *
     * @var Resolver
     */
    private $resolver;

    private $socksHost;

    private $socksPort;

    private $timeout;

    /**
     * @var LoopInterface
     */
    protected $loop;

    private $resolveLocal = true;

    private $protocolVersion = null;

    protected $auth = null;

    public function __construct(LoopInterface $loop, $socksHost, $socksPort, ConnectorInterface $connector = null, Resolver $resolver = null)
    {
        if ($resolver === null) {
            // default to using Google's public DNS server
            $dnsResolverFactory = new DnsFactory();
            $resolver = $dnsResolverFactory->createCached('8.8.8.8', $loop);
        }
        if ($connector === null) {
            $connector = new TcpConnector($loop, $resolver);
        }

        $this->loop = $loop;
        $this->socksHost = $socksHost;
        $this->socksPort = $socksPort;
        $this->connector = $connector;
        $this->resolver = $resolver;

        $this->timeout = ini_get("default_socket_timeout");
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function setResolveLocal($resolveLocal)
    {
        if ($this->protocolVersion === '4' && !$resolveLocal) {
            throw new UnexpectedValueException('SOCKS4 requires resolving locally. Consider using another protocol version or resolving locally');
        }
        $this->resolveLocal = $resolveLocal;
    }

    public function setProtocolVersion($version)
    {
        if ($version !== null) {
            $version = (string)$version;
            if (!in_array($version, array('4', '4a', '5'), true)) {
                throw new InvalidArgumentException('Invalid protocol version given');
            }
            if ($version !== '5' && $this->auth){
                throw new UnexpectedValueException('Unable to change protocol version to anything but SOCKS5 while authentication is used. Consider removing authentication info or sticking to SOCKS5');
            }
            if ($version === '4' && !$this->resolveLocal) {
                throw new UnexpectedValueException('Unable to change to SOCKS4 while resolving locally is turned off. Consider using another protocol version or resolving locally');
            }
        }
        $this->protocolVersion = $version;
    }

    /**
     * set login data for username/password authentication method (RFC1929)
     *
     * @param string $username
     * @param string $password
     * @link http://tools.ietf.org/html/rfc1929
     */
    public function setAuth($username, $password)
    {
        if (strlen($username) > 255 || strlen($password) > 255) {
            throw new InvalidArgumentException('Both username and password MUST NOT exceed a length of 255 bytes each');
        }
        if ($this->protocolVersion !== null && $this->protocolVersion !== '5') {
            throw new UnexpectedValueException('Authentication requires SOCKS5. Consider using protocol version 5 or waive authentication');
        }
        $this->auth = pack('C2', 0x01, strlen($username)) . $username . pack('C', strlen($password)) . $password;
    }

    public function unsetAuth()
    {
        $this->auth = null;
    }

    public function createSecureConnector()
    {
        return new SecureConnector($this->createConnector(), $this->loop);
    }

    public function createConnector()
    {
        return new Connector($this);
    }

    public function getConnection($host, $port)
    {
        if (strlen($host) > 255 || $port > 65535 || $port < 0) {
            return When::reject(new InvalidArgumentException('Invalid target specified'));
        }
        $deferred = new Deferred();

        $timestampTimeout = microtime(true) + $this->timeout;
        $timerTimeout = $this->loop->addTimer($this->timeout, function () use ($deferred) {
            $deferred->reject(new Exception('Timeout while connecting to socks server'));
            // TODO: stop initiating connection and DNS query
        });

        // create local references as these settings may change later due to its async nature
        $auth = $this->auth;
        $protocolVersion = $this->protocolVersion;

        // protocol version not explicitly set?
        if ($protocolVersion === null) {
            // authentication requires SOCKS5, otherwise use SOCKS4a
            $protocolVersion = ($auth === null) ? '4a' : '5';
        }

        $loop = $this->loop;
        $that = $this;
        When::all(
            array(
                $this->connector->create($this->socksHost, $this->socksPort)->then(
                    null,
                    function ($error) {
                        throw new Exception('Unable to connect to socks server', 0, $error);
                    }
                ),
                $this->resolve($host)->then(
                    null,
                    function ($error) {
                        throw new Exception('Unable to resolve remote hostname', 0, $error);
                    }
                )
            ),
            function ($fulfilled) use ($deferred, $port, $timestampTimeout, $that, $loop, $timerTimeout, $protocolVersion, $auth) {
                $loop->cancelTimer($timerTimeout);

                $timeout = max($timestampTimeout - microtime(true), 0.1);
                $deferred->resolve($that->handleConnectedSocks($fulfilled[0], $fulfilled[1], $port, $timeout, $protocolVersion, $auth));
            },
            function ($error) use ($deferred, $loop, $timerTimeout) {
                $loop->cancelTimer($timerTimeout);
                $deferred->reject(new Exception('Unable to connect to socks server', 0, $error));
            }
        );
        return $deferred->promise();
    }

    private function resolve($host)
    {
        // return if it's already an IP or we want to resolve remotely (socks 4 only supports resolving locally)
        if (false !== filter_var($host, FILTER_VALIDATE_IP) || ($this->protocolVersion !== '4' && !$this->resolveLocal)) {
            return When::resolve($host);
        }

        return $this->resolver->resolve($host);
    }

    public function handleConnectedSocks(Stream $stream, $host, $port, $timeout, $protocolVersion, $auth=null)
    {
        $deferred = new Deferred();
        $resolver = $deferred->resolver();

        $timerTimeout = $this->loop->addTimer($timeout, function () use ($resolver) {
            $resolver->reject(new Exception('Timeout while establishing socks session'));
        });

        $reader = new StreamReader($stream);
        $stream->on('data', array($reader, 'write'));

        if ($protocolVersion === '5' || $auth !== null) {
            $promise = $this->handleSocks5($stream, $host, $port, $auth, $reader);
        } else {
            $promise = $this->handleSocks4($stream, $host, $port, $reader);
        }
        $promise->then(function () use ($resolver, $stream) {
            $resolver->resolve($stream);
        }, function($error) use ($resolver) {
            $resolver->reject(new Exception('Unable to communicate...', 0, $error));
        });

        $loop = $this->loop;
        $deferred->then(
            function (Stream $stream) use ($timerTimeout, $loop, $reader) {
                $loop->cancelTimer($timerTimeout);
                $stream->removeAllListeners('end');

                $stream->removeListener('data', array($reader, 'write'));

                return $stream;
            },
            function ($error) use ($stream, $timerTimeout, $loop, $reader) {
                $loop->cancelTimer($timerTimeout);
                $stream->close();

                $stream->removeListener('data', array($reader, 'write'));

                return $error;
            }
        );

        $stream->on('end', function (Stream $stream) use ($resolver) {
            $resolver->reject(new Exception('Premature end while establishing socks session'));
        });

        return $deferred->promise();
    }

    protected function handleSocks4(Stream $stream, $host, $port, StreamReader $reader)
    {
        // do not resolve hostname. only try to convert to IP
        $ip = ip2long($host);

        // send IP or (0.0.0.1) if invalid
        $data = pack('C2nNC', 0x04, 0x01, $port, $ip === false ? 1 : $ip, 0x00);

        if ($ip === false) {
            // host is not a valid IP => send along hostname (SOCKS4a)
            $data .= $host . pack('C', 0x00);
        }

        $stream->write($data);

        return $reader->readBinary(array(
            'null'   => 'C',
            'status' => 'C',
            'port'   => 'n',
            'ip'     => 'N'
        ))->then(function ($data) {
            if ($data['null'] !== 0x00 || $data['status'] !== 0x5a) {
                throw new Exception('Invalid SOCKS response');
            }
        });
    }

    protected function handleSocks5(Stream $stream, $host, $port, $auth=null, StreamReader $reader)
    {
        // protocol version 5
        $data = pack('C', 0x05);
        if ($auth === null) {
            // one method, no authentication
            $data .= pack('C2', 0x01, 0x00);
        } else {
            // two methods, username/password and no authentication
            $data .= pack('C3', 0x02, 0x02, 0x00);
        }
        $stream->write($data);

        $that = $this;

        return $reader->readBinary(array(
            'version' => 'C',
            'method'  => 'C'
        ))->then(function ($data) use ($auth, $stream, $reader) {
            if ($data['version'] !== 0x05) {
                throw new Exception('Version/Protocol mismatch');
            }

            if ($data['method'] === 0x02 && $auth !== null) {
                // username/password authentication requested and provided
                $stream->write($auth);

                return $reader->readBinary(array(
                    'version' => 'C',
                    'status'  => 'C'
                ))->then(function ($data) {
                    if ($data['version'] !== 0x01 || $data['status'] !== 0x00) {
                        throw new Exception('Username/Password authentication failed');
                    }
                });
            } else if ($data['method'] !== 0x00) {
                // any other method than "no authentication"
                throw new Exception('Unacceptable authentication method requested');
            }
        })->then(function () use ($stream, $reader, $host, $port) {
            // do not resolve hostname. only try to convert to (binary/packed) IP
            $ip = @inet_pton($host);

            $data = pack('C3', 0x05, 0x01, 0x00);
            if ($ip === false) {
                // not an IP, send as hostname
                $data .= pack('C2', 0x03, strlen($host)) . $host;
            } else {
                // send as IPv4 / IPv6
                $data .= pack('C', (strpos($host, ':') === false) ? 0x01 : 0x04) . $ip;
            }
            $data .= pack('n', $port);

            $stream->write($data);

            return $reader->readBinary(array(
                'version' => 'C',
                'status'  => 'C',
                'null'    => 'C',
                'type'    => 'C'
            ));
        })->then(function ($data) use ($reader) {
            if ($data['version'] !== 0x05 || $data['status'] !== 0x00 || $data['null'] !== 0x00) {
                throw new Exception('Invalid SOCKS response');
            }
            if ($data['type'] === 0x01) {
                // IPv4 address => skip IP and port
                return $reader->readLength(6);
            } else if ($data['type'] === 0x03) {
                // domain name => read domain name length
                return $reader->readBinary(array(
                    'length' => 'C'
                ))->then(function ($data) use ($that) {
                    // skip domain name and port
                    return $that->readLength($data['length'] + 2);
                });
            } else if ($data['type'] === 0x04) {
                // IPv6 address => skip IP and port
                return $reader->readLength(18);
            } else {
                throw new Exception('Invalid SOCKS reponse: Invalid address type');
            }
        });
    }
}
# clue/socks-react - SOCKS client and server [![Build Status](https://travis-ci.org/clue/php-socks-react.svg?branch=master)](https://travis-ci.org/clue/php-socks-react)

Async SOCKS client library to connect to SOCKS4, SOCKS4a and SOCKS5 proxy servers,
as well as a SOCKS server implementation, capable of handling multiple concurrent
connections in a non-blocking fashion.

## Description

The SOCKS protocol family can be used to easily tunnel TCP connections independent
of the actual application level protocol, such as HTTP, SMTP, IMAP, Telnet, etc.

## Quickstart examples

Once [installed](#install), initialize a connection to a remote SOCKS proxy server:

```PHP
<?php
include_once __DIR__.'/vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

// create SOCKS client which communicates with SOCKS server 127.0.0.1:9050
$client = new Clue\React\Socks\Client($loop, '127.0.0.1', 9050);

// now work with your $client, see below

$loop->run();
```

### Tunnelled TCP connections

The `Client` uses a [Promise](https://github.com/reactphp/promise)-based interface which makes working with asynchronous functions a breeze.
Let's open up a TCP [Stream](https://github.com/reactphp/stream) connection and write some data:
```PHP
$tcp = $client->createConnector();

$tcp->create('www.google.com',80)->then(function (React\Stream\Stream $stream) {
    echo 'connected to www.google.com:80';
    $stream->write("GET / HTTP/1.0\r\n\r\n");
    // ...
});
```

### SSL/TLS encrypted

If you want to connect to arbitrary SSL/TLS servers, there sure too is an easy to use API available:
```PHP
$ssl = $client->createSecureConnector();

// now create an SSL encrypted connection (notice the $ssl instead of $tcp)
$ssl->create('www.google.com',443)->then(function (React\Stream\Stream $stream) {
    // proceed with just the plain text data
    // everything is encrypted/decrypted automatically
    echo 'connected to SSL encrypted www.google.com';
    $stream->write("GET / HTTP/1.0\r\n\r\n");
    // ...
});
```

### HTTP requests

HTTP operates on a higher layer than this low-level SOCKS implementation.
If you want to issue HTTP requests, you can add a dependency for
[clue/buzz-react](https://github.com/clue/php-buzz-react).
It can interact with this library by issuing all
[http requests through a SOCKS server](https://github.com/clue/php-buzz-react/#via-socks-server).
This works for both plain HTTP and SSL encrypted HTTPS requests.

## SOCKS Protocol versions & differences

While SOCKS4 already had (a somewhat limited) support for `SOCKS BIND` requests
and SOCKS5 added generic UDP support (`SOCKS UDPASSOCIATE`), this library
focuses on the most commonly used core feature of `SOCKS CONNECT`.
In this mode, a SOCKS server acts as a generic proxy allowing higher level
application protocols to work through it.

<table>
  <tr>
    <th></th>
    <th>SOCKS4</th>
    <th>SOCKS4a</th>
    <th>SOCKS5</th>
  </tr>
  <tr>
    <th>Protocol specification</th>
    <td><a href="http://ftp.icm.edu.pl/packages/socks/socks4/SOCKS4.protocol">SOCKS4.protocol</a></td>
    <td><a href="http://ftp.icm.edu.pl/packages/socks/socks4/SOCKS4A.protocol">SOCKS4A.protocol</a></td>
    <td><a href="http://tools.ietf.org/html/rfc1928">RFC 1928</a></td>
  </tr>
  <tr>
    <th>Tunnel outgoing TCP connections</th>
    <td>✓</td>
    <td>✓</td>
    <td>✓</td>
  </tr>
  <tr>
    <th><a href="#remote-vs-local-dns-resolving">Remote DNS resolving</a></th>
    <td>✗</td>
    <td>✓</td>
    <td>✓</td>
  </tr>
  <tr>
    <th>IPv6 addresses</th>
    <td>✗</td>
    <td>✗</td>
    <td>✓</td>
  </tr>
  <tr>
    <th><a href="#username--password-authentication">Username/Password authentication</a></th>
    <td>✗</td>
    <td>✗</td>
    <td>✓ (as per <a href="http://tools.ietf.org/html/rfc1929">RFC 1929</a>)</td>
  </tr>
  <tr>
    <th>Handshake # roundtrips</th>
    <td>1</td>
    <td>1</td>
    <td>2 (3 with authentication)</td>
  </tr>
  <tr>
    <th>Handshake traffic<br />+ remote DNS</th>
    <td>17 bytes<br />✗</td>
    <td>17 bytes<br />+ hostname + 1</td>
    <td><em>variable</em> (+ auth + IPv6)<br />+ hostname - 3</td>
  </tr>
</table>

Note, this is __not__ a full SOCKS5 implementation due to missing GSSAPI
authentication (but it's unlikely you're going to miss it anyway).

### Explicitly setting protocol version

This library supports the SOCKS4, SOCKS4a and SOCKS5 protocol versions.
Usually, there's no need to worry about which protocol version is being used.
Depending on which features you use (e.g. [remote DNS resolving](#remote-vs-local-dns-resolving)
and [authentication](#username--password-authentication)),
the `Client` automatically uses the _best_ protocol available.
In general this library automatically switches to higher protocol versions
when needed, but tries to keep things simple otherwise and sticks to lower
protocol versions when possible.
The `Server` supports all protocol versions by default.

If want to explicitly set the protocol version, use the supported values `4`, `4a` or `5`:

```PHP
// valid protocol versions:
$client->setProtocolVersion('4a');
$server->setProtocolVersion(5);
```

In order to reset the protocol version to its default (i.e. automatic detection),
use `null` as protocol version.

```PHP
$client->setProcolVersion(null);
$server->setProtocolVersion(null);
```

### Remote vs. local DNS resolving

By default, the `Client` uses local DNS resolving to resolve target hostnames
into IP addresses and only transmits the resulting target IP to the socks server.

Resolving locally usually results in better performance as for each outgoing
request both resolving the hostname and initializing the connection to the
SOCKS server can be done simultanously. So by the time the SOCKS connection is
established (requires a TCP handshake for each connection), the target hostname
will likely already be resolved ( _usually_ either already cached or requires a
simple DNS query via UDP).

You may want to switch to remote DNS resolving if your local `Client` either can not
resolve target hostnames because it has no direct access to the internet or if
it should not resolve target hostnames because its outgoing DNS traffic might
be intercepted (in particular when using the
[Tor network](#using-the-tor-anonymity-network-to-tunnel-socks-connections)). 

Local DNS resolving is available in all SOCKS protocol versions.
Remote DNS resolving is only available for SOCKS4a and SOCKS5
(i.e. it is NOT available for SOCKS4).

Valid values are boolean `true`(default) or `false`.

```PHP
$client->setResolveLocal(false);
```

### Username / Password authentication

This library supports username/password authentication for SOCKS5 servers as
defined in [RFC 1929](http://tools.ietf.org/html/rfc1929).

On the client side, simply set your username and password to use for
authentication (see below).
For each further connection the client will merely send a flag to the server
indicating authentication information is available.
Only if the server requests authentication during the initial handshake,
the actual authentication credentials will be transmitted to the server.

Note that the password is transmitted in cleartext to the SOCKS proxy server,
so this methods should not be used on a network where you have to worry about eavesdropping.
Authentication is only supported by protocol version 5 (SOCKS5),
so setting authentication on the `Client` enforces communication with protocol
version 5 and complains if you have explicitly set anything else. 

```PHP
$client->setAuth('username', 'password');
```

Setting authentication on the `Server` enforces each further connected client
to use protocol version 5.
If a client tries to use any other protocol version, does not send along
authentication details or if authentication details can not be verified,
the connection will be rejected.

Because your authentication mechanism might take some time to actually check
the provided authentication credentials (like querying a remote database or webservice),
the server side uses a [Promise](https://github.com/reactphp/promise) based interface.
While this might seem complex at first, it actually provides a very simple way
to handle simultanous connections in a non-blocking fashion and increases overall performance.

```PHP
$server->setAuth(function ($username, $password) {
    // either return a boolean success value right away
    // or use promises for delayed authentication
});
```

Or if you only accept static authentication details, you can use the simple
array-based authentication method as a shortcut:

```PHP
$server->setAuthArray(array(
    'tom' => 'password',
    'admin' => 'root'
));
```

If you do not want to use authentication anymore:

```PHP
$client->unsetAuth();
$server->unsetAuth();
```

## Usage

### Using SSH as a SOCKS server

If you already have an SSH server set up, you can easily use it as a SOCKS
tunnel end point. On your client, simply start your SSH client and use
the `-D [port]` option to start a local SOCKS server (quoting the man page:
a `local "dynamic" application-level port forwarding`) by issuing:

`$ ssh -D 9050 ssh-server`

```PHP
$client = new Client($loop, '127.0.0.1', 9050);
```

### Using the Tor (anonymity network) to tunnel SOCKS connections

The [Tor anonymity network](http://www.torproject.org) client software is designed
to encrypt your traffic and route it over a network of several nodes to conceal its origin.
It presents a SOCKS4 and SOCKS5 interface on TCP port 9050 by default
which allows you to tunnel any traffic through the anonymity network.
In most scenarios you probably don't want your client to resolve the target hostnames,
because you would leak DNS information to anybody observing your local traffic.
Also, Tor provides hidden services through an `.onion` pseudo top-level domain
which have to be resolved by Tor.

```PHP
$client = new Client($loop, '127.0.0.1', 9050);
$client->setResolveLocal(false);
```

## Install

The recommended way to install this library is [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/socks-react": "~0.2.0"
    }
}
```

## License

MIT, see LICENSE
<?php

use Clue\React\Socks\Server;
use React\Socket\Server as Socket;

include_once __DIR__.'/../vendor/autoload.php';

$port = isset($argv[1]) ? $argv[1] : 9050;

$loop = React\EventLoop\Factory::create();

// listen on localhost:$port
$socket = new Socket($loop);
$socket->listen($port,'localhost');

// start a new server listening for incoming connection on the given socket
$server = new Server($loop, $socket);

echo 'SOCKS server listening on localhost:' . $port . PHP_EOL;

$loop->run();
<?php

use React\Stream\Stream;
use Clue\React\Socks\Client;

include_once __DIR__.'/../vendor/autoload.php';

$port = isset($argv[1]) ? $argv[1] : 9050;

$loop = React\EventLoop\Factory::create();

$client = new Client($loop, '127.0.0.1', $port);
$client->setTimeout(3.0);
$client->setResolveLocal(false);
// $client->setProtocolVersion(5);
// $client->setAuth('test','test');

echo 'Demo SOCKS client connecting to SOCKS server 127.0.0.1:' . $port . PHP_EOL;
echo 'Not already running a SOCKS server? Try this: ssh -D ' . $port . ' localhost' . PHP_EOL;

$tcp = $client->createConnector();

$tcp->create('www.google.com', 80)->then(function (Stream $stream) {
    echo 'connected' . PHP_EOL;
    $stream->write("GET / HTTP/1.0\r\n\r\n");
    $stream->on('data', function ($data) {
        echo $data;
    });
}, 'var_dump');

$loop->run();
<?php

use Clue\React\Socks\Client;
use Clue\React\Socks\Server;
use React\Socket\Server as Socket;

include_once __DIR__.'/../vendor/autoload.php';

$myPort = isset($argv[1]) ? $argv[1] : 9051;
$otherPort = isset($argv[2]) ? $argv[2] : 9050;

$loop = React\EventLoop\Factory::create();

// set next SOCKS server localhost:$otherPort as target
$target = new Client($loop, '127.0.0.1', $otherPort);
$target->setAuth('user','p@ssw0rd');

// listen on localhost:$myPort
$socket = new Socket($loop);
$socket->listen($myPort, 'localhost');

// start a new server which forwards all connections to the other SOCKS server
$server = new Server($loop, $socket, $target->createConnector());

echo 'SOCKS server listening on localhost:' . $myPort . ' (which forwards everything to target SOCKS server 127.0.0.1:' . $otherPort . ')' . PHP_EOL;
echo 'Not already running the target SOCKS server? Try this: php server-auth.php ' . $otherPort . PHP_EOL;

$loop->run();
<?php

use Clue\React\Socks\Server;
use React\Socket\Server as Socket;

include_once __DIR__.'/../vendor/autoload.php';

$port = isset($argv[1]) ? $argv[1] : 9050;

$loop = React\EventLoop\Factory::create();

// listen on localhost:$port
$socket = new Socket($loop);
$socket->listen($port,'localhost');

// start a new server listening for incoming connection on the given socket
// require authentication and hence make this a SOCKS5-only server
$server = new Server($loop, $socket);
$server->setAuthArray(array(
    'tom' => 'god',
    'user' => 'p@ssw0rd'
));

echo 'SOCKS5 server requiring authentication listening on localhost:' . $port . PHP_EOL;

$loop->run();
{
    "name": "clue/socks-react",
    "description": "Async SOCKS proxy client and server (SOCKS4, SOCKS4a and SOCKS5)",
    "keywords": ["socks client", "socks server", "tcp tunnel", "socks protocol", "async", "react"],
    "homepage": "https://github.com/clue/php-socks-react",
    "license": "MIT",
    "authors": [
        {
            "name": "Christian Lück",
            "email": "christian@lueck.tv"
        }
    ],
    "autoload": {
        "psr-4": {"Clue\\React\\Socks\\": "src"}
    },
    "require": {
        "php": ">=5.3",
        "react/event-loop": "0.3.*",
        "react/socket-client": "0.3.*",
        "react/socket": "0.3.*",
        "react/dns": "0.3.*",
        "react/stream": "0.3.*",
        "react/promise": "~1.0",
        "evenement/evenement": "~1.0"
    }
}
<?php

(include_once __DIR__.'/../vendor/autoload.php') OR die(PHP_EOL.'ERROR: composer autoloader not found, run "composer install" or see README for instructions'.PHP_EOL);

class TestCase extends PHPUnit_Framework_TestCase
{
    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();


        if (func_num_args() > 0) {
            $mock
                ->expects($this->once())
                ->method('__invoke')
                ->with($this->equalTo(func_get_arg(0)));
        } else {
            $mock
                ->expects($this->once())
                ->method('__invoke');
        }

        return $mock;
    }

    protected function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnceParameter($type)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->isInstanceOf($type));

        return $mock;
    }

    /**
     * @link https://github.com/reactphp/react/blob/master/tests/React/Tests/Socket/TestCase.php (taken from reactphp/react)
     */
    protected function createCallableMock()
    {
        return $this->getMock('CallableStub');
    }

    protected function expectPromiseResolve($promise)
    {
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $that = $this;
        $promise->then(null, function($error) use ($that) {
            $that->assertNull($error);
            $that->fail('promise rejected');
        });
        $promise->then($this->expectCallableOnce(), $this->expectCallableNever());

        return $promise;
    }

    protected function expectPromiseReject($promise)
    {
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $that = $this;
        $promise->then(function($value) use ($that) {
            $that->assertNull($value);
            $that->fail('promise resolved');
        });

        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());

        return $promise;
    }
}

class CallableStub
{
    public function __invoke()
    {
    }
}
<?php

use Clue\React\Socks\Client;

class ClientTest extends TestCase
{
    /** @var  Client */
    private $client;

    public function setUp()
    {
        $loop = React\EventLoop\Factory::create();
        $this->client = new Client($loop, '127.0.0.1', 9050);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidAuthInformation()
    {
        $this->client->setAuth(str_repeat('a', 256), 'test');
    }

    /**
     * @expectedException UnexpectedValueException
     * @dataProvider providerInvalidAuthVersion
     */
    public function testInvalidAuthVersion($version)
    {
        $this->client->setAuth('username', 'password');
        $this->client->setProtocolVersion($version);
    }

    public function providerInvalidAuthVersion()
    {
        return array(array('4'), array('4a'));
    }

    public function testValidAuthVersion()
    {
        $this->client->setAuth('username', 'password');
        $this->assertNull($this->client->setProtocolVersion(5));
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testInvalidCanNotSetAuthenticationForSocks4()
    {
        $this->client->setProtocolVersion(4);
        $this->client->setAuth('username', 'password');
    }

    public function testUnsetAuth()
    {
        // unset auth even if it's not set is valid
        $this->client->unsetAuth();

        $this->client->setAuth('username', 'password');
        $this->client->unsetAuth();
    }

    /**
     * @dataProvider providerValidProtocolVersion
     */
    public function testValidProtocolVersion($version)
    {
        $this->assertNull($this->client->setProtocolVersion($version));
    }

    public function providerValidProtocolVersion()
    {
        return array(array('4'), array('4a'), array('5'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidProtocolVersion()
    {
        $this->client->setProtocolVersion(3);
    }

    public function testValidResolveLocal()
    {
        $this->assertNull($this->client->setResolveLocal(false));
        $this->assertNull($this->client->setResolveLocal(true));
        $this->assertNull($this->client->setProtocolVersion('4'));
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testInvalidResolveRemote()
    {
        $this->client->setProtocolVersion('4');
        $this->client->setResolveLocal(false);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testInvalidResolveRemoteVersion()
    {
        $this->client->setResolveLocal(false);
        $this->client->setProtocolVersion('4');
    }

    public function testSetTimeout()
    {
        $this->client->setTimeout(1);
        $this->client->setTimeout(2.0);
        $this->client->setTimeout(3);
    }

    public function testCreateConnector()
    {
        $this->assertInstanceOf('\React\SocketClient\ConnectorInterface', $this->client->createConnector());
    }

    public function testCreateSecureConnector()
    {
        $this->assertInstanceOf('\React\SocketClient\SecureConnector', $this->client->createSecureConnector());
    }

    /**
     * @dataProvider providerAddress
     */
    public function testGetConnection($host, $port)
    {
        $this->assertInstanceOf('\React\Promise\PromiseInterface', $this->client->getConnection($host, $port));
    }

    public function providerAddress()
    {
        return array(
            array('localhost','80'),
            array('invalid domain','non-numeric')
        );
    }
}
<?php

use React\Stream\Stream;
use Clue\React\Socks\Client;
use Clue\React\Socks\Server;
use React\Promise\PromiseInterface;

class FunctionalTest extends TestCase
{
    private $loop;
    private $client;
    private $server;

    public function setUp()
    {
        $this->loop = React\EventLoop\Factory::create();

        $socket = $this->createSocketServer();
        $port = $socket->getPort();
        $this->assertNotEquals(0, $port);

        $this->server = new Server($this->loop, $socket);
        $this->client = new Client($this->loop, '127.0.0.1', $port);
    }

    public function testConnection()
    {
        $this->assertResolveStream($this->client->getConnection('www.google.com', 80));
    }

    public function testConnectionSocks4()
    {
        $this->server->setProtocolVersion(4);
        $this->client->setProtocolVersion(4);

        $this->assertResolveStream($this->client->getConnection('www.google.com', 80));
    }

    public function testConnectionSocks5()
    {
        $this->server->setProtocolVersion(5);
        $this->client->setProtocolVersion(5);

        $this->assertResolveStream($this->client->getConnection('www.google.com', 80));
    }

    public function testConnectionInvalidSocks4aRemote()
    {
        $this->client->setProtocolVersion('4a');
        $this->client->setResolveLocal(false);

        $this->assertResolveStream($this->client->getConnection('www.google.com', 80));
    }

    public function testConnectionSocks5Remote()
    {
        $this->client->setProtocolVersion(5);
        $this->client->setResolveLocal(false);

        $this->assertResolveStream($this->client->getConnection('www.google.com', 80));
    }

    public function testConnectionAuthentication()
    {
        $this->server->setAuthArray(array('name' => 'pass'));
        $this->client->setAuth('name', 'pass');

        $this->assertResolveStream($this->client->getConnection('www.google.com', 80));
    }

    public function testConnectionAuthenticationUnused()
    {
        $this->client->setAuth('name', 'pass');

        $this->assertResolveStream($this->client->getConnection('www.google.com', 80));
    }

    public function testConnectionInvalidProtocolMismatch()
    {
        $this->server->setProtocolVersion(5);
        $this->client->setProtocolVersion(4);

        $this->assertRejectPromise($this->client->getConnection('www.google.com', 80));
    }

    public function testConnectionInvalidNoAuthentication()
    {
        $this->server->setAuthArray(array('name' => 'pass'));
        $this->client->setProtocolVersion(5);

        $this->assertRejectPromise($this->client->getConnection('www.google.com', 80));
    }

    public function testConnectionInvalidAuthenticationMismatch()
    {
        $this->server->setAuthArray(array('name' => 'pass'));
        $this->client->setAuth('user', 'other');

        $this->assertRejectPromise($this->client->getConnection('www.google.com', 80));
    }

    public function testConnectorOkay()
    {
        $tcp = $this->client->createConnector();

        $this->assertResolveStream($tcp->create('www.google.com', 80));
    }

    public function testConnectorInvalidDomain()
    {
        $tcp = $this->client->createConnector();

        $this->assertRejectPromise($tcp->create('www.google.commm', 80));
    }

    public function testConnectorInvalidUnboundPortTimeout()
    {
        $this->client->setTimeout(0.1);
        $tcp = $this->client->createConnector();

        $this->assertRejectPromise($tcp->create('www.google.com', 8080));
    }

    public function testSecureConnectorOkay()
    {
        $ssl = $this->client->createSecureConnector();

        $this->assertResolveStream($ssl->create('www.google.com', 443));
    }

    public function testSecureConnectorInvalidPlaintextIsNotSsl()
    {
        $ssl = $this->client->createSecureConnector();

        $this->assertRejectPromise($ssl->create('www.google.com', 80));
    }

    public function testSecureConnectorInvalidUnboundPortTimeout()
    {
        $this->client->setTimeout(0.1);
        $ssl = $this->client->createSecureConnector();

        $this->assertRejectPromise($ssl->create('www.google.com', 8080));
    }

    private function createSocketServer()
    {
        $socket = new React\Socket\Server($this->loop);
        $socket->listen(0);

        return $socket;
    }

    private function assertResolveStream($promise)
    {
        $this->expectPromiseResolve($promise);

        $promise->then(function ($stream) {
            $stream->close();
        });

        $this->waitFor($promise);
    }

    private function assertRejectPromise($promise)
    {
        $this->expectPromiseReject($promise);

        $this->setExpectedException('Exception');
        $this->waitFor($promise);
    }

    private function waitFor(PromiseInterface $promise)
    {
        $resolved = null;
        $exception = null;

        $promise->then(function ($c) use (&$resolved) {
            $resolved = $c;
        }, function($error) use (&$exception) {
            $exception = $error;
        });

        while ($resolved === null && $exception === null) {
            $this->loop->tick();
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $resolved;
    }
}
<?php

use Clue\React\Socks\StreamReader;

class StreamReaderTest extends TestCase
{
    private $reader;

    public function setUp()
    {
        $this->reader = new StreamReader();
    }

    public function testReadByteAssertCorrect()
    {
        $this->reader->readByteAssert(0x01)->then($this->expectCallableOnce(0x01));

        $this->reader->write("\x01");
    }

    public function testReadByteAssertInvalid()
    {
        $this->reader->readByteAssert(0x02)->then(null, $this->expectCallableOnce());

        $this->reader->write("\x03");
    }

    public function testReadStringNull()
    {
        $this->reader->readStringNull()->then($this->expectCallableOnce('hello'));

        $this->reader->write("hello\x00");
    }

    public function testReadStringLength()
    {
        $this->reader->readLength(5)->then($this->expectCallableOnce('hello'));

        $this->reader->write('he');
        $this->reader->write('ll');
        $this->reader->write('o ');

        $this->assertEquals(' ', $this->reader->getBuffer());
    }

    public function testReadBuffered()
    {
        $this->reader->write('hello');

        $this->reader->readLength(5)->then($this->expectCallableOnce('hello'));

        $this->assertEquals('', $this->reader->getBuffer());
    }

    public function testSequence()
    {
        $this->reader->readByte()->then($this->expectCallableOnce(ord('h')));
        $this->reader->readByteAssert(ord('e'))->then($this->expectCallableOnce(ord('e')));
        $this->reader->readLength(4)->then($this->expectCallableOnce('llo '));
        $this->reader->readBinary(array('w'=>'C', 'o' => 'C'))->then($this->expectCallableOnce(array('w' => ord('w'), 'o' => ord('o'))));

        $this->reader->write('hello world');

        $this->assertEquals('rld', $this->reader->getBuffer());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidStructure()
    {
        $this->reader->readBinary(array('invalid' => 'y'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidCallback()
    {
        $this->reader->readBufferCallback(array());
    }
}
<?php

use Clue\React\Socks\Server;

class ServerTest extends TestCase
{
    /** @var Server */
    private $server;

    public function setUp()
    {
        $socket = $this->getMockBuilder('React\Socket\Server')
            ->disableOriginalConstructor()
            ->getMock();

        $loop = $this->getMockBuilder('React\EventLoop\StreamSelectLoop')
            ->disableOriginalConstructor()
            ->getMock();

        $connector = $this->getMockBuilder('React\SocketClient\Connector')
            ->disableOriginalConstructor()
            ->getMock();

        $this->server = new Server($loop, $socket, $connector);
    }

    public function testSetProtocolVersion()
    {
        $this->server->setProtocolVersion(4);
        $this->server->setProtocolVersion('4a');
        $this->server->setProtocolVersion(5);
        $this->server->setProtocolVersion(null);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetInvalidProtocolVersion()
    {
        $this->server->setProtocolVersion(6);
    }

    public function testSetAuthArray()
    {
        $this->server->setAuthArray(array());

        $this->server->setAuthArray(array(
            'name1' => 'password1',
            'name2' => 'password2'
        ));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetAuthInvalid()
    {
        $this->server->setAuth(true);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testUnableToSetAuthIfProtocolDoesNotSupportAuth()
    {
        $this->server->setProtocolVersion(4);

        $this->server->setAuthArray(array());
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testUnableToSetProtocolWhichDoesNotSupportAuth()
    {
        $this->server->setAuthArray(array());

        // this is okay
        $this->server->setProtocolVersion(5);

        $this->server->setProtocolVersion(4);
    }

    public function testUnsetAuth()
    {
        $this->server->unsetAuth();
        $this->server->unsetAuth();
    }
}
<?xml version="1.0" encoding="UTF-8"?>

<phpunit colors="true" bootstrap="./tests/bootstrap.php">
    <testsuites>
        <testsuite name="Socks Test Suite">
            <directory>./tests/</directory>
        </testsuite>
    </testsuites>
    <filter>
        <whitelist>
            <directory>./src/</directory>
        </whitelist>
    </filter>
</phpunit>
{
    "_readme": [
        "This file locks the dependencies of your project to a known state",
        "Read more about it at http://getcomposer.org/doc/01-basic-usage.md#composer-lock-the-lock-file",
        "This file is @generated automatically"
    ],
    "hash": "10f5ad1a61dcc495ce79b8c5895ff236",
    "packages": [
        {
            "name": "evenement/evenement",
            "version": "v1.0.0",
            "source": {
                "type": "git",
                "url": "https://github.com/igorw/evenement.git",
                "reference": "fa966683e7df3e5dd5929d984a44abfbd6bafe8d"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/igorw/evenement/zipball/fa966683e7df3e5dd5929d984a44abfbd6bafe8d",
                "reference": "fa966683e7df3e5dd5929d984a44abfbd6bafe8d",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.0"
            },
            "type": "library",
            "autoload": {
                "psr-0": {
                    "Evenement": "src"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Igor Wiedler",
                    "email": "igor@wiedler.ch",
                    "homepage": "http://wiedler.ch/igor/"
                }
            ],
            "description": "Événement is a very simple event dispatching library for PHP 5.3",
            "keywords": [
                "event-dispatcher"
            ],
            "time": "2012-05-30 15:01:08"
        },
        {
            "name": "react/cache",
            "version": "v0.3.2",
            "target-dir": "React/Cache",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/cache.git",
                "reference": "437357102effb562b44dbc0ac4eb2c209c4f572b"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/cache/zipball/437357102effb562b44dbc0ac4eb2c209c4f572b",
                "reference": "437357102effb562b44dbc0ac4eb2c209c4f572b",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.2",
                "react/promise": "~1.0"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\Cache": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Async caching.",
            "keywords": [
                "cache"
            ],
            "time": "2013-04-24 08:33:43"
        },
        {
            "name": "react/dns",
            "version": "v0.3.2",
            "target-dir": "React/Dns",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/dns.git",
                "reference": "518e12e12f0a86e63ba482ebd728408391cc883f"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/dns/zipball/518e12e12f0a86e63ba482ebd728408391cc883f",
                "reference": "518e12e12f0a86e63ba482ebd728408391cc883f",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.2",
                "react/cache": "0.3.*",
                "react/promise": "~1.0",
                "react/socket": "0.3.*"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\Dns": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Async DNS resolver.",
            "keywords": [
                "dns",
                "dns-resolver"
            ],
            "time": "2013-04-27 08:57:30"
        },
        {
            "name": "react/event-loop",
            "version": "v0.3.4",
            "target-dir": "React/EventLoop",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/event-loop.git",
                "reference": "235cddfa999a392e7d63dc9bef2e042492608d9f"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/event-loop/zipball/235cddfa999a392e7d63dc9bef2e042492608d9f",
                "reference": "235cddfa999a392e7d63dc9bef2e042492608d9f",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.3"
            },
            "suggest": {
                "ext-libev": "*",
                "ext-libevent": ">=0.0.5"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\EventLoop": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Event loop abstraction layer that libraries can use for evented I/O.",
            "keywords": [
                "event-loop"
            ],
            "time": "2013-07-21 02:23:09"
        },
        {
            "name": "react/promise",
            "version": "v1.0.4",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/promise.git",
                "reference": "d6de8cae1dbb4878d909c41cb89aff764504472c"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/promise/zipball/d6de8cae1dbb4878d909c41cb89aff764504472c",
                "reference": "d6de8cae1dbb4878d909c41cb89aff764504472c",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.3"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "1.0-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\Promise": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@googlemail.com",
                    "homepage": "http://sorgalla.com",
                    "role": "maintainer"
                }
            ],
            "description": "A lightweight implementation of CommonJS Promises/A for PHP",
            "time": "2013-04-03 14:05:55"
        },
        {
            "name": "react/socket",
            "version": "v0.3.4",
            "target-dir": "React/Socket",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/socket.git",
                "reference": "19bc0c4309243717396022ffb2e59be1cc784327"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/socket/zipball/19bc0c4309243717396022ffb2e59be1cc784327",
                "reference": "19bc0c4309243717396022ffb2e59be1cc784327",
                "shasum": ""
            },
            "require": {
                "evenement/evenement": "1.0.*",
                "php": ">=5.3.3",
                "react/event-loop": "0.3.*",
                "react/stream": "0.3.*"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\Socket": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Library for building an evented socket server.",
            "keywords": [
                "Socket"
            ],
            "time": "2014-02-17 22:32:00"
        },
        {
            "name": "react/socket-client",
            "version": "v0.3.1",
            "target-dir": "React/SocketClient",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/socket-client.git",
                "reference": "87935a0223362c36cd30cf215cbec33377d31ca4"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/socket-client/zipball/87935a0223362c36cd30cf215cbec33377d31ca4",
                "reference": "87935a0223362c36cd30cf215cbec33377d31ca4",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.3",
                "react/dns": "0.3.*",
                "react/event-loop": "0.3.*",
                "react/promise": "~1.0"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\SocketClient": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Async connector to open TCP/IP and SSL/TLS based connections.",
            "keywords": [
                "Socket"
            ],
            "time": "2013-04-20 14:55:59"
        },
        {
            "name": "react/stream",
            "version": "v0.3.4",
            "target-dir": "React/Stream",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/stream.git",
                "reference": "feef56628afe3fa861f0da5f92c909e029efceac"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/stream/zipball/feef56628afe3fa861f0da5f92c909e029efceac",
                "reference": "feef56628afe3fa861f0da5f92c909e029efceac",
                "shasum": ""
            },
            "require": {
                "evenement/evenement": "1.0.*",
                "php": ">=5.3.3"
            },
            "suggest": {
                "react/event-loop": "0.3.*",
                "react/promise": "~1.0"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\Stream": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Basic readable and writable stream interfaces that support piping.",
            "keywords": [
                "pipe",
                "stream"
            ],
            "time": "2014-02-16 19:48:52"
        }
    ],
    "packages-dev": [

    ],
    "aliases": [

    ],
    "minimum-stability": "stable",
    "stability-flags": [

    ],
    "prefer-stable": false,
    "platform": {
        "php": ">=5.3"
    },
    "platform-dev": [

    ]
}
# CHANGELOG

This file is a manually maintained list of changes for each release. Feel free
to add your changes here when sending pull requests. Also send corrections if
you spot any mistakes.

## 0.2.0 (2014-09-27)

* BC break: Simplify constructors my making parameters optional.
  ([#10](https://github.com/clue/php-socks-react/pull/10))
  
  The `Factory` has been removed, you can now create instances of the `Client`
  and `Server` yourself:
  
  ```php
  // old
  $factory = new Factory($loop, $dns);
  $client = $factory->createClient('localhost', 9050);
  $server = $factory->createSever($socket);
  
  // new
  $client = new Client($loop, 'localhost', 9050);
  $server = new Server($loop, $socket);
  ```

* BC break: Remove HTTP support and link to [clue/buzz-react](https://github.com/clue/php-buzz-react) instead.
  ([#9](https://github.com/clue/php-socks-react/pull/9))
  
  HTTP operates on a different layer than this low-level SOCKS library.
  Removing this reduces the footprint of this library.
  
  > Upgrading? Check the [README](https://github.com/clue/php-socks-react#http-requests) for details.  

* Fix: Refactored to support other, faster loops (libev/libevent)
  ([#12](https://github.com/clue/php-socks-react/pull/12))

* Explicitly list dependencies, clean up examples and extend test suite significantly

## 0.1.0 (2014-05-19)

* First stable release
* Async SOCKS `Client` and `Server` implementation
* Project was originally part of [clue/socks](https://github.com/clue/php-socks)
  and was split off from its latest releave v0.4.0
  ([#1](https://github.com/clue/reactphp-socks/issues/1))

> Upgrading from clue/socks v0.4.0? Use namespace `Clue\React\Socks` instead of `Socks` and you're ready to go!

## 0.0.0 (2011-04-26)

* Initial concept, originally tracked as part of
  [clue/socks](https://github.com/clue/php-socks)
The MIT License (MIT)

Copyright (c) 2013 Christian Lück

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
<?php

namespace ConnectionManager\Extra;

use React\SocketClient\ConnectorInterface;
use \InvalidArgumentException;
use React\Promise\Deferred;
use \Exception;

class ConnectionManagerRepeat implements ConnectorInterface
{
    protected $connectionManager;
    protected $maximumRepetitions;
    
    public function __construct(ConnectorInterface $connectionManager, $maximumRepetitons)
    {
        if ($maximumRepetitons < 1) {
            throw new InvalidArgumentException('Maximum number of repetitions must be >= 1');
        }
        $this->connectionManager = $connectionManager;
        $this->maximumRepetitions = $maximumRepetitons;
    }
    
    public function create($host, $port)
    {
        return $this->tryConnection($this->maximumRepetitions, $host, $port);
    }
    
    public function tryConnection($repeat, $host, $port)
    {
        $that = $this;
        return $this->connectionManager->create($host, $port)->then(
            null,
            function ($error) use ($repeat, $that) {
                if ($repeat > 0) {
                    return $that->tryConnection($repeat - 1, $host, $port);
                } else {
                    throw new Exception('Connection still fails even after repeating', 0, $error);
                }
            }
        );
    }
}
<?php

namespace ConnectionManager\Extra;

use React\SocketClient\ConnectorInterface;
use React\Promise\Deferred;
use \Exception;

// a simple connection manager that rejects every single connection attempt
class ConnectionManagerReject implements ConnectorInterface
{
    public function create($host, $port)
    {
        $deferred = new Deferred();
        $deferred->reject(new Exception('Connection rejected'));
        return $deferred->promise();
    }
}
<?php

namespace ConnectionManager\Extra;

use React\SocketClient\ConnectorInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use Exception;

class ConnectionManagerTimeout implements ConnectorInterface
{
    private $connectionManager;
    private $loop;
    private $timeout;

    public function __construct(ConnectorInterface $connectionManager, LoopInterface $loop, $timeout)
    {
        $this->connectionManager = $connectionManager;
        $this->loop = $loop;
        $this->timeout = $timeout;
    }

    public function create($host, $port)
    {
        $deferred = new Deferred();
        $timedout = false;

        $tid = $this->loop->addTimer($this->timeout, function() use ($deferred, &$timedout) {
            $deferred->reject(new Exception('Connection attempt timed out'));
            $timedout = true;
            // TODO: find a proper way to actually cancel the connection
        });

        $loop = $this->loop;
        $this->connectionManager->create($host, $port)->then(function ($connection) use ($tid, $loop, &$timedout, $deferred) {
            if ($timedout) {
                // connection successfully established but timeout already expired => close successful connection
                $connection->end();
            } else {
                $loop->cancelTimer($tid);
                $deferred->resolve($connection);
            }
        }, function ($error) use ($loop, $tid, $deferred) {
            $loop->cancelTimer($tid);
            $deferred->reject($error);
        });
        return $deferred->promise();
    }
}
<?php

namespace ConnectionManager\Extra;

use React\SocketClient\ConnectorInterface;

// connection manager decorator which simplifies exchanging the actual connection manager during runtime
class ConnectionManagerSwappable implements ConnectorInterface
{
    protected $connectionManager;

    public function __construct(ConnectorInterface $connectionManager)
    {
        $this->connectionManager = $connectionManager;
    }

    public function create($host, $port)
    {
        return $this->connectionManager->create($host, $port);
    }

    public function setConnectionManager(ConnectorInterface $connectionManager)
    {
        $this->connectionManager = $connectionManager;
    }
}
<?php

namespace ConnectionManager\Extra;

use React\SocketClient\ConnectorInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

class ConnectionManagerDelay implements ConnectorInterface
{
    private $connectionManager;
    private $loop;
    private $delay;
    
    public function __construct(ConnectorInterface $connectionManager, LoopInterface $loop, $delay)
    {
        $this->connectionManager = $connectionManager;
        $this->loop = $loop;
        $this->delay = $delay;
    }
    
    public function create($host, $port)
    {
        $deferred = new Deferred();
        
        $connectionManager = $this->connectionManager;
        $this->loop->addTimer($this->delay, function() use ($deferred, $connectionManager, $host, $port) {
            $connectionManager->create($host, $port)->then(
                array($deferred, 'resolve'),
                array($deferred, 'reject')
            );
        });
        return $deferred->promise();
    }
}
<?php

namespace ConnectionManager\Extra\Multiple;

class ConnectionManagerRandom extends ConnectionManagerConsecutive
{
    public function create($host, $port)
    {
        $managers = $this->managers;
        shuffle($managers);
        
        return $this->tryConnection($managers, $host, $port);
    }
}
<?php

namespace ConnectionManager\Extra\Multiple;

use React\SocketClient\ConnectorInterface;
use React\Promise\Deferred;
use \UnderflowException;

class ConnectionManagerConsecutive implements ConnectorInterface
{
    protected $managers = array();

    public function addConnectionManager(ConnectorInterface $connectionManager)
    {
        $this->managers []= $connectionManager;
    }

    public function create($host, $port)
    {
        return $this->tryConnection($this->managers, $host, $port);
    }

    /**
     *
     * @param ConnectorInterface[] $managers
     * @param string $host
     * @param int $port
     * @return Promise
     * @internal
     */
    public function tryConnection(array $managers, $host, $port)
    {
        if (!$managers) {
            $deferred = new Deferred();
            $deferred->reject(new UnderflowException('No more managers to try to connect through'));
            return $deferred->promise();
        }
        $manager = array_shift($managers);
        $that = $this;
        return $manager->create($host,$port)->then(null, function() use ($that, $managers, $host, $port) {
            // connection failed, re-try with remaining connection managers
            return $that->tryConnection($managers, $host, $port);
        });
    }
}
<?php

namespace ConnectionManager\Extra\Multiple;

use React\SocketClient\ConnectorInterface;
use React\Promise\Deferred;
use \UnderflowException;
use \InvalidArgumentException;

class ConnectionManagerSelective implements ConnectorInterface
{
    const MATCH_ALL = '*';

    private $targets = array();

    public function create($host, $port)
    {
        try {
            $cm = $this->getConnectionManagerFor($host, $port);
        }
        catch (UnderflowException $e) {
            $deferred = new Deferred();
            $deferred->reject($e);
            return $deferred->promise();
        }
        return $cm->create($host, $port);
    }

    public function addConnectionManagerFor($connectionManager, $targetHost=self::MATCH_ALL, $targetPort=self::MATCH_ALL, $priority=0)
    {
        $this->targets []= array(
            'connectionManager' => $connectionManager,
            'matchHost' => $this->createMatcherHost($targetHost),
            'matchPort' => $this->createMatcherPort($targetPort),
            'host'      => $targetHost,
            'port'      => $targetPort,
            'priority'  => $priority
        );

        // return the key as new entry ID
        end($this->targets);
        $id = key($this->targets);

        // sort array by priority
        $targets =& $this->targets;
        uksort($this->targets, function ($a, $b) use ($targets) {
            $pa = $targets[$a]['priority'];
            $pb = $targets[$b]['priority'];
            return ($pa < $pb ? -1 : ($pa > $pb ? 1 : ($a - $b)));
        });

        return $id;
    }

    public function getConnectionManagerEntries()
    {
        return $this->targets;
    }

    public function removeConnectionManagerEntry($id)
    {
        unset($this->targets[$id]);
    }

    public function getConnectionManagerFor($targetHost, $targetPort)
    {
        foreach ($this->targets as $target) {
            if ($target['matchPort']($targetPort) && $target['matchHost']($targetHost)) {
                return $target['connectionManager'];
            }
        }
        throw new UnderflowException('No connection manager for given target found');
    }

    // *
    // singlePort
    // startPort - targetPort
    // port1, port2, port3
    // startPort - targetPort, portAdditional
    public function createMatcherPort($pattern)
    {
        if ($pattern === self::MATCH_ALL) {
            return function() {
                return true;
            };
        } else if (strpos($pattern, ',') !== false) {
            $checks = array();
            foreach (explode(',', $pattern) as $part) {
                $checks []= $this->createMatcherPort(trim($part));
            }
            return function ($port) use ($checks) {
                foreach ($checks as $check) {
                    if ($check($port)) {
                        return true;
                    }
                }
                return false;
            };
        } else if (preg_match('/^(\d+)$/', $pattern, $match)) {
            $single = $this->coercePort($match[1]);
            return function ($port) use ($single) {
                return ($port == $single);
            };
        } else if (preg_match('/^(\d+)\s*\-\s*(\d+)$/', $pattern, $match)) {
            $start = $this->coercePort($match[1]);
            $end   = $this->coercePort($match[2]);
            if ($start >= $end) {
                throw new InvalidArgumentException('Invalid port range given');
            }
            return function($port) use ($start, $end) {
                return ($port >= $start && $port <= $end);
            };
        } else {
             throw new InvalidArgumentException('Invalid port matcher given');
        }
    }

    private function coercePort($port)
    {
        // TODO: check 0-65535
        return (int)$port;
    }

    // *
    // targetHostname
    // targetIp
    // targetHostname, otherTargetHostname, anotherTargetHostname
    // TODO: targetIp/netmaskNum
    // TODO: targetIp/netmaskIp
    public function createMatcherHost($pattern)
    {
        if ($pattern === self::MATCH_ALL) {
            return function() {
                return true;
            };
        } else if (strpos($pattern, ',') !== false) {
            $checks = array();
            foreach (explode(',', $pattern) as $part) {
                $checks []= $this->createMatcherHost(trim($part));
            }
            return function ($host) use ($checks) {
                foreach ($checks as $check) {
                    if ($check($host)) {
                        return true;
                    }
                }
                return false;
            };
        } else if (is_string($pattern)) {
            $pattern = strtolower($pattern);
            return function($target) use ($pattern) {
                return fnmatch($pattern, strtolower($target));
            };
        } else {
            throw new InvalidArgumentException('Invalid host matcher given');
        }
    }
}
# clue/connection-manager-extra [![Build Status](https://travis-ci.org/clue/php-connection-manager-extra.svg?branch=master)](https://travis-ci.org/clue/php-connection-manager-extra)

This project provides _extra_ (in terms of "additional", "extraordinary", "special" and "unusual") decorators
built upon [react/socket-client](https://github.com/reactphp/socket-client).

## Introduction

If you're not already familar with [react/socket-client](https://github.com/reactphp/socket-client),
think of it as an async (non-blocking) version of [`fsockopen()`](http://php.net/manual/en/function.fsockopen.php)
or [`stream_socket_client()`](http://php.net/manual/en/function.stream-socket-client.php).
I.e. before you can send and receive data to/from a remote server, you first have to establish a connection - which
takes its time because it involves several steps.
In order to be able to establish several connections at the same time, [react/socket-client](https://github.com/reactphp/socket-client) provides a simple
API to establish simple connections in an async (non-blocking) way.

This project includes several classes that extend this base functionality by implementing the same simple `ConnectorInterface`.
This interface provides a single promise-based method `create($host, $ip)` which can be used to easily notify
when the connection is successfully established or the `Connector` gives up and the connection fails.

```php
$connector->create('www.google.com', 80)->then(function ($stream) {
    echo 'connection successfully established';
    $stream->write("GET / HTTP/1.0\r\nHost: www.google.com\r\n\r\n");
    $stream->end();
}, function ($exception) {
    echo 'connection attempt failed: ' . $exception->getMessage();
});

```

Because everything uses the same simple API, the resulting `Connector` classes can be easily interchanged
and be used in places that expect the normal `ConnectorInterface`. This can be used to stack them into each other,
like using [timeouts](#timeout) for TCP connections, [delaying](#delay) SSL/TLS connections,
[retrying](#repeating--retrying) failed connection attemps, [randomly](#random) picking a `Connector` or
any combination thereof.

## Usage

This section lists all this libraries' features along with some examples.
The examples assume you've [installed](#install) this library and
already [set up a `SocketClient/Connector` instance `$connector`](https://github.com/reactphp/socket-client#async-tcpip-connections).

All classes are located in the `ConnectionManager\Extra` namespace.

### Repeat

The `ConnectionManagerRepeat($connector, $repeat)` retries connecting to the given location up to a maximum
of `$repeat` times when the connection fails.

```php
$connectorRepeater = new \ConnectionManager\Extra\ConnectionManagerRepeat($connector, 3);
$connectorRepeater->create('www.google.com', 80)->then(function ($stream) {
    echo 'connection successfully established';
    $stream->close();
});
```

### Timeout

The `ConnectionManagerTimeout($connector, $timeout)` sets a maximum `$timeout` in seconds on when to give up
waiting for the connection to complete.

### Delay

The `ConnectionManagerDelay($connector, $delay)` sets a fixed initial `$delay` in seconds before actually
trying to connect. (Not to be confused with [`ConnectionManagerTimeout`](#timeout) which sets a _maximum timeout_.)

### Reject

The `ConnectionManagerReject()` simply rejects every single connection attempt.
This is particularly useful for the below [`ConnectionManagerSelective`](#selective) to reject connection attempts
to only certain destinations (for example blocking advertisements or harmful sites).

### Swappable

The `ConnectionManagerSwappable($connector)` is a simple decorator for other `ConnectionManager`s to
simplify exchanging the actual `ConnectionManager` during runtime (`->setConnectionManager($connector)`).

### Consecutive

The `ConnectionManagerConsecutive($connectors)` establishs connections by trying to connect through
any of the given `ConnectionManager`s in consecutive order until the first one succeeds.

### Random

The `ConnectionManagerRandom($connectors)` works much like `ConnectionManagerConsecutive` but instead
of using a fixed order, it always uses a randomly shuffled order.

### Selective

The `ConnectionManagerSelective()` manages several `Connector`s and forwards connection through either of
those besed on lists similar to to firewall or networking access control lists (ACLs).

This allows fine-grained control on how to handle outgoing connections, like rejecting advertisements,
delaying HTTP requests, or forwarding HTTPS connection through a foreign country.

```php
$connectorSelective->addConnectionManagerFor($connector, $targetHost, $targetPort, $priority);
```

## Install

The recommended way to install this library is [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/connection-manager-extra": "0.3.*"
    }
}
```

## License

MIT
{
    "name": "clue/connection-manager-extra",
     "description": "Extra decorators for creating async TCP/IP connections built upon react/socket-client",
    "keywords": ["SocketClient", "network", "connection", "timeout", "delay", "reject", "repeat", "retry", "random", "acl", "firewall"],
    "homepage": "https://github.com/clue/php-connection-manager-extra",
    "license": "MIT",
    "authors": [
        {
            "name": "Christian Lück",
            "email": "christian@lueck.tv"
        }
    ],
    "autoload": {
        "psr-4": {"ConnectionManager\\Extra\\": "src"}
    },
    "require": {
        "php": ">=5.3",
        "react/socket-client": "0.3.*|0.4.*",
        "react/event-loop": "0.3.*|0.4.*",
        "react/promise": "~1.0|~2.0"
    }
}
<?php

use React\Promise\Deferred;

require __DIR__ . '/../vendor/autoload.php';

class TestCase extends PHPUnit_Framework_TestCase
{
    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnceParameter($type)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->isInstanceOf($type));

        return $mock;
    }

    protected function expectCallableOnceValue($type)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->isInstanceOf($type));

        return $mock;
    }

    /**
     * @link https://github.com/reactphp/react/blob/master/tests/React/Tests/Socket/TestCase.php (taken from reactphp/react)
     */
    protected function createCallableMock()
    {
        return $this->getMock('CallableStub');
    }

    protected function createConnectionManagerMock($ret)
    {
        $mock = $this->getMockBuilder('React\SocketClient\Connector')
            ->disableOriginalConstructor()
            ->getMock();

        $deferred = new Deferred();
        $deferred->resolve($ret);

        $mock
            ->expects($this->any())
            ->method('create')
            ->will($this->returnValue($deferred->promise()));

        return $mock;
    }

    protected function assertPromiseResolve($promise)
    {
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then($this->expectCallableOnce(), $this->expectCallableNever());
    }

    protected function assertPromiseReject($promise)
    {
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());
    }
}

class CallableStub
{
    public function __invoke()
    {
    }
}
<?php


use ConnectionManager\Extra\ConnectionManagerDelay;

class ConnectionManagerDelayTest extends TestCase
{
    public function setUp()
    {
        $this->loop = React\EventLoop\Factory::create();
    }

    public function testDelayTenth()
    {
        $will = $this->createConnectionManagerMock(true);
        $cm = new ConnectionManagerDelay($will, $this->loop, 0.1);

        $promise = $cm->create('www.google.com', 80);
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $this->loop->run();
        $promise->then($this->expectCallableOnce(), $this->expectCallableNever());
    }
}
<?php

use ConnectionManager\Extra\ConnectionManagerSwappable;
use ConnectionManager\Extra\ConnectionManagerReject;

class ConnectionManagerSwappableTest extends TestCase
{
    public function testSwap()
    {
        $wont = new ConnectionManagerReject();
        $cm = new ConnectionManagerSwappable($wont);

        $promise = $cm->create('www.google.com', 80);
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());

        $will = $this->createConnectionManagerMock(true);
        $cm->setConnectionManager($will);

        $promise = $cm->create('www.google.com', 80);
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
        $promise->then($this->expectCallableOnce(), $this->expectCallableNever());
    }
}
<?php

use ConnectionManager\Extra\ConnectionManagerReject;

use React\Stream\Stream;

use ConnectionManager\Extra\ConnectionManagerDelay;

use ConnectionManager\Extra\ConnectionManagerTimeout;

class ConnectionManagerTimeoutTest extends TestCase
{
    public function setUp()
    {
        $this->loop = React\EventLoop\Factory::create();
    }

    public function testTimeoutOkay()
    {
        $will = $this->createConnectionManagerMock(true);
        $cm = new ConnectionManagerTimeout($will, $this->loop, 0.1);

        $promise = $cm->create('www.google.com', 80);
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $this->loop->run();
        $promise->then($this->expectCallableOnce(), $this->expectCallableNever());
    }

    public function testTimeoutExpire()
    {
        $will = $this->createConnectionManagerMock(new Stream(fopen('php://temp', 'r'), $this->loop));
        $wont = new ConnectionManagerDelay($will, $this->loop, 0.2);

        $cm = new ConnectionManagerTimeout($wont, $this->loop, 0.1);

        $promise = $cm->create('www.google.com', 80);
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $this->loop->run();
        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());
    }

    public function testTimeoutAbort()
    {
        $wont = new ConnectionManagerReject();

        $cm = new ConnectionManagerTimeout($wont, $this->loop, 0.1);

        $promise = $cm->create('www.google.com', 80);
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $this->loop->run();
        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());
    }
}
<?php

use ConnectionManager\Extra\ConnectionManagerReject;

class ConnectionManagerRejectTest extends TestCase
{
    public function testReject()
    {
        $cm = new ConnectionManagerReject();
        $promise = $cm->create('www.google.com', 80);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());
    }
}
<?php

use ConnectionManager\Extra\Multiple\ConnectionManagerConsecutive;
use ConnectionManager\Extra\ConnectionManagerReject;

class ConnectionManagerConsecutiveTest extends TestCase
{
    public function testEmpty()
    {
        $cm = new ConnectionManagerConsecutive();

        $promise = $cm->create('www.google.com', 80);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());
    }

    public function testReject()
    {
        $wont = new ConnectionManagerReject();

        $cm = new ConnectionManagerConsecutive();
        $cm->addConnectionManager($wont);

        $promise = $cm->create('www.google.com', 80);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());
    }
}
<?php

use ConnectionManager\Extra\Multiple\ConnectionManagerRandom;
use ConnectionManager\Extra\ConnectionManagerReject;

class ConnectionManagerRandomTest extends TestCase
{
    public function testEmpty()
    {
        $cm = new ConnectionManagerRandom();

        $promise = $cm->create('www.google.com', 80);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());
    }

    public function testReject()
    {
        $wont = new ConnectionManagerReject();

        $cm = new ConnectionManagerRandom();
        $cm->addConnectionManager($wont);

        $promise = $cm->create('www.google.com', 80);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());
    }
}
<?php

use React\Stream\Stream;

use ConnectionManager\Extra\Multiple\ConnectionManagerSelective;
use ConnectionManager\Extra\ConnectionManagerReject;

class ConnectionManagerSelectiveTest extends TestCase
{
    public function testEmptyWillAlwaysReject()
    {
        $cm = new ConnectionManagerSelective();

        $promise = $cm->create('www.google.com', 80);
        $this->assertPromiseReject($promise);
    }

    public function testReject()
    {
        $wont = new ConnectionManagerReject();
        $will = $this->createConnectionManagerMock(new Stream(fopen('php://temp', 'r'), $this->createLoopMock()));

        $cm = new ConnectionManagerSelective();

        $cm->addConnectionManagerFor($will, 'www.google.com', 443);
        $cm->addConnectionManagerFor($will, 'www.youtube.com');

        $this->assertPromiseResolve($cm->create('www.google.com', 443));

        $this->assertPromiseReject($cm->create('www.google.com', 80));

        $this->assertPromiseResolve($cm->create('www.youtube.com', 80));
    }

    public function testRemove()
    {
        $wont = new ConnectionManagerReject();

        $cm = new ConnectionManagerSelective();
        $this->assertCount(0, $cm->getConnectionManagerEntries());

        $id = $cm->addConnectionManagerFor($wont);
        $this->assertCount(1, $cm->getConnectionManagerEntries());

        $cm->removeConnectionManagerEntry($id);
        $this->assertCount(0, $cm->getConnectionManagerEntries());

        // removing a non-existant ID is a NO-OP
        $cm->removeConnectionManagerEntry(12345);
        $this->assertCount(0, $cm->getConnectionManagerEntries());
    }

    public function testSamePriorityFirstWins()
    {
        $wont = new ConnectionManagerReject();
        $will = $this->createConnectionManagerMock(new Stream(fopen('php://temp', 'r'), $this->createLoopMock()));

        $cm = new ConnectionManagerSelective();

        $cm->addConnectionManagerFor($will, 'www.google.com', 443, 100);
        $cm->addConnectionManagerFor($wont, ConnectionManagerSelective::MATCH_ALL, ConnectionManagerSelective::MATCH_ALL, 100);

        $this->assertPromiseResolve($cm->create('www.google.com', 443));
        $this->assertPromiseReject($cm->create('www.google.com', 80));
    }

    public function testWildcardsMatch()
    {
        $wont = new ConnectionManagerReject();
        $will = $this->createConnectionManagerMock(new Stream(fopen('php://temp', 'r'), $this->createLoopMock()));

        $cm = new ConnectionManagerSelective();

        $cm->addConnectionManagerFor($will, '*.com');
        $cm->addConnectionManagerFor($will, '*', '443-444,8080');
        $cm->addConnectionManagerFor($will, '*.youtube.*,youtube.*', '*');

        $this->assertPromiseResolve($cm->create('www.google.com', 80));
        $this->assertPromiseReject($cm->create('www.google.de', 80));

        $this->assertPromiseResolve($cm->create('www.google.de', 443));
        $this->assertPromiseResolve($cm->create('www.google.de', 444));
        $this->assertPromiseResolve($cm->create('www.google.de', 8080));
        $this->assertPromiseReject($cm->create('www.google.de', 445));

        $this->assertPromiseResolve($cm->create('www.youtube.de', 80));
        $this->assertPromiseResolve($cm->create('download.youtube.de', 80));
        $this->assertPromiseResolve($cm->create('youtube.de', 80));
    }

    private function createLoopMock()
    {
        return $this->getMockBuilder('React\EventLoop\StreamSelectLoop')
                     ->disableOriginalConstructor()
                     ->getMock();
    }
}
<?php

use ConnectionManager\Extra\ConnectionManagerRepeat;
use ConnectionManager\Extra\ConnectionManagerReject;

class ConnectionManagerRepeatTest extends TestCase
{
    public function testRepeatRejected()
    {
        $wont = new ConnectionManagerReject();
        $cm = new ConnectionManagerRepeat($wont, 3);
        $promise = $cm->create('www.google.com', 80);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidRepetitions()
    {
        $wont = new ConnectionManagerReject();
        $cm = new ConnectionManagerRepeat($wont, -3);
    }
}
<?xml version="1.0" encoding="UTF-8"?>

<phpunit bootstrap="tests/bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="connection-manager-extra Test Suite">
            <directory>./tests/</directory>
        </testsuite>
    </testsuites>
    <filter>
        <whitelist>
            <directory>./ConnectionManager</directory>
        </whitelist>
    </filter>
</phpunit># CHANGELOG

This file is a manually maintained list of changes for each release. Feel free
to add your changes here when sending pull requests. Also send corrections if
you spot any mistakes.

## 0.3.1 (2014-09-27)

* Support React PHP v0.4 (while preserving BC with React PHP v0.3)
  (#4)

## 0.3.0 (2013-06-24)

* BC break: Switch from (deprecated) `clue/connection-manager` to `react/socket-client`
  and thus replace each occurance of `getConnect($host, $port)` with `create($host, $port)`
  (#1)
  
* Fix: Timeouts in `ConnectionManagerTimeout` now actually work
  (#1)

* Fix: Properly reject promise in `ConnectionManagerSelective` when no targets
  have been found
  (#1)

## 0.2.0 (2013-02-08)

* Feature: Add `ConnectionManagerSelective` which works like a network/firewall ACL

## 0.1.0 (2013-01-12)

* First tagged release

Copyright (c) 2011 Igor Wiedler

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
<?php

/*
 * This file is part of Evenement.
 *
 * (c) Igor Wiedler <igor@wiedler.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Evenement;

class EventEmitter implements EventEmitterInterface
{
    protected $listeners = array();

    public function on($event, $listener)
    {
        if (!is_callable($listener)) {
            throw new \InvalidArgumentException('The provided listener was not a valid callable.');
        }

        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = array();
        }

        $this->listeners[$event][] = $listener;
    }

    public function once($event, $listener)
    {
        $that = $this;

        $onceListener = function () use ($that, &$onceListener, $event, $listener) {
            $that->removeListener($event, $onceListener);

            call_user_func_array($listener, func_get_args());
        };

        $this->on($event, $onceListener);
    }

    public function removeListener($event, $listener)
    {
        if (isset($this->listeners[$event])) {
            if (false !== $index = array_search($listener, $this->listeners[$event], true)) {
                unset($this->listeners[$event][$index]);
            }
        }
    }

    public function removeAllListeners($event = null)
    {
        if ($event !== null) {
            unset($this->listeners[$event]);
        } else {
            $this->listeners = array();
        }
    }

    public function listeners($event)
    {
        return isset($this->listeners[$event]) ? $this->listeners[$event] : array();
    }

    public function emit($event, array $arguments = array())
    {
        foreach ($this->listeners($event) as $listener) {
            call_user_func_array($listener, $arguments);
        }
    }
}
<?php

/*
 * This file is part of Evenement.
 *
 * (c) Igor Wiedler <igor@wiedler.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Evenement;

interface EventEmitterInterface
{
    public function on($event, $listener);
    public function once($event, $listener);
    public function removeListener($event, $listener);
    public function removeAllListeners($event = null);
    public function listeners($event);
    public function emit($event, array $arguments = array());
}
<?php

/*
 * This file is part of Evenement.
 *
 * (c) Igor Wiedler <igor@wiedler.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Evenement;

class EventEmitter2 extends EventEmitter
{
    protected $options;
    protected $anyListeners = array();

    public function __construct(array $options = array())
    {
        $this->options = array_merge(array(
            'delimiter' => '.',
        ), $options);
    }

    public function onAny($listener)
    {
        $this->anyListeners[] = $listener;
    }

    public function offAny($listener)
    {
        if (false !== $index = array_search($listener, $this->anyListeners, true)) {
            unset($this->anyListeners[$index]);
        }
    }

    public function many($event, $timesToListen, $listener)
    {
        $that = $this;

        $timesListened = 0;

        if ($timesToListen == 0) {
            return;
        }

        if ($timesToListen < 0) {
            throw new \OutOfRangeException('You cannot listen less than zero times.');
        }

        $manyListener = function () use ($that, &$timesListened, &$manyListener, $event, $timesToListen, $listener) {
            if (++$timesListened == $timesToListen) {
                $that->removeListener($event, $manyListener);
            }

            call_user_func_array($listener, func_get_args());
        };

        $this->on($event, $manyListener);
    }

    public function emit($event, array $arguments = array())
    {
        foreach ($this->anyListeners as $listener) {
            call_user_func_array($listener, $arguments);
        }

        parent::emit($event, $arguments);
    }

    public function listeners($event)
    {
        $matchedListeners = array();

        foreach ($this->listeners as $name => $listeners) {
            foreach ($listeners as $listener) {
                if ($this->matchEventName($event, $name)) {
                    $matchedListeners[] = $listener;
                }
            }
        }

        return $matchedListeners;
    }

    protected function matchEventName($matchPattern, $eventName)
    {
        $patternParts = explode($this->options['delimiter'], $matchPattern);
        $nameParts = explode($this->options['delimiter'], $eventName);

        if (count($patternParts) != count($nameParts)) {
            return false;
        }

        $size = min(count($patternParts), count($nameParts));
        for ($i = 0; $i < $size; $i++) {
            $patternPart = $patternParts[$i];
            $namePart = $nameParts[$i];

            if ('*' === $patternPart || '*' === $namePart) {
                continue;
            }

            if ($namePart === $patternPart) {
                continue;
            }

            return false;
        }

        return true;
    }
}
# Événement

Événement is a very simple event dispatching library for PHP 5.3.

It has the same design goals as [Silex](http://silex-project.org) and
[Pimple](http://pimple-project.org), to empower the user while staying concise
and simple.

It is very strongly inspired by the EventEmitter API found in
[node.js](http://nodejs.org). It includes an implementation of
[EventEmitter2](https://github.com/hij1nx/EventEmitter2), that extends
the original EventEmitter.

[![Build Status](https://secure.travis-ci.org/igorw/evenement.png)](http://travis-ci.org/igorw/evenement)

## Fetch

The recommended way to install Événement is [through composer](http://getcomposer.org).

Just create a composer.json file for your project:

```JSON
{
    "require": {
        "evenement/evenement": "dev-master"
    }
}
```

And run these two commands to install it:

    $ curl -s http://getcomposer.org/installer | php
    $ php composer.phar install

Now you can add the autoloader, and you will have access to the library:

```php
<?php
require 'vendor/autoload.php';
```

## Usage

### Creating an Emitter

```php
<?php
$emitter = new Evenement\EventEmitter();
```

### Adding Listeners

```php
<?php
$emitter->on('user.create', function (User $user) use ($logger) {
    $logger->log(sprintf("User '%s' was created.", $user->getLogin()));
});
```

### Emitting Events

```php
<?php
$emitter->emit('user.create', array($user));
```

Tests
-----

    $ phpunit

License
-------
MIT, see LICENSE.
{
    "name": "evenement/evenement",
    "description": "Événement is a very simple event dispatching library for PHP 5.3",
    "keywords": ["event-dispatcher"],
    "license": "MIT",
    "authors": [
        {
            "name": "Igor Wiedler",
            "email": "igor@wiedler.ch"
        }
    ],
    "require": {
        "php": ">=5.3.0"
    },
    "autoload": {
        "psr-0": {
            "Evenement": "src"
        }
    }
}
<?php

/*
 * This file is part of Evenement.
 *
 * Copyright (c) 2011 Igor Wiedler
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

$loader = require __DIR__.'/../vendor/autoload.php';
$loader->add('Evenement\Tests', __DIR__);
<?php

/*
 * This file is part of Evenement.
 *
 * Copyright (c) 2011 Igor Wiedler
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Evenement\Tests;

class Listener
{
    public function onFoo()
    {
    }

    public static function onBar()
    {
    }
}
<?php

/*
 * This file is part of Evenement.
 *
 * Copyright (c) 2011 Igor Wiedler
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Evenement\Tests;

use Evenement\EventEmitter;

class EventEmitterTest extends \PHPUnit_Framework_TestCase
{
    private $emitter;

    public function setUp()
    {
        $this->emitter = new EventEmitter();
    }

    public function testAddListenerWithLambda()
    {
        $this->emitter->on('foo', function () {});
    }

    public function testAddListenerWithMethod()
    {
        $listener = new Listener();
        $this->emitter->on('foo', array($listener, 'onFoo'));
    }

    public function testAddListenerWithStaticMethod()
    {
        $this->emitter->on('bar', array('Evenement\Tests\Listener', 'onBar'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testAddListenerWithInvalidListener()
    {
        $this->emitter->on('foo', 'not a callable');
    }

    public function testOnce()
    {
        $listenerCalled = 0;

        $this->emitter->once('foo', function () use (&$listenerCalled) {
            $listenerCalled++;
        });

        $this->assertSame(0, $listenerCalled);

        $this->emitter->emit('foo');

        $this->assertSame(1, $listenerCalled);

        $this->emitter->emit('foo');

        $this->assertSame(1, $listenerCalled);
    }

    public function testEmitWithoutArguments()
    {
        $listenerCalled = false;

        $this->emitter->on('foo', function () use (&$listenerCalled) {
            $listenerCalled = true;
        });

        $this->assertSame(false, $listenerCalled);
        $this->emitter->emit('foo');
        $this->assertSame(true, $listenerCalled);
    }

    public function testEmitWithOneArgument()
    {
        $test = $this;

        $listenerCalled = false;

        $this->emitter->on('foo', function ($value) use (&$listenerCalled, $test) {
            $listenerCalled = true;

            $test->assertSame('bar', $value);
        });

        $this->assertSame(false, $listenerCalled);
        $this->emitter->emit('foo', array('bar'));
        $this->assertSame(true, $listenerCalled);
    }

    public function testEmitWithTwoArguments()
    {
        $test = $this;

        $listenerCalled = false;

        $this->emitter->on('foo', function ($arg1, $arg2) use (&$listenerCalled, $test) {
            $listenerCalled = true;

            $test->assertSame('bar', $arg1);
            $test->assertSame('baz', $arg2);
        });

        $this->assertSame(false, $listenerCalled);
        $this->emitter->emit('foo', array('bar', 'baz'));
        $this->assertSame(true, $listenerCalled);
    }

    public function testEmitWithNoListeners()
    {
        $this->emitter->emit('foo');
        $this->emitter->emit('foo', array('bar'));
        $this->emitter->emit('foo', array('bar', 'baz'));
    }

    public function testEmitWithTwoListeners()
    {
        $listenersCalled = 0;

        $this->emitter->on('foo', function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->emitter->on('foo', function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->assertSame(2, $listenersCalled);
    }

    public function testRemoveListenerMatching()
    {
        $listenersCalled = 0;

        $listener = function () use (&$listenersCalled) {
            $listenersCalled++;
        };

        $this->emitter->on('foo', $listener);
        $this->emitter->removeListener('foo', $listener);

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->assertSame(0, $listenersCalled);
    }

    public function testRemoveListenerNotMatching()
    {
        $listenersCalled = 0;

        $listener = function () use (&$listenersCalled) {
            $listenersCalled++;
        };

        $this->emitter->on('foo', $listener);
        $this->emitter->removeListener('bar', $listener);

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->assertSame(1, $listenersCalled);
    }

    public function testRemoveAllListenersMatching()
    {
        $listenersCalled = 0;

        $this->emitter->on('foo', function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->emitter->removeAllListeners('foo');

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->assertSame(0, $listenersCalled);
    }

    public function testRemoveAllListenersNotMatching()
    {
        $listenersCalled = 0;

        $this->emitter->on('foo', function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->emitter->removeAllListeners('bar');

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->assertSame(1, $listenersCalled);
    }

    public function testRemoveAllListenersWithoutArguments()
    {
        $listenersCalled = 0;

        $this->emitter->on('foo', function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->emitter->on('bar', function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->emitter->removeAllListeners();

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->emitter->emit('bar');
        $this->assertSame(0, $listenersCalled);
    }
}
<?php

/*
 * This file is part of Evenement.
 *
 * Copyright (c) 2011 Igor Wiedler
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Evenement\Tests;

use Evenement\EventEmitter2;

class EventEmitter2Test extends \PHPUnit_Framework_TestCase
{
    private $emitter;

    public function setUp()
    {
        $this->emitter = new EventEmitter2();
    }

    // matching tests from
    // test/wildcardEvents/addListener.js

    public function testWildcardMatching7()
    {
        $listenerCalled = 0;

        $listener = function () use (&$listenerCalled) {
            $listenerCalled++;
        };

        $this->emitter->on('*.test', $listener);
        $this->emitter->on('*.*', $listener);
        $this->emitter->on('*', $listener);

        $this->emitter->emit('other.emit');
        $this->emitter->emit('foo.test');

        $this->assertSame(3, $listenerCalled);
    }

    public function testWildcardMatching8()
    {
        $listenerCalled = 0;

        $listener = function () use (&$listenerCalled) {
            $listenerCalled++;
        };

        $this->emitter->on('foo.test', $listener);
        $this->emitter->on('*.*', $listener);
        $this->emitter->on('*', $listener);

        $this->emitter->emit('*.*');
        $this->emitter->emit('foo.test');
        $this->emitter->emit('*');

        $this->assertSame(5, $listenerCalled);
    }

    public function testOnAny()
    {
        $this->emitter->onAny(function () {});
    }

    public function testOnAnyWithEmit()
    {
        $listenerCalled = 0;

        $this->emitter->onAny(function () use (&$listenerCalled) {
            $listenerCalled++;
        });

        $this->assertSame(0, $listenerCalled);

        $this->emitter->emit('foo');

        $this->assertSame(1, $listenerCalled);

        $this->emitter->emit('bar');

        $this->assertSame(2, $listenerCalled);
    }

    public function testoffAnyWithEmit()
    {
        $listenerCalled = 0;

        $listener = function () use (&$listenerCalled) {
            $listenerCalled++;
        };

        $this->emitter->onAny($listener);
        $this->emitter->offAny($listener);

        $this->assertSame(0, $listenerCalled);
        $this->emitter->emit('foo');
        $this->assertSame(0, $listenerCalled);
    }

    /**
     * @dataProvider provideMany
     */
    public function testMany($amount)
    {
        $listenerCalled = 0;

        $this->emitter->many('foo', $amount, function () use (&$listenerCalled) {
            $listenerCalled++;
        });

        for ($i = 0; $i < $amount; $i++) {
            $this->assertSame($i, $listenerCalled);
            $this->emitter->emit('foo');
        }

        $this->emitter->emit('foo');
        $this->assertSame($amount, $listenerCalled);
    }

    public function provideMany()
    {
        return array(
            array(0),
            array(1),
            array(2),
            array(3),
            array(4),
            array(400),
        );
    }

    /**
     * @expectedException OutOfRangeException
     */
    public function testManyWithLessThanZeroTtl()
    {
        $this->emitter->many('foo', -1, function () {});
        $this->emitter->emit('foo');
    }
}
<?xml version="1.0" encoding="UTF-8"?>

<phpunit backupGlobals="false"
         backupStaticAttributes="false"
         colors="true"
         convertErrorsToExceptions="true"
         convertNoticesToExceptions="true"
         convertWarningsToExceptions="true"
         processIsolation="false"
         stopOnFailure="false"
         syntaxCheck="false"
         bootstrap="tests/bootstrap.php"
>
    <testsuites>
        <testsuite name="Evenement Test Suite">
            <directory>./tests/Evenement/</directory>
        </testsuite>
    </testsuites>

    <filter>
        <whitelist>
            <directory>./src/</directory>
        </whitelist>
    </filter>
</phpunit>
The MIT License (MIT)

Copyright (c) 2013 Christian Lück

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
<?php

namespace Clue\Psocksd\Command;

use Clue\Psocksd\ConnectionManagerLabeled;
use Clue\Psocksd\App;
use React\SocketClient\ConnectorInterface;
use \UnexpectedValueException;
use \InvalidArgumentException;
use \Exception;

class Via implements CommandInterface
{
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function getHelp()
    {
        return 'forward all connections via next SOCKS server';
    }

    public function run($args)
    {
        if (count($args) === 1 && $args[0] === 'list') {
            $this->runList();
        } else if (count($args) === 2 && $args[0] === 'default') {
            $this->runSetDefault($args[1]);
        } else if (count($args) === 2 && $args[0] === 'reject') {
            $this->runAdd($args[1], 'reject', -1);
        } else if ((count($args) === 3 || count($args) === 4) && $args[0] === 'add') {
            $this->runAdd($args[1], $args[2], isset($args[3]) ? $args[3] : 0);
        } else if (count($args) === 2 && $args[0] === 'remove') {
            $this->runRemove($args[1]);
        } else if (count($args) === 1 && $args[0] === 'reset') {
            $this->runReset();
        } else {
            echo (count($args) === 0 ? 'no' : 'error: invalid') . ' command arguments given. Valid options are:' . PHP_EOL;

            $this->app->getCommand('help')->dumpHelp(array(
                'list'                             => 'list all entries',
                'default <target>'                 => 'set given <target> socks proxy as default target',
                'reject <host>'                    => 'reject connections to the given host',
                'add <host> <target> [<priority>]' => 'add new <target> socks proxy for connections to given <host>',
                'remove <entryId>'                 => 'emove entry with given <id> (see "list")',
                'reset'                            => 'clear and reset everything and only connect locally'
            ));
        }
    }

    public function runList()
    {
        $cm = $this->app->getConnectionManager();

        $lengths = array(
            'id' => 3,
            'host' => 5,
            'port' => 5,
            'priority' => 5
        );

        $pad = '  ';

        $list = array();
        foreach ($cm->getConnectionManagerEntries() as $id => $entry) {
            $list [$id]= $entry;

            $entry['id'] = $id;
            foreach ($lengths as $key => &$value) {
                $l = mb_strlen($entry[$key], 'utf-8');
                if ($l > $value) {
                    $value = $l;
                }
            }
        }

        echo $this->pad('Id:', $lengths['id']) . $pad .
             $this->pad('Host:', $lengths['host']) . $pad .
             $this->pad('Port:', $lengths['port']) . $pad .
             $this->pad('Prio:', $lengths['priority']) . $pad .
             'Target:' . PHP_EOL;
        foreach ($list as $id => $entry) {
            echo $this->pad($id, $lengths['id']) . $pad .
                 $this->pad($entry['host'], $lengths['host']) . $pad .
                 $this->pad($entry['port'], $lengths['port']) . $pad .
                 $this->pad($entry['priority'], $lengths['priority']) . $pad .
                 $this->dumpConnectionManager($entry['connectionManager']) . PHP_EOL;
        }
    }

    public function runRemove($id)
    {
        $this->app->getConnectionManager()->removeConnectionManagerEntry($id);
    }

    public function runReset()
    {
        $cm = $this->app->getConnectionManager();

        // remove all connection managers
        foreach ($cm->getConnectionManagerEntries() as $id => $entry) {
            $cm->removeConnectionManagerEntry($id);
        }

        // add default connection manager
        $cm->addConnectionManagerFor($this->app->createConnectionManager('none'), '*', '*', App::PRIORITY_DEFAULT);
    }

    public function runSetDefault($socket)
    {
        try {
            $via = $this->app->createConnectionManager($socket);
        }
        catch (Exception $e) {
            echo 'error: invalid target: ' . $e->getMessage() . PHP_EOL;
            return;
        }

        // remove all CMs with PRIORITY_DEFAULT
        $cm = $this->app->getConnectionManager();
        foreach ($cm->getConnectionManagerEntries() as $id => $entry) {
            if ($entry['priority'] == App::PRIORITY_DEFAULT) {
                $cm->removeConnectionManagerEntry($id);
            }
        }

        $cm->addConnectionManagerFor($via, '*', '*', App::PRIORITY_DEFAULT);
    }

    public function runAdd($target, $socket, $priority)
    {
        try {
            $via = $this->app->createConnectionManager($socket);
        }
        catch (Exception $e) {
            echo 'error: invalid target: ' . $e->getMessage() . PHP_EOL;
            return;
        }

        try {
            $priority = $this->coercePriority($priority);
        }
        catch (Exception $e) {
            echo 'error: invalid priority: ' . $e->getMessage() . PHP_EOL;
            return;
        }

        $host = $target;
        $port = '*';

        $colon = strrpos($host, ':');

        // there is a colon and this is the only colon or there's a closing IPv6 bracket right before it
        if ($colon !== false && (strpos($host, ':') === $colon || strpos($host, ']') === ($colon - 1))) {
            $port = (int)substr($host, $colon + 1);
            $host = substr($host, 0, $colon);

            // remove IPv6 square brackets
            if (substr($host, 0, 1) === '[') {
                $host = substr($host, 1, -1);
            }
        }

        $this->app->getConnectionManager()->addConnectionManagerFor($via, $host, $port, $priority);
    }

    protected function coercePriority($priority)
    {
        $ret = filter_var($priority, FILTER_VALIDATE_FLOAT);
        if ($ret === false) {
            throw new InvalidArgumentException('Invalid priority given');
        }
        return $ret;
    }

    private function pad($str, $len)
    {
        return $str . str_repeat(' ', $len - mb_strlen($str, 'utf-8'));
    }

    protected function dumpConnectionManager(ConnectorInterface $connectionManager)
    {
        if ($connectionManager instanceof ConnectionManagerLabeled) {
            return (string)$connectionManager;
        }
        return get_class($connectionManager) . '(…)';
    }
}
<?php

namespace Clue\Psocksd\Command;

interface CommandInterface
{
    public function run($args);

    public function getHelp();
}
<?php

namespace Clue\Psocksd\Command;

use Clue\Psocksd\App;
use Clue\React\Socks\Client;
use React\SocketClient\Connector;
use React\SocketClient\ConnectorInterface;
use \UnexpectedValueException;
use \Exception;

class Ping implements CommandInterface
{
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function run($args)
    {
        if (count($args) !== 1) {
            echo 'error: command requires one argument (target socks server)'.PHP_EOL;
            return;
        }

        $socket = $args[0];
        try {
            $parsed = $this->app->parseSocksSocket($socket);
        }
        catch (Exception $e) {
            echo 'error: invalid ping target: ' . $e->getMessage() . PHP_EOL;
            return;
        }

        // TODO: remove hack
        // resolver can not resolve 'localhost' ATM
        if ($parsed['host'] === 'localhost') {
            $parsed['host'] = '127.0.0.1';
        }

        $direct = new Connector($this->app->getLoop(), $this->app->getResolver());
        $via = new Client($this->app->getLoop(), $parsed['host'], $parsed['port'], $direct, $this->app->getResolver());
        if (isset($parsed['protocolVersion'])) {
            try {
                $via->setProtocolVersion($parsed['protocolVersion']);
            }
            catch (Exception $e) {
                echo 'error: invalid protocol version: ' . $e->getMessage() . PHP_EOL;
                return;
            }
        }
        if (isset($parsed['user']) || isset($parsed['pass'])) {
            $parsed += array('user' =>'', 'pass' => '');
            try {
                $via->setAuth($parsed['user'], $parsed['pass']);
            }
            catch (Exception $e) {
                echo 'error: invalid authentication info: ' . $e->getMessage() . PHP_EOL;
                return;
            }
        }

        try {
            $via->setResolveLocal(false);
        }
        catch (UnexpectedValueException $ignore) {
            // ignore in case it's not allowed (SOCKS4 client)
        }
        $this->pingEcho($via->createConnector(), 'www.google.com', 80);
    }

    public function getHelp()
    {
        return 'ping another SOCKS proxy server via TCP handshake';
    }

    public function pingEcho(ConnectorInterface $via, $host, $port)
    {
        echo 'ping ' . $host . ':' . $port . PHP_EOL;
        return $this->ping($via, $host, $port)->then(function ($time) {
            echo 'ping test OK (⌚ ' . round($time, 3).'s)' . PHP_EOL;
            return $time;
        }, function ($error) {
            $msg = $error->getMessage();
            echo 'ping test FAILED: ' . $msg . PHP_EOL;
            throw $error;
        });
    }

    public function ping(ConnectorInterface $via, $host, $port)
    {
        $start = microtime(true);
        return $via->create($host, $port)->then(function ($stream) use ($start) {
            $stop = microtime(true);
            $stream->close();
            return ($stop - $start);
        });
    }
}
<?php

namespace Clue\Psocksd\Command;

class Status implements CommandInterface
{
    public function __construct($app)
    {

    }

    public function run($args)
    {
        echo 'status n/a' . PHP_EOL;
    }

    public function getHelp()
    {
        return 'show status';
    }
}
<?php

namespace Clue\Psocksd\Command;

use Clue\Psocksd\App;

class Help implements CommandInterface
{
    private $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function run($args)
    {
        echo 'psocksd help:' . PHP_EOL;
        $this->dumpCommands($this->app->getCommands());
    }

    public function dumpCommands($commands)
    {
        $help = array();
        foreach ($commands as $name => $command) {
            $help[$name] = $command->getHelp();
        }
        return $this->dumpHelp($help);
    }

    public function dumpHelp($help)
    {
        foreach ($help as $name => $info) {
            echo '    ' . $name . PHP_EOL .
                 '        ' . $info . PHP_EOL;
        }
    }

    public function getHelp()
    {
        return 'show this very help';
    }
}
<?php

namespace Clue\Psocksd\Command;

use Clue\Psocksd\App;

class Quit implements CommandInterface
{
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function run($args)
    {
        echo 'exiting...';
        $this->app->getLoop()->stop();
        echo PHP_EOL;
    }

    public function getHelp()
    {
        return 'shutdown this application';
    }
}
<?php

namespace Clue\Psocksd;

use Clue\React\Socks\Client;
use React\SocketClient\Connector;
use React\SocketClient\ConnectorInterface;
use ConnectionManager\Extra\Multiple\ConnectionManagerSelective;
use ConnectionManager\Extra\ConnectionManagerReject;
use \InvalidArgumentException;
use \Exception;

class App
{
    private $server;
    private $loop;
    private $resolver;
    private $via;
    private $commands;

    const PRIORITY_DEFAULT = 100;

    public function __construct()
    {
        $this->commands = array(
            'help'   => new Command\Help($this),
            'status' => new Command\Status($this),
            'via'    => new Command\Via($this),
            'ping'   => new Command\Ping($this),
            'quit'   => new Command\Quit($this)
        );
    }

    public function run()
    {
        $measureTraffic = true;
        $measureTime = true;

        $socket = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 'socks://localhost:9050';

        $settings = $this->parseSocksSocket($socket);

        if ($settings['host'] === '*') {
            $settings['host'] = '0.0.0.0';
        }


        $this->loop = $loop = \React\EventLoop\Factory::create();

        $dnsResolverFactory = new \React\Dns\Resolver\Factory();
        $this->resolver = $dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);

        $this->via = new ConnectionManagerSelective();
        $this->via->addConnectionManagerFor($this->createConnectionManager('none'), '*', '*', self::PRIORITY_DEFAULT);

        $socket = new \React\Socket\Server($loop);

        $this->server = new \Clue\React\Socks\Server($loop, $socket, $this->via);

        if (isset($settings['protocolVersion'])) {
            $this->server->setProtocolVersion($settings['protocolVersion']);
        }

        $socket->listen($settings['port'], $settings['host']);

        if (isset($settings['user']) || isset($settings['pass'])) {
            $settings += array('user' => '', 'pass' => '');
            $this->server->setAuthArray(array(
                $settings['user'] => $settings['pass']
            ));
        }

        new Option\Log($this->server);

        if ($measureTraffic) {
            new Option\MeasureTraffic($this->server);
        }

        if ($measureTime) {
            new Option\MeasureTime($this->server);
        }

        echo 'SOCKS proxy server listening on ' . $settings['host'] . ':' . $settings['port'] . PHP_EOL;

        if (defined('STDIN') && is_resource(STDIN)) {
            $that = $this;
            $loop->addReadStream(STDIN, function() use ($that) {
                $line = trim(fgets(STDIN, 4096));
                $that->onReadLine($line);
            });
        }

        $loop->run();
    }

    public function onReadLine($line)
    {
        // nothing entered => skip input
        if ($line === '') {
            return;
        }

        // TODO: properly parse command and its arguments (respect quotes, etc.)
        $args = explode(' ', $line);
        $command = array_shift($args);

        if (isset($this->commands[$command])) {
            $this->commands[$command]->run($args);
        } else {
            echo 'invalid command. type "help"?' . PHP_EOL;
        }
    }

    public function getServer()
    {
        return $this->server;
    }

    public function getResolver()
    {
        return $this->resolver;
    }

    /**
     *
     * @return React\EventLoop\LoopInterface
     */
    public function getLoop()
    {
        return $this->loop;
    }

    public function getCommands()
    {
        return $this->commands;
    }

    /**
     *
     * @param string $command
     * @return Command\CommandInterface
     * @throws Exception
     */
    public function getCommand($command)
    {
        if (!isset($this->commands[$command])) {
            throw new Exception('Invalid command given');
        }
        return $this->commands[$command];
    }

    /**
     * @return \ConnectionManager\Extra\Multiple\ConnectionManagerSelective
     */
    public function getConnectionManager()
    {
        return $this->via;
    }

    public function createConnectionManager($socket)
    {
        if ($socket === 'reject') {
            echo 'reject' . PHP_EOL;
            return new ConnectionManagerLabeled(new ConnectionManagerReject(), '-reject-');
        }
        $direct = new Connector($this->loop, $this->resolver);
        if ($socket === 'none') {
            $via = new ConnectionManagerLabeled($direct, '-direct-');

            echo 'use direct connection to target' . PHP_EOL;
        } else {
            $parsed = $this->parseSocksSocket($socket);

            // TODO: remove hack
            // resolver can not resolve 'localhost' ATM
            if ($parsed['host'] === 'localhost') {
                $parsed['host'] = '127.0.0.1';
            }

            $via = new Client($this->loop, $parsed['host'], $parsed['port'], $direct, $this->resolver);
            if (isset($parsed['protocolVersion'])) {
                try {
                    $via->setProtocolVersion($parsed['protocolVersion']);
                }
                catch (Exception $e) {
                    throw new Exception('invalid protocol version: ' . $e->getMessage());
                }
            }
            if (isset($parsed['user']) || isset($parsed['pass'])) {
                $parsed += array('user' =>'', 'pass' => '');
                try {
                    $via->setAuth($parsed['user'], $parsed['pass']);
                }
                catch (Exception $e) {
                    throw new Exception('invalid authentication info: ' . $e->getMessage());
                }
            }

            echo 'use '.$this->reverseSocksSocket($parsed) . ' as next hop';

            try {
                $via->setResolveLocal(false);
                echo ' (resolve remotely)';
            }
            catch (UnexpectedValueException $ignore) {
                // ignore in case it's not allowed (SOCKS4 client)
                echo ' (resolve locally)';
            }

            $via = new ConnectionManagerLabeled($via->createConnector(), $this->reverseSocksSocket($parsed));

            echo PHP_EOL;
        }
        return $via;
    }

    // $socket = 9050;
    // $socket = 'socks://me@localhost:9050';
    // $socket = 'localhost:9050';
    public function parseSocksSocket($socket)
    {
        // workaround parsing plain port numbers
        if (preg_match('/^\d+$/', $socket)) {
            $parts = array('port' => (int)$socket);
        } else {
            // workaround for incorrect parsing when scheme is missing
            $parts = parse_url((strpos($socket, '://') === false ? 'socks://' : '') . $socket);
            if (!$parts) {
                throw new InvalidArgumentException('Invalid/unparsable socket given');
            }
        }
        if (isset($parts['path']) || isset($parts['query']) || isset($parts['frament'])) {
            throw new InvalidArgumentException('Invalid socket given');
        }

        $parts += array('scheme' => 'socks', 'host' => 'localhost', 'port' => 9050);

        if (preg_match('/^socks(\d\w?)?$/', $parts['scheme'], $match)) {
            if (isset($match[1])) {
                $parts['protocolVersion'] = $match[1];
            }
        } else {
            throw new InvalidArgumentException('Invalid socket scheme given');
        }

        return $parts;
    }

    public function reverseSocksSocket($parts)
    {
        $ret = $parts['scheme'] . '://';
        if (isset($parts['user']) || isset($parts['pass'])) {
            $parts += array('user' => '', 'pass' => '');
            $ret .= $parts['user'] . ':' . $parts['pass'] . '@';
        }
        $ret .= $parts['host'] . ':' . $parts['port'];
        return $ret;
    }
}
<?php

namespace Clue\Psocksd\Option;

class MeasureTraffic
{
    public function __construct($server)
    {
        $server->on('connection', function(\React\Socket\Connection $client) {
            $client->on('ready', function(\React\Stream\Stream $remote) use($client) {
                $up = $down = 0;

                $client->on('data', function($data) use (&$up) {
                    $up += strlen($data);
                });

                $remote->on('data', function($data) use (&$down) {
                    $down += strlen($data);
                });

                $client->on('dump-close', function (&$dump) use (&$up, &$down) {
                    $dump .= ' (traffic: ' . $down . 'B⤓/' . $up . 'B↥)';
                });
            });
        });
    }
}
<?php

namespace Clue\Psocksd\Option;

class MeasureTime
{
    public function __construct($server)
    {
        $server->on('connection', function(\React\Socket\Connection $client) {
            $start = microtime(true);

            $client->on('dump-close', function (&$dump) use ($start) {
                $stop = microtime(true);
                $dump .= ' (⌚ ' . round($stop - $start,3).'s)';
            });
        });
    }
}
<?php

namespace Clue\Psocksd\Option;

class Log
{
    public function __construct($server)
    {
        $server->on('connection', function(\React\Socket\Connection $client) {
            $name = '#'.(int)$client->stream;
            $log = function($msg) use ($client, &$name) {
                echo date('Y-m-d H:i:s') . ' ' . $name . ' ' . $msg . PHP_EOL;
            };

            $log('connected');

            $client->on('error', function($error) use ($log) {
                $msg = $error->getMessage();
                while ($error->getPrevious() !== null) {
                    $error = $error->getPrevious();
                    $msg .= ' - ' . $error->getMessage();
                }

                $log('error: ' . $msg);
            });

            $client->on('target', function ($host, $port) use ($log) {
                $log('tunnel target: ' . $host . ':' . $port);
            });

            $client->on('auth', function($username) use ($log, &$name) {
                $name .= '(' . $username . ')';
                $log('client authenticated');
            });

            $client->on('ready', function(\React\Stream\Stream $remote) use($log) {
                $log('tunnel to remote stream #' . (int)$remote->stream . ' successfully established');
            });

            $client->on('close', function () use ($log, &$client) {
                $dump = '';
                $client->emit('dump-close', array(&$dump));

                $log('disconnected' . $dump);
            });
        });
    }
}
<?php

namespace Clue\Psocksd;

use React\SocketClient\ConnectorInterface;

class ConnectionManagerLabeled implements ConnectorInterface
{
    private $connectionManager;
    private $label;

    public function __construct(ConnectorInterface $connectionManager, $label)
    {
        $this->connectionManager = $connectionManager;
        $this->label = $label;
    }

    public function create($host, $port)
    {
        return $this->connectionManager->create($host, $port);
    }

    public function __toString()
    {
        return $this->label;
    }
}
psocksd
=======

Extensible SOCKS tunnel / proxy server daemon written in PHP

## Features

The SOCKS protocol family can be used to easily tunnel TCP connections independent
of the actual application level protocol, such as HTTP, SMTP, IMAP, Telnet, etc.
In this mode, a SOCKS server acts as a generic proxy allowing higher level application protocols to work through it.

*   SOCKS proxy server with support for SOCKS4, SOCKS4a and SOCKS5 protocol versions (all at the same time)
*   Optionally require username / password authentication (SOCKS5 only)
*   Zero configuration, easy to use command line interface (CLI) to change settings without restarting server
*   Incoming SOCKS requests can be forwarded to another SOCKS server to act as a tunnel gateway,
perform transparent protocol translation or add SOCKS authentication for clients not capable of doing it themselves.
    *   Tunnel endpoint can be changed during runtime (`via` CLI command).
    *   Particularly useful when used as an intermediary server and using ever-changing public SOCKS tunnel end points.
*   Using an async event-loop, it is capable of handling multiple concurrent connections in a non-blocking fashion
*   Built upon the shoulders of [reactphp/react](https://github.com/reactphp/react) and
[clue/socks](https://github.com/clue/socks), it uses well-tested dependencies instead of reinventing the wheel.

## Usage

Once [installed](#install), you can start `psocksd` and listen for incoming SOCKS connections by running:

```bash
$ php psocksd.phar
```

Using this command, `psocksd` will start listening on the default adress `localhost:9050`.

### Listen address

If you want to listen on another address, you can supply an explicit
listen address like this:

```bash
# start SOCKS daemon on port 9051 instead
$ php psocksd.phar 9051

# explicitly listen on the given interface
$ php psocksd.phar 192.168.1.2:9050

# listen on all interfaces (allow access to SOCKS server from the outside)
$ php psocksd.phar *:9050

# explicitly only support SOCKS5 and reject other protocol versions
$ php psocksd.phar socks5://localhost:9050

# require client to send the given authentication information
$ php psocksd.phar socks5://username:password@localhost:9051
```

### Client configuration

Once `psocksd` is started, it accepts incoming SOCKS client connections.
Therefor, you have to configure your client program (webbrowser, email client etc.) to actually use the SOCKS server.

The exact configuration depends on your program, but quite a few programs allow you to use a SOCKS proxy.
So depending on the above list address, supply the following information:

```
Proxy-Type: SOCKS4 or SOCKS5
Socks-Host: localhost
Socks-Port: 9050
```

## Install

You can grab a copy of clue/psocksd in either of the following ways.

### As a phar (recommended)

You can simply download a pre-compiled and ready-to-use version as a Phar
to any directory:

```bash
$ wget http://www.lueck.tv/psocksd/psocksd.phar
```

> If you prefer a global (system-wide) installation without having to type the `.phar` extension
each time, you may invoke:
> 
> ```bash
> $ chmod 0755 psocksd.phar
> $ sudo mv psocksd.phar /usr/local/bin/psocksd
> ```
>
> You can verify everything works by running:
> 
> ```bash
> $ psocksd
> ```

#### Updating phar

There's no separate `update` procedure, simply overwrite the existing phar with the new version downloaded.

### Manual Installation from Source

The manual way to install `psocksd` is to clone (or download) this repository
and use [composer](http://getcomposer.org) to download its dependencies.
Obviously, for this to work, you'll need PHP, git and curl installed:

```bash
$ sudo apt-get install php5-cli git curl
$ git clone https://github.com/clue/psocksd.git
$ curl -s https://getcomposer.org/installer | php
$ php composer.phar install
```

> If you want to build the above mentioned `psocksd.phar` yourself, you have
to install [clue/phar-composer](https://github.com/clue/phar-composer#install)
and can simply invoke:
>
> ```bash
> $ php phar-composer.phar build ~/workspace/psocksd
> ```

#### Updating manually

If you have followed the above install instructions, you can update `psocksd` by issuing the following two commands:

```bash
$ git pull
$ php composer.phar install
```

### Docker

This project is also available as a [docker](https://www.docker.com/) image.
Using the [clue/psocksd](https://registry.hub.docker.com/u/clue/psocksd/) image is as easy as running this:

```bash
$ docker run -d -p 9050:9050 clue/psocksd
```

## License

MIT-licensed
{
    "name": "clue/psocksd",
    "type": "project",
    "description": "Extensible SOCKS tunnel / proxy server daemon",
    "keywords": ["SOCKS proxy", "proxy server", "TCP tunnel", "server daemon"],
    "homepage": "https://github.com/clue/psocksd",
    "license": "MIT",
    "authors": [
        {
            "name": "Christian Lück",
            "email": "christian@lueck.tv"
        }
    ],
    "autoload": {
        "psr-4": {"Clue\\Psocksd\\": "src"}
    },
    "require": {
        "php": ">=5.3",
        "clue/socks-react": "~0.2.0",
        "clue/connection-manager-extra": "0.3.*",
        "react/event-loop": "0.3",
        "react/socket": "0.3",
        "react/socket-client": "0.3.*",
        "react/dns": "0.3",
        "react/stream": "0.3"
    },
    "bin": ["bin/psocksd"]
}
{
    "_readme": [
        "This file locks the dependencies of your project to a known state",
        "Read more about it at http://getcomposer.org/doc/01-basic-usage.md#composer-lock-the-lock-file",
        "This file is @generated automatically"
    ],
    "hash": "67443a9ce69487597b257c42270b1047",
    "packages": [
        {
            "name": "clue/connection-manager-extra",
            "version": "v0.3.1",
            "source": {
                "type": "git",
                "url": "https://github.com/clue/php-connection-manager-extra.git",
                "reference": "f8fc2ec784db7974e649c158c826c014296bcf01"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/clue/php-connection-manager-extra/zipball/f8fc2ec784db7974e649c158c826c014296bcf01",
                "reference": "f8fc2ec784db7974e649c158c826c014296bcf01",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3",
                "react/event-loop": "0.3.*|0.4.*",
                "react/promise": "~1.0|~2.0",
                "react/socket-client": "0.3.*|0.4.*"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "ConnectionManager\\Extra\\": "src"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@lueck.tv"
                }
            ],
            "description": "Extra decorators for creating async TCP/IP connections built upon react/socket-client",
            "homepage": "https://github.com/clue/php-connection-manager-extra",
            "keywords": [
                "Connection",
                "SocketClient",
                "acl",
                "delay",
                "firewall",
                "network",
                "random",
                "reject",
                "repeat",
                "retry",
                "timeout"
            ],
            "time": "2014-09-27 23:03:41"
        },
        {
            "name": "clue/socks-react",
            "version": "v0.2.0",
            "source": {
                "type": "git",
                "url": "https://github.com/clue/php-socks-react.git",
                "reference": "14a98b639a9f9ff45e7dda4ed4ee995ff6c46dcd"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/clue/php-socks-react/zipball/14a98b639a9f9ff45e7dda4ed4ee995ff6c46dcd",
                "reference": "14a98b639a9f9ff45e7dda4ed4ee995ff6c46dcd",
                "shasum": ""
            },
            "require": {
                "evenement/evenement": "~1.0",
                "php": ">=5.3",
                "react/dns": "0.3.*",
                "react/event-loop": "0.3.*",
                "react/promise": "~1.0",
                "react/socket": "0.3.*",
                "react/socket-client": "0.3.*",
                "react/stream": "0.3.*"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "Clue\\React\\Socks\\": "src"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@lueck.tv"
                }
            ],
            "description": "Async SOCKS proxy client and server (SOCKS4, SOCKS4a and SOCKS5)",
            "homepage": "https://github.com/clue/php-socks-react",
            "keywords": [
                "async",
                "react",
                "socks client",
                "socks protocol",
                "socks server",
                "tcp tunnel"
            ],
            "time": "2014-09-27 15:32:30"
        },
        {
            "name": "evenement/evenement",
            "version": "v1.0.0",
            "source": {
                "type": "git",
                "url": "https://github.com/igorw/evenement",
                "reference": "v1.0.0"
            },
            "dist": {
                "type": "zip",
                "url": "https://github.com/igorw/evenement/zipball/v1.0.0",
                "reference": "v1.0.0",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.0"
            },
            "type": "library",
            "autoload": {
                "psr-0": {
                    "Evenement": "src"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Igor Wiedler",
                    "email": "igor@wiedler.ch",
                    "homepage": "http://wiedler.ch/igor/"
                }
            ],
            "description": "Événement is a very simple event dispatching library for PHP 5.3",
            "keywords": [
                "event-dispatcher"
            ],
            "time": "2012-05-30 08:01:08"
        },
        {
            "name": "react/cache",
            "version": "v0.3.2",
            "target-dir": "React/Cache",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/cache.git",
                "reference": "v0.3.2"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/cache/zipball/v0.3.2",
                "reference": "v0.3.2",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.2",
                "react/promise": ">=1.0,<2.0"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\Cache": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Async caching.",
            "keywords": [
                "cache"
            ],
            "time": "2013-04-24 08:33:43"
        },
        {
            "name": "react/dns",
            "version": "v0.3.0",
            "target-dir": "React/Dns",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/dns.git",
                "reference": "3011d27e9e39f83e702b0e7e469192d36fb21205"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/dns/zipball/3011d27e9e39f83e702b0e7e469192d36fb21205",
                "reference": "3011d27e9e39f83e702b0e7e469192d36fb21205",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.2",
                "react/cache": "0.3.*",
                "react/promise": "~1.0",
                "react/socket": "0.3.*"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\Dns": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Async DNS resolver.",
            "keywords": [
                "dns",
                "dns-resolver"
            ],
            "time": "2013-01-20 19:13:14"
        },
        {
            "name": "react/event-loop",
            "version": "v0.3.0",
            "target-dir": "React/EventLoop",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/event-loop.git",
                "reference": "798a43b2e9bd3cedaf7c5de6a39e6d03e4db8e32"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/event-loop/zipball/798a43b2e9bd3cedaf7c5de6a39e6d03e4db8e32",
                "reference": "798a43b2e9bd3cedaf7c5de6a39e6d03e4db8e32",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.3"
            },
            "suggest": {
                "ext-libev": "*",
                "ext-libevent": ">=0.0.5"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\EventLoop": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Event loop abstraction layer that libraries can use for evented I/O.",
            "keywords": [
                "event-loop"
            ],
            "time": "2013-01-14 23:11:47"
        },
        {
            "name": "react/promise",
            "version": "v1.0.4",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/promise.git",
                "reference": "v1.0.4"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/promise/zipball/v1.0.4",
                "reference": "v1.0.4",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.3"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "1.0-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\Promise": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@googlemail.com",
                    "homepage": "http://sorgalla.com",
                    "role": "maintainer"
                }
            ],
            "description": "A lightweight implementation of CommonJS Promises/A for PHP",
            "time": "2013-04-03 14:05:55"
        },
        {
            "name": "react/socket",
            "version": "v0.3.0",
            "target-dir": "React/Socket",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/socket.git",
                "reference": "e549b1e39daefebc2f2290c6afdfc6ba5d12e51f"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/socket/zipball/e549b1e39daefebc2f2290c6afdfc6ba5d12e51f",
                "reference": "e549b1e39daefebc2f2290c6afdfc6ba5d12e51f",
                "shasum": ""
            },
            "require": {
                "evenement/evenement": "1.0.*",
                "php": ">=5.3.3",
                "react/event-loop": "0.3.*",
                "react/stream": "0.3.*"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\Socket": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Library for building an evented socket server.",
            "keywords": [
                "Socket"
            ],
            "time": "2013-01-21 04:20:49"
        },
        {
            "name": "react/socket-client",
            "version": "v0.3.1",
            "target-dir": "React/SocketClient",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/socket-client.git",
                "reference": "87935a0223362c36cd30cf215cbec33377d31ca4"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/socket-client/zipball/87935a0223362c36cd30cf215cbec33377d31ca4",
                "reference": "87935a0223362c36cd30cf215cbec33377d31ca4",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.3",
                "react/dns": "0.3.*",
                "react/event-loop": "0.3.*",
                "react/promise": "~1.0"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\SocketClient": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Async connector to open TCP/IP and SSL/TLS based connections.",
            "keywords": [
                "Socket"
            ],
            "time": "2013-04-20 14:55:59"
        },
        {
            "name": "react/stream",
            "version": "v0.3.0",
            "target-dir": "React/Stream",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/stream.git",
                "reference": "20cc0458ad93e8f1f00ef15408e759436ce36d68"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/stream/zipball/20cc0458ad93e8f1f00ef15408e759436ce36d68",
                "reference": "20cc0458ad93e8f1f00ef15408e759436ce36d68",
                "shasum": ""
            },
            "require": {
                "evenement/evenement": "1.0.*",
                "php": ">=5.3.3"
            },
            "suggest": {
                "react/event-loop": "0.3.*",
                "react/promise": "~1.0"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "0.3-dev"
                }
            },
            "autoload": {
                "psr-0": {
                    "React\\Stream": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "description": "Basic readable and writable stream interfaces that support piping.",
            "keywords": [
                "pipe",
                "stream"
            ],
            "time": "2013-04-14 02:10:39"
        }
    ],
    "packages-dev": [],
    "aliases": [],
    "minimum-stability": "stable",
    "stability-flags": [],
    "prefer-stable": false,
    "platform": {
        "php": ">=5.3"
    },
    "platform-dev": []
}
# CHANGELOG

This file is a manually maintained list of changes for each release. Feel free
to add your changes here when sending pull requests. Also send corrections if
you spot any mistakes.

## 0.3.4 (2014-10-22)

* Fix: Downgrade broken dependency for react/socket-client to v0.3.1
  (#16)

## 0.3.3 (2014-09-27)

* Replace [clue/socks](https://github.com/clue/php-socks) with
  [clue/socks-react](https://github.com/clue/php-socks-react)
  and fix support for faster loops (libev / libevent)
  (#13 and #14)
  
* Fix broken dependencies by updating to their latest versions
  (#15)

## 0.3.2 (2014-04-05)

* Fix: Fixed invalid reference in the `ping` command.

## 0.3.1 (2013-06-24)

* Fix: Invalid bin path (unable to run psocksd.phar)

## 0.3.0 (2013-06-24)

* Fix: Support PHP < 5.3.6
* Update clue/socks to v0.4 and clue/connection-manager-extra to v0.3.0 and 
react to v0.3.0 (#1)

## 0.2.0 (2013-06-11)

* Feature: Interactive CLI commands
* Feature: Connections can be forwarded selectively to next SOCKS proxy
* Feature: Event log with authentication, traffic and times

## 0.1.0 (2012-12-23)

* First tagged release

<?php

require __DIR__.'/../vendor/autoload.php';

$app = new Clue\Psocksd\App();
$app->run();�� ͉��Aa�m �jԶ�   GBMB