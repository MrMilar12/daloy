<?php
declare(strict_types=1);
// Dependency-free Chrome DevTools client used only by the optional visual smoke test.
final class Browser {
    private $process;
    private $socket;
    private int $id=0;
    public function __construct(string $chrome,string $directory) {
        $port=random_int(25000,28000);
        $this->process=proc_open([$chrome,'--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-extensions','--remote-allow-origins=*','--remote-debugging-port='.$port,'--user-data-dir='.$directory,'about:blank'],[0=>['pipe','r'],1=>['file',$directory.'.log','a'],2=>['file',$directory.'.log','a']],$pipes);
        if(!is_resource($this->process)) throw new RuntimeException('Unable to launch Chrome.'); fclose($pipes[0]);
        $pages=null;
        for($i=0;$i<100;$i++) { $curl=curl_init('http://127.0.0.1:'.$port.'/json/list'); curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT_MS=>100,CURLOPT_TIMEOUT_MS=>200]); $json=curl_exec($curl); curl_close($curl); if($json) { $pages=json_decode($json,true); if(!empty($pages[0]['webSocketDebuggerUrl'])) break; } usleep(100000); }
        if(empty($pages[0]['webSocketDebuggerUrl'])) throw new RuntimeException('Chrome debugging endpoint did not become ready.');
        $url=parse_url($pages[0]['webSocketDebuggerUrl']); $this->socket=fsockopen($url['host'],$url['port'],$errno,$error,10); stream_set_timeout($this->socket,15);
        $key=base64_encode(random_bytes(16)); fwrite($this->socket,'GET '.$url['path']." HTTP/1.1\r\nHost: ".$url['host'].':'.$url['port']."\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n");
        $header=''; while(!str_ends_with($header,"\r\n\r\n")) { $byte=fread($this->socket,1); if($byte==='') throw new RuntimeException('WebSocket handshake failed.'); $header.=$byte; }
        if(!str_contains($header,'101')) throw new RuntimeException('Chrome rejected WebSocket connection.');
        $this->command('Page.enable'); $this->command('Runtime.enable');
    }
    private function read(int $length): string { $data=''; while(strlen($data)<$length) { $chunk=fread($this->socket,$length-strlen($data)); if($chunk==='' || $chunk===false) throw new RuntimeException('Chrome connection timed out.'); $data.=$chunk; } return $data; }
    public function command(string $method,array $params=[]): array {
        $id=++$this->id; $payload=json_encode(['id'=>$id,'method'=>$method,'params'=>$params?:new stdClass()],JSON_THROW_ON_ERROR); $length=strlen($payload); $mask=random_bytes(4);
        $header=chr(0x81).($length<126?chr(0x80|$length):chr(0x80|126).pack('n',$length)); $encoded=''; for($i=0;$i<$length;$i++) $encoded.=$payload[$i]^$mask[$i%4]; fwrite($this->socket,$header.$mask.$encoded);
        $message='';
        while(true) {
            $frame=$this->read(2); $final=(ord($frame[0])&128)!==0; $opcode=ord($frame[0])&15;
            if($opcode===8) throw new RuntimeException('Chrome closed the WebSocket.');
            $len=ord($frame[1])&127; if($len===126) $len=unpack('n',$this->read(2))[1]; elseif($len===127) { $wide=unpack('N2',$this->read(8)); $len=$wide[1]*4294967296+$wide[2]; }
            if($len>30*1024*1024) throw new RuntimeException('Unexpectedly large Chrome frame.');
            $message.=$this->read((int)$len); if(!$final) continue;
            $data=json_decode($message,true); $message=''; if(($data['id']??null)===$id) { if(isset($data['error'])) throw new RuntimeException(json_encode($data['error'])); return $data['result']??[]; }
        }
    }
    public function evaluate(string $js): mixed { $r=$this->command('Runtime.evaluate',['expression'=>$js,'returnByValue'=>true,'awaitPromise'=>true]); if(isset($r['exceptionDetails'])) throw new RuntimeException(json_encode($r['exceptionDetails'])); return $r['result']['value']??null; }
    public function click(string $selector): void {
        $position=$this->evaluate('(() => { const el=document.querySelector('.json_encode($selector).'); if(!el) throw new Error("Element not found"); el.scrollIntoView({block:"center"}); const r=el.getBoundingClientRect(); return {x:r.x+r.width/2,y:r.y+r.height/2}; })()');
        $params=$position+['button'=>'left','clickCount'=>1];
        $this->command('Input.dispatchMouseEvent',$params+['type'=>'mousePressed']);
        $this->command('Input.dispatchMouseEvent',$params+['type'=>'mouseReleased']);
    }
    public function navigate(string $url): void { $this->command('Page.navigate',['url'=>$url]); $this->wait('location.href === '.json_encode($url).' && document.readyState === "complete"'); }
    public function wait(string $expression): void { for($i=0;$i<80;$i++) { usleep(100000); if($this->evaluate($expression)) return; } throw new RuntimeException('Browser condition timed out: '.$expression); }
    public function screenshot(string $file,int $width,int $height): void {
        $this->command('Emulation.setDeviceMetricsOverride',['width'=>$width,'height'=>$height,'deviceScaleFactor'=>1,'mobile'=>$width<600]); usleep(250000);
        $result=$this->command('Page.captureScreenshot',['format'=>'png','captureBeyondViewport'=>false]); file_put_contents($file,base64_decode($result['data']));
    }
    public function close(): void {
        if(is_resource($this->socket)) { try { $this->command('Browser.close'); } catch(Throwable $e) {} fclose($this->socket); $this->socket=null; }
        if(is_resource($this->process)) { proc_terminate($this->process); proc_close($this->process); $this->process=null; }
    }
    public function __destruct() { $this->close(); }
}
