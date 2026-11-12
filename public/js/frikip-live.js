/* FootPro – lives des membres : envoi (WHIP) et lecture (WHEP) en WebRTC, avec Cloudflare Stream. */
(function () {
  var ICE = [{ urls: 'stun:stun.cloudflare.com:3478' }];
  function iceDone(pc) {
    return new Promise(function (res) {
      if (pc.iceGatheringState === 'complete') return res();
      var t = setTimeout(res, 2500);
      pc.addEventListener('icegatheringstatechange', function () { if (pc.iceGatheringState === 'complete') { clearTimeout(t); res(); } });
    });
  }
  async function exchange(pc, url) {
    var offer = await pc.createOffer(); await pc.setLocalDescription(offer); await iceDone(pc);
    var r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/sdp' }, body: pc.localDescription.sdp });
    if (!r.ok) { var e = new Error('HTTP ' + r.status); e.status = r.status; throw e; }
    await pc.setRemoteDescription({ type: 'answer', sdp: await r.text() });
    var loc = r.headers.get('Location');
    return loc ? new URL(loc, url).toString() : null;
  }
  /** Envoie la caméra et le micro. @return {pc, stop()} */
  async function whip(url, stream) {
    var pc = new RTCPeerConnection({ iceServers: ICE, bundlePolicy: 'max-bundle' });
    stream.getTracks().forEach(function (t) { pc.addTransceiver(t, { direction: 'sendonly' }); });
    var resource = await exchange(pc, url);
    return { pc: pc, stop: function () { try { pc.close(); } catch (e) {} if (resource) fetch(resource, { method: 'DELETE' }).catch(function () {}); } };
  }
  /** Lit un live dans <video>. Réessaie toutes les 5 s tant que le live n'a pas commencé. */
  function whep(url, video, onState) {
    var stopped = false, pc = null;
    async function attempt() {
      if (stopped) return;
      pc = new RTCPeerConnection({ iceServers: ICE, bundlePolicy: 'max-bundle' });
      pc.addTransceiver('video', { direction: 'recvonly' }); pc.addTransceiver('audio', { direction: 'recvonly' });
      var ms = new MediaStream(); video.srcObject = ms;
      pc.ontrack = function (e) { ms.addTrack(e.track); };
      pc.onconnectionstatechange = function () {
        if (onState) onState(pc.connectionState);
        if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') { try { pc.close(); } catch (e) {} setTimeout(attempt, 4000); }
      };
      try { await exchange(pc, url); video.play().catch(function () {}); }
      catch (e) { try { pc.close(); } catch (x) {} if (onState) onState('waiting'); setTimeout(attempt, 5000); }
    }
    attempt();
    return { stop: function () { stopped = true; if (pc) try { pc.close(); } catch (e) {} } };
  }
  window.FrikipLive = { whip: whip, whep: whep };
})();
