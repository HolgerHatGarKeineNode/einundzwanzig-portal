/*!
 * nostr-mill — Multi-Interface Login Layer
 * https://github.com/0ceanslim/nostr-mill
 * MIT License
 */
(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
  typeof define === 'function' && define.amd ? define(factory) :
  (global = typeof globalThis !== 'undefined' ? globalThis : global || self, global.MILL = factory());
})(this, (function () { 'use strict';

  /**
   * MILL — themes.js
   * CSS custom property theming system.
   * Consumers can pass a partial theme object and only override what they need.
   */


  /** Built-in themes */
  const THEMES = {

    dark: {
      '--mill-bg':             '#09080f',
      '--mill-surface':        '#100e1b',
      '--mill-card':           '#181528',
      '--mill-card-hover':     '#1f1c35',
      '--mill-inset':          'rgba(0,0,0,0.25)',
      '--mill-inset-strong':   'rgba(0,0,0,0.35)',
      '--mill-overlay':        'rgba(4,3,10,0.78)',
      '--mill-border':         '#2a2544',
      '--mill-border-light':   '#3e3860',
      '--mill-accent':         'oklch(0.67 0.28 282)',
      '--mill-accent-hover':   'oklch(0.73 0.28 282)',
      '--mill-accent-dim':     'oklch(0.67 0.28 282 / 0.13)',
      '--mill-teal':           'oklch(0.67 0.18 195)',
      '--mill-teal-dim':       'oklch(0.67 0.18 195 / 0.13)',
      '--mill-text':           '#ede8fc',
      '--mill-text-secondary': '#9d94c0',
      '--mill-muted':          '#5e5880',
      '--mill-danger':         'oklch(0.65 0.24 15)',
      '--mill-danger-dim':     'oklch(0.65 0.24 15 / 0.13)',
      '--mill-warning':        'oklch(0.78 0.18 65)',
      '--mill-warning-dim':    'oklch(0.78 0.18 65 / 0.13)',
      '--mill-success':        'oklch(0.7 0.2 155)',
      '--mill-success-dim':    'oklch(0.7 0.2 155 / 0.13)',
      '--mill-radius':         '14px',
      '--mill-border-width':   '1px',
      '--mill-border-style':   'solid',
      '--mill-shadow':         '0 0 0 1px rgba(130,80,255,0.08), 0 24px 64px rgba(0,0,0,0.7), 0 0 80px oklch(0.67 0.28 282 / 0.06)',
      '--mill-font':           "'Space Grotesk', 'DM Sans', system-ui, sans-serif",
      '--mill-font-mono':      "'JetBrains Mono', 'Fira Code', monospace",
    },

    light: {
      '--mill-bg':             '#f5f3ff',
      '--mill-surface':        '#ffffff',
      '--mill-card':           '#f8f7fe',
      '--mill-card-hover':     '#efedfb',
      '--mill-inset':          'rgba(20,14,40,0.05)',
      '--mill-inset-strong':   'rgba(20,14,40,0.08)',
      '--mill-overlay':        'rgba(60,40,120,0.30)',
      '--mill-border':         '#ddd9f5',
      '--mill-border-light':   '#c5c0e8',
      '--mill-accent':         'oklch(0.5 0.25 282)',
      '--mill-accent-hover':   'oklch(0.44 0.25 282)',
      '--mill-accent-dim':     'oklch(0.5 0.25 282 / 0.10)',
      '--mill-teal':           'oklch(0.45 0.18 195)',
      '--mill-teal-dim':       'oklch(0.45 0.18 195 / 0.10)',
      '--mill-text':           '#1a1630',
      '--mill-text-secondary': '#534d7a',
      '--mill-muted':          '#9990bb',
      '--mill-danger':         'oklch(0.5 0.24 15)',
      '--mill-danger-dim':     'oklch(0.5 0.24 15 / 0.10)',
      '--mill-warning':        'oklch(0.55 0.18 65)',
      '--mill-warning-dim':    'oklch(0.55 0.18 65 / 0.10)',
      '--mill-success':        'oklch(0.45 0.2 155)',
      '--mill-success-dim':    'oklch(0.45 0.2 155 / 0.10)',
      '--mill-radius':         '14px',
      '--mill-border-width':   '1px',
      '--mill-border-style':   'solid',
      '--mill-shadow':         '0 8px 32px rgba(60,40,120,0.18)',
      '--mill-font':           "'Space Grotesk', 'DM Sans', system-ui, sans-serif",
      '--mill-font-mono':      "'JetBrains Mono', 'Fira Code', monospace",
    },

    minimal: {
      '--mill-bg':             '#fafafa',
      '--mill-surface':        '#ffffff',
      '--mill-card':           '#f4f4f4',
      '--mill-card-hover':     '#ececec',
      '--mill-inset':          'rgba(0,0,0,0.04)',
      '--mill-inset-strong':   'rgba(0,0,0,0.07)',
      '--mill-overlay':        'rgba(0,0,0,0.45)',
      '--mill-border':         '#e2e2e2',
      '--mill-border-light':   '#cacaca',
      '--mill-accent':         '#111111',
      '--mill-accent-hover':   '#333333',
      '--mill-accent-dim':     'rgba(0,0,0,0.07)',
      '--mill-teal':           '#555555',
      '--mill-teal-dim':       'rgba(0,0,0,0.07)',
      '--mill-text':           '#111111',
      '--mill-text-secondary': '#555555',
      '--mill-muted':          '#aaaaaa',
      '--mill-danger':         '#cc2222',
      '--mill-danger-dim':     'rgba(204,34,34,0.08)',
      '--mill-warning':        '#c07700',
      '--mill-warning-dim':    'rgba(192,119,0,0.08)',
      '--mill-success':        '#1a8a4a',
      '--mill-success-dim':    'rgba(26,138,74,0.08)',
      '--mill-radius':         '8px',
      '--mill-border-width':   '1px',
      '--mill-border-style':   'solid',
      '--mill-shadow':         '0 8px 24px rgba(0,0,0,0.10)',
      '--mill-font':           "'Inter', system-ui, sans-serif",
      '--mill-font-mono':      "'IBM Plex Mono', monospace",
    },

    // Branded theme — green/grain
    grain: {
      '--mill-bg':             '#0d0f0c',
      '--mill-surface':        '#141710',
      '--mill-card':           '#1a1f16',
      '--mill-card-hover':     '#222820',
      '--mill-inset':          'rgba(0,0,0,0.25)',
      '--mill-inset-strong':   'rgba(0,0,0,0.35)',
      '--mill-overlay':        'rgba(8,12,6,0.78)',
      '--mill-border':         '#2c3226',
      '--mill-border-light':   '#3d4736',
      '--mill-accent':         'oklch(0.67 0.22 142)',   /* green */
      '--mill-accent-hover':   'oklch(0.73 0.22 142)',
      '--mill-accent-dim':     'oklch(0.67 0.22 142 / 0.13)',
      '--mill-teal':           'oklch(0.67 0.18 175)',
      '--mill-teal-dim':       'oklch(0.67 0.18 175 / 0.13)',
      '--mill-text':           '#e8f0e4',
      '--mill-text-secondary': '#8ea882',
      '--mill-muted':          '#506048',
      '--mill-danger':         'oklch(0.65 0.22 25)',
      '--mill-danger-dim':     'oklch(0.65 0.22 25 / 0.13)',
      '--mill-warning':        'oklch(0.78 0.18 80)',
      '--mill-warning-dim':    'oklch(0.78 0.18 80 / 0.13)',
      '--mill-success':        'oklch(0.7 0.22 142)',
      '--mill-success-dim':    'oklch(0.7 0.22 142 / 0.13)',
      '--mill-radius':         '12px',
      '--mill-border-width':   '1px',
      '--mill-border-style':   'solid',
      '--mill-shadow':         '0 0 0 1px rgba(120,200,140,0.10), 0 24px 64px rgba(0,0,0,0.7), 0 0 80px oklch(0.7 0.22 142 / 0.06)',
      '--mill-font':           "'Space Grotesk', system-ui, sans-serif",
      '--mill-font-mono':      "'JetBrains Mono', monospace",
    },
  };

  /**
   * Apply a theme to a target element (defaults to :host of shadow DOM or document.documentElement).
   * @param {string|object} theme  - Built-in name ('dark','light','minimal','grain') or partial token object
   * @param {HTMLElement}   target - Where to apply CSS vars (default: document.documentElement)
   */
  function applyTheme(theme, target = document.documentElement) {
    const tokens = typeof theme === 'string'
      ? THEMES[theme] ?? THEMES.dark
      : { ...THEMES.dark, ...theme };           // merge partials onto dark baseline

    for (const [prop, val] of Object.entries(tokens)) {
      target.style.setProperty(prop, val);
    }
  }

  /**
   * Generate a minimal theme from just a few brand inputs.
   * @param {{ accent: string, bg?: string, radius?: string, font?: string }} opts
   */
  function brandTheme({ accent, bg, radius, font } = {}) {
    return {
      ...THEMES.dark,
      ...(accent ? {
        '--mill-accent':       accent,
        '--mill-accent-hover': accent,        // caller can fine-tune
        '--mill-accent-dim':   accent + '22', // rough alpha — works for hex
        '--mill-success':      accent,
        '--mill-success-dim':  accent + '22',
      } : {}),
      ...(bg     ? { '--mill-bg': bg, '--mill-surface': bg } : {}),
      ...(radius ? { '--mill-radius': radius } : {}),
      ...(font   ? { '--mill-font': font } : {}),
    };
  }

  /**
   * Utilities for hex, bytes, CSPRNG.
   * @module
   */
  /*! noble-hashes - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  /** Checks if something is Uint8Array. Be careful: nodejs Buffer will return true. */
  function isBytes$3(a) {
      return a instanceof Uint8Array || (ArrayBuffer.isView(a) && a.constructor.name === 'Uint8Array');
  }
  /** Asserts something is positive integer. */
  function anumber$3(n, title = '') {
      if (!Number.isSafeInteger(n) || n < 0) {
          const prefix = title && `"${title}" `;
          throw new Error(`${prefix}expected integer >= 0, got ${n}`);
      }
  }
  /** Asserts something is Uint8Array. */
  function abytes$3(value, length, title = '') {
      const bytes = isBytes$3(value);
      const len = value?.length;
      const needsLen = length !== undefined;
      if (!bytes || (needsLen && len !== length)) {
          const prefix = title && `"${title}" `;
          const ofLen = needsLen ? ` of length ${length}` : '';
          const got = bytes ? `length=${len}` : `type=${typeof value}`;
          throw new Error(prefix + 'expected Uint8Array' + ofLen + ', got ' + got);
      }
      return value;
  }
  /** Asserts something is hash */
  function ahash$1(h) {
      if (typeof h !== 'function' || typeof h.create !== 'function')
          throw new Error('Hash must wrapped by utils.createHasher');
      anumber$3(h.outputLen);
      anumber$3(h.blockLen);
  }
  /** Asserts a hash instance has not been destroyed / finished */
  function aexists$2(instance, checkFinished = true) {
      if (instance.destroyed)
          throw new Error('Hash instance has been destroyed');
      if (checkFinished && instance.finished)
          throw new Error('Hash#digest() has already been called');
  }
  /** Asserts output is properly-sized byte array */
  function aoutput$2(out, instance) {
      abytes$3(out, undefined, 'digestInto() output');
      const min = instance.outputLen;
      if (out.length < min) {
          throw new Error('"digestInto() output" expected to be of length >=' + min);
      }
  }
  /** Cast u8 / u16 / u32 to u8. */
  function u8(arr) {
      return new Uint8Array(arr.buffer, arr.byteOffset, arr.byteLength);
  }
  /** Cast u8 / u16 / u32 to u32. */
  function u32$1(arr) {
      return new Uint32Array(arr.buffer, arr.byteOffset, Math.floor(arr.byteLength / 4));
  }
  /** Zeroize a byte array. Warning: JS provides no guarantees. */
  function clean$2(...arrays) {
      for (let i = 0; i < arrays.length; i++) {
          arrays[i].fill(0);
      }
  }
  /** Create DataView of an array for easy byte-level manipulation. */
  function createView$4(arr) {
      return new DataView(arr.buffer, arr.byteOffset, arr.byteLength);
  }
  /** The rotate right (circular right shift) operation for uint32 */
  function rotr$3(word, shift) {
      return (word << (32 - shift)) | (word >>> shift);
  }
  /** The rotate left (circular left shift) operation for uint32 */
  function rotl$1(word, shift) {
      return (word << shift) | ((word >>> (32 - shift)) >>> 0);
  }
  /** Is current platform little-endian? Most are. Big-Endian platform: IBM */
  const isLE$3 = /* @__PURE__ */ (() => new Uint8Array(new Uint32Array([0x11223344]).buffer)[0] === 0x44)();
  /** The byte swap operation for uint32 */
  function byteSwap(word) {
      return (((word << 24) & 0xff000000) |
          ((word << 8) & 0xff0000) |
          ((word >>> 8) & 0xff00) |
          ((word >>> 24) & 0xff));
  }
  /** Conditionally byte swap if on a big-endian platform */
  const swap8IfBE = isLE$3
      ? (n) => n
      : (n) => byteSwap(n);
  /** In place byte swap for Uint32Array */
  function byteSwap32(arr) {
      for (let i = 0; i < arr.length; i++) {
          arr[i] = byteSwap(arr[i]);
      }
      return arr;
  }
  const swap32IfBE = isLE$3
      ? (u) => u
      : byteSwap32;
  // Built-in hex conversion https://caniuse.com/mdn-javascript_builtins_uint8array_fromhex
  const hasHexBuiltin$1 = /* @__PURE__ */ (() => 
  // @ts-ignore
  typeof Uint8Array.from([]).toHex === 'function' && typeof Uint8Array.fromHex === 'function')();
  // Array where index 0xf0 (240) is mapped to string 'f0'
  const hexes$3 = /* @__PURE__ */ Array.from({ length: 256 }, (_, i) => i.toString(16).padStart(2, '0'));
  /**
   * Convert byte array to hex string. Uses built-in function, when available.
   * @example bytesToHex(Uint8Array.from([0xca, 0xfe, 0x01, 0x23])) // 'cafe0123'
   */
  function bytesToHex$5(bytes) {
      abytes$3(bytes);
      // @ts-ignore
      if (hasHexBuiltin$1)
          return bytes.toHex();
      // pre-caching improves the speed 6x
      let hex = '';
      for (let i = 0; i < bytes.length; i++) {
          hex += hexes$3[bytes[i]];
      }
      return hex;
  }
  // We use optimized technique to convert hex string to byte array
  const asciis$1 = { _0: 48, _9: 57, A: 65, F: 70, a: 97, f: 102 };
  function asciiToBase16$1(ch) {
      if (ch >= asciis$1._0 && ch <= asciis$1._9)
          return ch - asciis$1._0; // '2' => 50-48
      if (ch >= asciis$1.A && ch <= asciis$1.F)
          return ch - (asciis$1.A - 10); // 'B' => 66-(65-10)
      if (ch >= asciis$1.a && ch <= asciis$1.f)
          return ch - (asciis$1.a - 10); // 'b' => 98-(97-10)
      return;
  }
  /**
   * Convert hex string to byte array. Uses built-in function, when available.
   * @example hexToBytes('cafe0123') // Uint8Array.from([0xca, 0xfe, 0x01, 0x23])
   */
  function hexToBytes$3(hex) {
      if (typeof hex !== 'string')
          throw new Error('hex string expected, got ' + typeof hex);
      // @ts-ignore
      if (hasHexBuiltin$1)
          return Uint8Array.fromHex(hex);
      const hl = hex.length;
      const al = hl / 2;
      if (hl % 2)
          throw new Error('hex string expected, got unpadded hex of length ' + hl);
      const array = new Uint8Array(al);
      for (let ai = 0, hi = 0; ai < al; ai++, hi += 2) {
          const n1 = asciiToBase16$1(hex.charCodeAt(hi));
          const n2 = asciiToBase16$1(hex.charCodeAt(hi + 1));
          if (n1 === undefined || n2 === undefined) {
              const char = hex[hi] + hex[hi + 1];
              throw new Error('hex string expected, got non-hex character "' + char + '" at index ' + hi);
          }
          array[ai] = n1 * 16 + n2; // multiply first octet, e.g. 'a3' => 10*16+3 => 160 + 3 => 163
      }
      return array;
  }
  /**
   * Converts string to bytes using UTF8 encoding.
   * Built-in doesn't validate input to be string: we do the check.
   * @example utf8ToBytes('abc') // Uint8Array.from([97, 98, 99])
   */
  function utf8ToBytes$3(str) {
      if (typeof str !== 'string')
          throw new Error('string expected');
      return new Uint8Array(new TextEncoder().encode(str)); // https://bugzil.la/1681809
  }
  /**
   * Helper for KDFs: consumes uint8array or string.
   * When string is passed, does utf8 decoding, using TextDecoder.
   */
  function kdfInputToBytes(data, errorTitle = '') {
      if (typeof data === 'string')
          return utf8ToBytes$3(data);
      return abytes$3(data, undefined, errorTitle);
  }
  /** Copies several Uint8Arrays into one. */
  function concatBytes$3(...arrays) {
      let sum = 0;
      for (let i = 0; i < arrays.length; i++) {
          const a = arrays[i];
          abytes$3(a);
          sum += a.length;
      }
      const res = new Uint8Array(sum);
      for (let i = 0, pad = 0; i < arrays.length; i++) {
          const a = arrays[i];
          res.set(a, pad);
          pad += a.length;
      }
      return res;
  }
  /** Merges default options and passed options. */
  function checkOpts$1(defaults, opts) {
      if (opts !== undefined && {}.toString.call(opts) !== '[object Object]')
          throw new Error('options must be object or undefined');
      const merged = Object.assign(defaults, opts);
      return merged;
  }
  /** Creates function with outputLen, blockLen, create properties from a class constructor. */
  function createHasher$1(hashCons, info = {}) {
      const hashC = (msg, opts) => hashCons(opts).update(msg).digest();
      const tmp = hashCons(undefined);
      hashC.outputLen = tmp.outputLen;
      hashC.blockLen = tmp.blockLen;
      hashC.create = (opts) => hashCons(opts);
      Object.assign(hashC, info);
      return Object.freeze(hashC);
  }
  /** Cryptographically secure PRNG. Uses internal OS-level `crypto.getRandomValues`. */
  function randomBytes$2(bytesLength = 32) {
      const cr = typeof globalThis === 'object' ? globalThis.crypto : null;
      if (typeof cr?.getRandomValues !== 'function')
          throw new Error('crypto.getRandomValues must be defined');
      return cr.getRandomValues(new Uint8Array(bytesLength));
  }
  /** Creates OID opts for NIST hashes, with prefix 06 09 60 86 48 01 65 03 04 02. */
  const oidNist = (suffix) => ({
      oid: Uint8Array.from([0x06, 0x09, 0x60, 0x86, 0x48, 0x01, 0x65, 0x03, 0x04, 0x02, suffix]),
  });

  /**
   * Internal Merkle-Damgard hash utils.
   * @module
   */
  /** Choice: a ? b : c */
  function Chi$3(a, b, c) {
      return (a & b) ^ (~a & c);
  }
  /** Majority function, true if any two inputs is true. */
  function Maj$3(a, b, c) {
      return (a & b) ^ (a & c) ^ (b & c);
  }
  /**
   * Merkle-Damgard hash construction base class.
   * Could be used to create MD5, RIPEMD, SHA1, SHA2.
   */
  let HashMD$1 = class HashMD {
      blockLen;
      outputLen;
      padOffset;
      isLE;
      // For partial updates less than block size
      buffer;
      view;
      finished = false;
      length = 0;
      pos = 0;
      destroyed = false;
      constructor(blockLen, outputLen, padOffset, isLE) {
          this.blockLen = blockLen;
          this.outputLen = outputLen;
          this.padOffset = padOffset;
          this.isLE = isLE;
          this.buffer = new Uint8Array(blockLen);
          this.view = createView$4(this.buffer);
      }
      update(data) {
          aexists$2(this);
          abytes$3(data);
          const { view, buffer, blockLen } = this;
          const len = data.length;
          for (let pos = 0; pos < len;) {
              const take = Math.min(blockLen - this.pos, len - pos);
              // Fast path: we have at least one block in input, cast it to view and process
              if (take === blockLen) {
                  const dataView = createView$4(data);
                  for (; blockLen <= len - pos; pos += blockLen)
                      this.process(dataView, pos);
                  continue;
              }
              buffer.set(data.subarray(pos, pos + take), this.pos);
              this.pos += take;
              pos += take;
              if (this.pos === blockLen) {
                  this.process(view, 0);
                  this.pos = 0;
              }
          }
          this.length += data.length;
          this.roundClean();
          return this;
      }
      digestInto(out) {
          aexists$2(this);
          aoutput$2(out, this);
          this.finished = true;
          // Padding
          // We can avoid allocation of buffer for padding completely if it
          // was previously not allocated here. But it won't change performance.
          const { buffer, view, blockLen, isLE } = this;
          let { pos } = this;
          // append the bit '1' to the message
          buffer[pos++] = 0b10000000;
          clean$2(this.buffer.subarray(pos));
          // we have less than padOffset left in buffer, so we cannot put length in
          // current block, need process it and pad again
          if (this.padOffset > blockLen - pos) {
              this.process(view, 0);
              pos = 0;
          }
          // Pad until full block byte with zeros
          for (let i = pos; i < blockLen; i++)
              buffer[i] = 0;
          // Note: sha512 requires length to be 128bit integer, but length in JS will overflow before that
          // You need to write around 2 exabytes (u64_max / 8 / (1024**6)) for this to happen.
          // So we just write lowest 64 bits of that value.
          view.setBigUint64(blockLen - 8, BigInt(this.length * 8), isLE);
          this.process(view, 0);
          const oview = createView$4(out);
          const len = this.outputLen;
          // NOTE: we do division by 4 later, which must be fused in single op with modulo by JIT
          if (len % 4)
              throw new Error('_sha2: outputLen must be aligned to 32bit');
          const outLen = len / 4;
          const state = this.get();
          if (outLen > state.length)
              throw new Error('_sha2: outputLen bigger than state');
          for (let i = 0; i < outLen; i++)
              oview.setUint32(4 * i, state[i], isLE);
      }
      digest() {
          const { buffer, outputLen } = this;
          this.digestInto(buffer);
          const res = buffer.slice(0, outputLen);
          this.destroy();
          return res;
      }
      _cloneInto(to) {
          to ||= new this.constructor();
          to.set(...this.get());
          const { blockLen, buffer, length, finished, destroyed, pos } = this;
          to.destroyed = destroyed;
          to.finished = finished;
          to.length = length;
          to.pos = pos;
          if (length % blockLen)
              to.buffer.set(buffer);
          return to;
      }
      clone() {
          return this._cloneInto();
      }
  };
  /**
   * Initial SHA-2 state: fractional parts of square roots of first 16 primes 2..53.
   * Check out `test/misc/sha2-gen-iv.js` for recomputation guide.
   */
  /** Initial SHA256 state. Bits 0..32 of frac part of sqrt of primes 2..19 */
  const SHA256_IV$1 = /* @__PURE__ */ Uint32Array.from([
      0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19,
  ]);

  /**
   * Internal helpers for u64. BigUint64Array is too slow as per 2025, so we implement it using Uint32Array.
   * @todo re-check https://issues.chromium.org/issues/42212588
   * @module
   */
  const U32_MASK64 = /* @__PURE__ */ BigInt(2 ** 32 - 1);
  const _32n = /* @__PURE__ */ BigInt(32);
  function fromBig(n, le = false) {
      if (le)
          return { h: Number(n & U32_MASK64), l: Number((n >> _32n) & U32_MASK64) };
      return { h: Number((n >> _32n) & U32_MASK64) | 0, l: Number(n & U32_MASK64) | 0 };
  }
  // Right rotate for Shift in [1, 32)
  const rotrSH = (h, l, s) => (h >>> s) | (l << (32 - s));
  const rotrSL = (h, l, s) => (h << (32 - s)) | (l >>> s);
  // Right rotate for Shift in (32, 64), NOTE: 32 is special case.
  const rotrBH = (h, l, s) => (h << (64 - s)) | (l >>> (s - 32));
  const rotrBL = (h, l, s) => (h >>> (s - 32)) | (l << (64 - s));
  // Right rotate for shift===32 (just swaps l&h)
  const rotr32H = (_h, l) => l;
  const rotr32L = (h, _l) => h;
  // JS uses 32-bit signed integers for bitwise operations which means we cannot
  // simple take carry out of low bit sum by shift, we need to use division.
  function add(Ah, Al, Bh, Bl) {
      const l = (Al >>> 0) + (Bl >>> 0);
      return { h: (Ah + Bh + ((l / 2 ** 32) | 0)) | 0, l: l | 0 };
  }
  // Addition with more than 2 elements
  const add3L = (Al, Bl, Cl) => (Al >>> 0) + (Bl >>> 0) + (Cl >>> 0);
  const add3H = (low, Ah, Bh, Ch) => (Ah + Bh + Ch + ((low / 2 ** 32) | 0)) | 0;

  /**
   * SHA2 hash function. A.k.a. sha256, sha384, sha512, sha512_224, sha512_256.
   * SHA256 is the fastest hash implementable in JS, even faster than Blake3.
   * Check out [RFC 4634](https://www.rfc-editor.org/rfc/rfc4634) and
   * [FIPS 180-4](https://nvlpubs.nist.gov/nistpubs/FIPS/NIST.FIPS.180-4.pdf).
   * @module
   */
  /**
   * Round constants:
   * First 32 bits of fractional parts of the cube roots of the first 64 primes 2..311)
   */
  // prettier-ignore
  const SHA256_K$3 = /* @__PURE__ */ Uint32Array.from([
      0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
      0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
      0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
      0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
      0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
      0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
      0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
      0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
  ]);
  /** Reusable temporary buffer. "W" comes straight from spec. */
  const SHA256_W$3 = /* @__PURE__ */ new Uint32Array(64);
  /** Internal 32-byte base SHA2 hash class. */
  class SHA2_32B extends HashMD$1 {
      constructor(outputLen) {
          super(64, outputLen, 8, false);
      }
      get() {
          const { A, B, C, D, E, F, G, H } = this;
          return [A, B, C, D, E, F, G, H];
      }
      // prettier-ignore
      set(A, B, C, D, E, F, G, H) {
          this.A = A | 0;
          this.B = B | 0;
          this.C = C | 0;
          this.D = D | 0;
          this.E = E | 0;
          this.F = F | 0;
          this.G = G | 0;
          this.H = H | 0;
      }
      process(view, offset) {
          // Extend the first 16 words into the remaining 48 words w[16..63] of the message schedule array
          for (let i = 0; i < 16; i++, offset += 4)
              SHA256_W$3[i] = view.getUint32(offset, false);
          for (let i = 16; i < 64; i++) {
              const W15 = SHA256_W$3[i - 15];
              const W2 = SHA256_W$3[i - 2];
              const s0 = rotr$3(W15, 7) ^ rotr$3(W15, 18) ^ (W15 >>> 3);
              const s1 = rotr$3(W2, 17) ^ rotr$3(W2, 19) ^ (W2 >>> 10);
              SHA256_W$3[i] = (s1 + SHA256_W$3[i - 7] + s0 + SHA256_W$3[i - 16]) | 0;
          }
          // Compression function main loop, 64 rounds
          let { A, B, C, D, E, F, G, H } = this;
          for (let i = 0; i < 64; i++) {
              const sigma1 = rotr$3(E, 6) ^ rotr$3(E, 11) ^ rotr$3(E, 25);
              const T1 = (H + sigma1 + Chi$3(E, F, G) + SHA256_K$3[i] + SHA256_W$3[i]) | 0;
              const sigma0 = rotr$3(A, 2) ^ rotr$3(A, 13) ^ rotr$3(A, 22);
              const T2 = (sigma0 + Maj$3(A, B, C)) | 0;
              H = G;
              G = F;
              F = E;
              E = (D + T1) | 0;
              D = C;
              C = B;
              B = A;
              A = (T1 + T2) | 0;
          }
          // Add the compressed chunk to the current hash value
          A = (A + this.A) | 0;
          B = (B + this.B) | 0;
          C = (C + this.C) | 0;
          D = (D + this.D) | 0;
          E = (E + this.E) | 0;
          F = (F + this.F) | 0;
          G = (G + this.G) | 0;
          H = (H + this.H) | 0;
          this.set(A, B, C, D, E, F, G, H);
      }
      roundClean() {
          clean$2(SHA256_W$3);
      }
      destroy() {
          this.set(0, 0, 0, 0, 0, 0, 0, 0);
          clean$2(this.buffer);
      }
  }
  /** Internal SHA2-256 hash class. */
  class _SHA256 extends SHA2_32B {
      // We cannot use array here since array allows indexing by variable
      // which means optimizer/compiler cannot use registers.
      A = SHA256_IV$1[0] | 0;
      B = SHA256_IV$1[1] | 0;
      C = SHA256_IV$1[2] | 0;
      D = SHA256_IV$1[3] | 0;
      E = SHA256_IV$1[4] | 0;
      F = SHA256_IV$1[5] | 0;
      G = SHA256_IV$1[6] | 0;
      H = SHA256_IV$1[7] | 0;
      constructor() {
          super(32);
      }
  }
  /**
   * SHA2-256 hash function from RFC 4634. In JS it's the fastest: even faster than Blake3. Some info:
   *
   * - Trying 2^128 hashes would get 50% chance of collision, using birthday attack.
   * - BTC network is doing 2^70 hashes/sec (2^95 hashes/year) as per 2025.
   * - Each sha256 hash is executing 2^18 bit operations.
   * - Good 2024 ASICs can do 200Th/sec with 3500 watts of power, corresponding to 2^36 hashes/joule.
   */
  const sha256$3 = /* @__PURE__ */ createHasher$1(() => new _SHA256(), 
  /* @__PURE__ */ oidNist(0x01));

  /**
   * Hex, bytes and number utilities.
   * @module
   */
  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  const _0n$c = /* @__PURE__ */ BigInt(0);
  const _1n$c = /* @__PURE__ */ BigInt(1);
  function abool$1(value, title = '') {
      if (typeof value !== 'boolean') {
          const prefix = title && `"${title}" `;
          throw new Error(prefix + 'expected boolean, got type=' + typeof value);
      }
      return value;
  }
  // Used in weierstrass, der
  function abignumber(n) {
      if (typeof n === 'bigint') {
          if (!isPosBig$1(n))
              throw new Error('positive bigint expected, got ' + n);
      }
      else
          anumber$3(n);
      return n;
  }
  function numberToHexUnpadded$1(num) {
      const hex = abignumber(num).toString(16);
      return hex.length & 1 ? '0' + hex : hex;
  }
  function hexToNumber$2(hex) {
      if (typeof hex !== 'string')
          throw new Error('hex string expected, got ' + typeof hex);
      return hex === '' ? _0n$c : BigInt('0x' + hex); // Big Endian
  }
  // BE: Big Endian, LE: Little Endian
  function bytesToNumberBE$3(bytes) {
      return hexToNumber$2(bytesToHex$5(bytes));
  }
  function bytesToNumberLE$2(bytes) {
      return hexToNumber$2(bytesToHex$5(copyBytes$1(abytes$3(bytes)).reverse()));
  }
  function numberToBytesBE$3(n, len) {
      anumber$3(len);
      n = abignumber(n);
      const res = hexToBytes$3(n.toString(16).padStart(len * 2, '0'));
      if (res.length !== len)
          throw new Error('number too large');
      return res;
  }
  function numberToBytesLE$2(n, len) {
      return numberToBytesBE$3(n, len).reverse();
  }
  /**
   * Copies Uint8Array. We can't use u8a.slice(), because u8a can be Buffer,
   * and Buffer#slice creates mutable copy. Never use Buffers!
   */
  function copyBytes$1(bytes) {
      return Uint8Array.from(bytes);
  }
  /**
   * Decodes 7-bit ASCII string to Uint8Array, throws on non-ascii symbols
   * Should be safe to use for things expected to be ASCII.
   * Returns exact same result as `TextEncoder` for ASCII or throws.
   */
  function asciiToBytes(ascii) {
      return Uint8Array.from(ascii, (c, i) => {
          const charCode = c.charCodeAt(0);
          if (c.length !== 1 || charCode > 127) {
              throw new Error(`string contains non-ASCII character "${ascii[i]}" with code ${charCode} at position ${i}`);
          }
          return charCode;
      });
  }
  // Is positive bigint
  const isPosBig$1 = (n) => typeof n === 'bigint' && _0n$c <= n;
  function inRange$1(n, min, max) {
      return isPosBig$1(n) && isPosBig$1(min) && isPosBig$1(max) && min <= n && n < max;
  }
  /**
   * Asserts min <= n < max. NOTE: It's < max and not <= max.
   * @example
   * aInRange('x', x, 1n, 256n); // would assume x is in (1n..255n)
   */
  function aInRange$1(title, n, min, max) {
      // Why min <= n < max and not a (min < n < max) OR b (min <= n <= max)?
      // consider P=256n, min=0n, max=P
      // - a for min=0 would require -1:          `inRange('x', x, -1n, P)`
      // - b would commonly require subtraction:  `inRange('x', x, 0n, P - 1n)`
      // - our way is the cleanest:               `inRange('x', x, 0n, P)
      if (!inRange$1(n, min, max))
          throw new Error('expected valid ' + title + ': ' + min + ' <= n < ' + max + ', got ' + n);
  }
  // Bit operations
  /**
   * Calculates amount of bits in a bigint.
   * Same as `n.toString(2).length`
   * TODO: merge with nLength in modular
   */
  function bitLen$1(n) {
      let len;
      for (len = 0; n > _0n$c; n >>= _1n$c, len += 1)
          ;
      return len;
  }
  /**
   * Calculate mask for N bits. Not using ** operator with bigints because of old engines.
   * Same as BigInt(`0b${Array(i).fill('1').join('')}`)
   */
  const bitMask$2 = (n) => (_1n$c << BigInt(n)) - _1n$c;
  /**
   * Minimal HMAC-DRBG from NIST 800-90 for RFC6979 sigs.
   * @returns function that will call DRBG until 2nd arg returns something meaningful
   * @example
   *   const drbg = createHmacDRBG<Key>(32, 32, hmac);
   *   drbg(seed, bytesToKey); // bytesToKey must return Key or undefined
   */
  function createHmacDrbg$2(hashLen, qByteLen, hmacFn) {
      anumber$3(hashLen, 'hashLen');
      anumber$3(qByteLen, 'qByteLen');
      if (typeof hmacFn !== 'function')
          throw new Error('hmacFn must be a function');
      const u8n = (len) => new Uint8Array(len); // creates Uint8Array
      const NULL = Uint8Array.of();
      const byte0 = Uint8Array.of(0x00);
      const byte1 = Uint8Array.of(0x01);
      const _maxDrbgIters = 1000;
      // Step B, Step C: set hashLen to 8*ceil(hlen/8)
      let v = u8n(hashLen); // Minimal non-full-spec HMAC-DRBG from NIST 800-90 for RFC6979 sigs.
      let k = u8n(hashLen); // Steps B and C of RFC6979 3.2: set hashLen, in our case always same
      let i = 0; // Iterations counter, will throw when over 1000
      const reset = () => {
          v.fill(1);
          k.fill(0);
          i = 0;
      };
      const h = (...msgs) => hmacFn(k, concatBytes$3(v, ...msgs)); // hmac(k)(v, ...values)
      const reseed = (seed = NULL) => {
          // HMAC-DRBG reseed() function. Steps D-G
          k = h(byte0, seed); // k = hmac(k || v || 0x00 || seed)
          v = h(); // v = hmac(k || v)
          if (seed.length === 0)
              return;
          k = h(byte1, seed); // k = hmac(k || v || 0x01 || seed)
          v = h(); // v = hmac(k || v)
      };
      const gen = () => {
          // HMAC-DRBG generate() function
          if (i++ >= _maxDrbgIters)
              throw new Error('drbg: tried max amount of iterations');
          let len = 0;
          const out = [];
          while (len < qByteLen) {
              v = h();
              const sl = v.slice();
              out.push(sl);
              len += v.length;
          }
          return concatBytes$3(...out);
      };
      const genUntil = (seed, pred) => {
          reset();
          reseed(seed); // Steps D-G
          let res = undefined; // Step H: grind until k is in [1..n-1]
          while (!(res = pred(gen())))
              reseed();
          reset();
          return res;
      };
      return genUntil;
  }
  function validateObject$1(object, fields = {}, optFields = {}) {
      if (!object || typeof object !== 'object')
          throw new Error('expected valid options object');
      function checkField(fieldName, expectedType, isOpt) {
          const val = object[fieldName];
          if (isOpt && val === undefined)
              return;
          const current = typeof val;
          if (current !== expectedType || val === null)
              throw new Error(`param "${fieldName}" is invalid: expected ${expectedType}, got ${current}`);
      }
      const iter = (f, isOpt) => Object.entries(f).forEach(([k, v]) => checkField(k, v, isOpt));
      iter(fields, false);
      iter(optFields, true);
  }
  /**
   * Memoizes (caches) computation result.
   * Uses WeakMap: the value is going auto-cleaned by GC after last reference is removed.
   */
  function memoized$1(fn) {
      const map = new WeakMap();
      return (arg, ...args) => {
          const val = map.get(arg);
          if (val !== undefined)
              return val;
          const computed = fn(arg, ...args);
          map.set(arg, computed);
          return computed;
      };
  }

  /**
   * Utils for modular division and fields.
   * Field over 11 is a finite (Galois) field is integer number operations `mod 11`.
   * There is no division: it is replaced by modular multiplicative inverse.
   * @module
   */
  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // Numbers aren't used in x25519 / x448 builds
  // prettier-ignore
  const _0n$b = /* @__PURE__ */ BigInt(0), _1n$b = /* @__PURE__ */ BigInt(1), _2n$8 = /* @__PURE__ */ BigInt(2);
  // prettier-ignore
  const _3n$5 = /* @__PURE__ */ BigInt(3), _4n$4 = /* @__PURE__ */ BigInt(4), _5n$2 = /* @__PURE__ */ BigInt(5);
  // prettier-ignore
  const _7n$1 = /* @__PURE__ */ BigInt(7), _8n$2 = /* @__PURE__ */ BigInt(8), _9n$1 = /* @__PURE__ */ BigInt(9);
  const _16n$1 = /* @__PURE__ */ BigInt(16);
  // Calculates a modulo b
  function mod$3(a, b) {
      const result = a % b;
      return result >= _0n$b ? result : b + result;
  }
  /** Does `x^(2^power)` mod p. `pow2(30, 4)` == `30^(2^4)` */
  function pow2$2(x, power, modulo) {
      let res = x;
      while (power-- > _0n$b) {
          res *= res;
          res %= modulo;
      }
      return res;
  }
  /**
   * Inverses number over modulo.
   * Implemented using [Euclidean GCD](https://brilliant.org/wiki/extended-euclidean-algorithm/).
   */
  function invert$3(number, modulo) {
      if (number === _0n$b)
          throw new Error('invert: expected non-zero number');
      if (modulo <= _0n$b)
          throw new Error('invert: expected positive modulus, got ' + modulo);
      // Fermat's little theorem "CT-like" version inv(n) = n^(m-2) mod m is 30x slower.
      let a = mod$3(number, modulo);
      let b = modulo;
      // prettier-ignore
      let x = _0n$b, u = _1n$b;
      while (a !== _0n$b) {
          // JIT applies optimization if those two lines follow each other
          const q = b / a;
          const r = b % a;
          const m = x - u * q;
          // prettier-ignore
          b = a, a = r, x = u, u = m;
      }
      const gcd = b;
      if (gcd !== _1n$b)
          throw new Error('invert: does not exist');
      return mod$3(x, modulo);
  }
  function assertIsSquare$1(Fp, root, n) {
      if (!Fp.eql(Fp.sqr(root), n))
          throw new Error('Cannot find square root');
  }
  // Not all roots are possible! Example which will throw:
  // const NUM =
  // n = 72057594037927816n;
  // Fp = Field(BigInt('0x1a0111ea397fe69a4b1ba7b6434bacd764774b84f38512bf6730d2a0f6b0f6241eabfffeb153ffffb9feffffffffaaab'));
  function sqrt3mod4$1(Fp, n) {
      const p1div4 = (Fp.ORDER + _1n$b) / _4n$4;
      const root = Fp.pow(n, p1div4);
      assertIsSquare$1(Fp, root, n);
      return root;
  }
  function sqrt5mod8$1(Fp, n) {
      const p5div8 = (Fp.ORDER - _5n$2) / _8n$2;
      const n2 = Fp.mul(n, _2n$8);
      const v = Fp.pow(n2, p5div8);
      const nv = Fp.mul(n, v);
      const i = Fp.mul(Fp.mul(nv, _2n$8), v);
      const root = Fp.mul(nv, Fp.sub(i, Fp.ONE));
      assertIsSquare$1(Fp, root, n);
      return root;
  }
  // Based on RFC9380, Kong algorithm
  // prettier-ignore
  function sqrt9mod16$1(P) {
      const Fp_ = Field$2(P);
      const tn = tonelliShanks$2(P);
      const c1 = tn(Fp_, Fp_.neg(Fp_.ONE)); //  1. c1 = sqrt(-1) in F, i.e., (c1^2) == -1 in F
      const c2 = tn(Fp_, c1); //  2. c2 = sqrt(c1) in F, i.e., (c2^2) == c1 in F
      const c3 = tn(Fp_, Fp_.neg(c1)); //  3. c3 = sqrt(-c1) in F, i.e., (c3^2) == -c1 in F
      const c4 = (P + _7n$1) / _16n$1; //  4. c4 = (q + 7) / 16        # Integer arithmetic
      return (Fp, n) => {
          let tv1 = Fp.pow(n, c4); //  1. tv1 = x^c4
          let tv2 = Fp.mul(tv1, c1); //  2. tv2 = c1 * tv1
          const tv3 = Fp.mul(tv1, c2); //  3. tv3 = c2 * tv1
          const tv4 = Fp.mul(tv1, c3); //  4. tv4 = c3 * tv1
          const e1 = Fp.eql(Fp.sqr(tv2), n); //  5.  e1 = (tv2^2) == x
          const e2 = Fp.eql(Fp.sqr(tv3), n); //  6.  e2 = (tv3^2) == x
          tv1 = Fp.cmov(tv1, tv2, e1); //  7. tv1 = CMOV(tv1, tv2, e1)  # Select tv2 if (tv2^2) == x
          tv2 = Fp.cmov(tv4, tv3, e2); //  8. tv2 = CMOV(tv4, tv3, e2)  # Select tv3 if (tv3^2) == x
          const e3 = Fp.eql(Fp.sqr(tv2), n); //  9.  e3 = (tv2^2) == x
          const root = Fp.cmov(tv1, tv2, e3); // 10.  z = CMOV(tv1, tv2, e3)   # Select sqrt from tv1 & tv2
          assertIsSquare$1(Fp, root, n);
          return root;
      };
  }
  /**
   * Tonelli-Shanks square root search algorithm.
   * 1. https://eprint.iacr.org/2012/685.pdf (page 12)
   * 2. Square Roots from 1; 24, 51, 10 to Dan Shanks
   * @param P field order
   * @returns function that takes field Fp (created from P) and number n
   */
  function tonelliShanks$2(P) {
      // Initialization (precomputation).
      // Caching initialization could boost perf by 7%.
      if (P < _3n$5)
          throw new Error('sqrt is not defined for small field');
      // Factor P - 1 = Q * 2^S, where Q is odd
      let Q = P - _1n$b;
      let S = 0;
      while (Q % _2n$8 === _0n$b) {
          Q /= _2n$8;
          S++;
      }
      // Find the first quadratic non-residue Z >= 2
      let Z = _2n$8;
      const _Fp = Field$2(P);
      while (FpLegendre$1(_Fp, Z) === 1) {
          // Basic primality test for P. After x iterations, chance of
          // not finding quadratic non-residue is 2^x, so 2^1000.
          if (Z++ > 1000)
              throw new Error('Cannot find square root: probably non-prime P');
      }
      // Fast-path; usually done before Z, but we do "primality test".
      if (S === 1)
          return sqrt3mod4$1;
      // Slow-path
      // TODO: test on Fp2 and others
      let cc = _Fp.pow(Z, Q); // c = z^Q
      const Q1div2 = (Q + _1n$b) / _2n$8;
      return function tonelliSlow(Fp, n) {
          if (Fp.is0(n))
              return n;
          // Check if n is a quadratic residue using Legendre symbol
          if (FpLegendre$1(Fp, n) !== 1)
              throw new Error('Cannot find square root');
          // Initialize variables for the main loop
          let M = S;
          let c = Fp.mul(Fp.ONE, cc); // c = z^Q, move cc from field _Fp into field Fp
          let t = Fp.pow(n, Q); // t = n^Q, first guess at the fudge factor
          let R = Fp.pow(n, Q1div2); // R = n^((Q+1)/2), first guess at the square root
          // Main loop
          // while t != 1
          while (!Fp.eql(t, Fp.ONE)) {
              if (Fp.is0(t))
                  return Fp.ZERO; // if t=0 return R=0
              let i = 1;
              // Find the smallest i >= 1 such that t^(2^i) ≡ 1 (mod P)
              let t_tmp = Fp.sqr(t); // t^(2^1)
              while (!Fp.eql(t_tmp, Fp.ONE)) {
                  i++;
                  t_tmp = Fp.sqr(t_tmp); // t^(2^2)...
                  if (i === M)
                      throw new Error('Cannot find square root');
              }
              // Calculate the exponent for b: 2^(M - i - 1)
              const exponent = _1n$b << BigInt(M - i - 1); // bigint is important
              const b = Fp.pow(c, exponent); // b = 2^(M - i - 1)
              // Update variables
              M = i;
              c = Fp.sqr(b); // c = b^2
              t = Fp.mul(t, c); // t = (t * b^2)
              R = Fp.mul(R, b); // R = R*b
          }
          return R;
      };
  }
  /**
   * Square root for a finite field. Will try optimized versions first:
   *
   * 1. P ≡ 3 (mod 4)
   * 2. P ≡ 5 (mod 8)
   * 3. P ≡ 9 (mod 16)
   * 4. Tonelli-Shanks algorithm
   *
   * Different algorithms can give different roots, it is up to user to decide which one they want.
   * For example there is FpSqrtOdd/FpSqrtEven to choice root based on oddness (used for hash-to-curve).
   */
  function FpSqrt$2(P) {
      // P ≡ 3 (mod 4) => √n = n^((P+1)/4)
      if (P % _4n$4 === _3n$5)
          return sqrt3mod4$1;
      // P ≡ 5 (mod 8) => Atkin algorithm, page 10 of https://eprint.iacr.org/2012/685.pdf
      if (P % _8n$2 === _5n$2)
          return sqrt5mod8$1;
      // P ≡ 9 (mod 16) => Kong algorithm, page 11 of https://eprint.iacr.org/2012/685.pdf (algorithm 4)
      if (P % _16n$1 === _9n$1)
          return sqrt9mod16$1(P);
      // Tonelli-Shanks algorithm
      return tonelliShanks$2(P);
  }
  // prettier-ignore
  const FIELD_FIELDS$2 = [
      'create', 'isValid', 'is0', 'neg', 'inv', 'sqrt', 'sqr',
      'eql', 'add', 'sub', 'mul', 'pow', 'div',
      'addN', 'subN', 'mulN', 'sqrN'
  ];
  function validateField$2(field) {
      const initial = {
          ORDER: 'bigint',
          BYTES: 'number',
          BITS: 'number',
      };
      const opts = FIELD_FIELDS$2.reduce((map, val) => {
          map[val] = 'function';
          return map;
      }, initial);
      validateObject$1(field, opts);
      // const max = 16384;
      // if (field.BYTES < 1 || field.BYTES > max) throw new Error('invalid field');
      // if (field.BITS < 1 || field.BITS > 8 * max) throw new Error('invalid field');
      return field;
  }
  // Generic field functions
  /**
   * Same as `pow` but for Fp: non-constant-time.
   * Unsafe in some contexts: uses ladder, so can expose bigint bits.
   */
  function FpPow$2(Fp, num, power) {
      if (power < _0n$b)
          throw new Error('invalid exponent, negatives unsupported');
      if (power === _0n$b)
          return Fp.ONE;
      if (power === _1n$b)
          return num;
      let p = Fp.ONE;
      let d = num;
      while (power > _0n$b) {
          if (power & _1n$b)
              p = Fp.mul(p, d);
          d = Fp.sqr(d);
          power >>= _1n$b;
      }
      return p;
  }
  /**
   * Efficiently invert an array of Field elements.
   * Exception-free. Will return `undefined` for 0 elements.
   * @param passZero map 0 to 0 (instead of undefined)
   */
  function FpInvertBatch$2(Fp, nums, passZero = false) {
      const inverted = new Array(nums.length).fill(passZero ? Fp.ZERO : undefined);
      // Walk from first to last, multiply them by each other MOD p
      const multipliedAcc = nums.reduce((acc, num, i) => {
          if (Fp.is0(num))
              return acc;
          inverted[i] = acc;
          return Fp.mul(acc, num);
      }, Fp.ONE);
      // Invert last element
      const invertedAcc = Fp.inv(multipliedAcc);
      // Walk from last to first, multiply them by inverted each other MOD p
      nums.reduceRight((acc, num, i) => {
          if (Fp.is0(num))
              return acc;
          inverted[i] = Fp.mul(acc, inverted[i]);
          return Fp.mul(acc, num);
      }, invertedAcc);
      return inverted;
  }
  /**
   * Legendre symbol.
   * Legendre constant is used to calculate Legendre symbol (a | p)
   * which denotes the value of a^((p-1)/2) (mod p).
   *
   * * (a | p) ≡ 1    if a is a square (mod p), quadratic residue
   * * (a | p) ≡ -1   if a is not a square (mod p), quadratic non residue
   * * (a | p) ≡ 0    if a ≡ 0 (mod p)
   */
  function FpLegendre$1(Fp, n) {
      // We can use 3rd argument as optional cache of this value
      // but seems unneeded for now. The operation is very fast.
      const p1mod2 = (Fp.ORDER - _1n$b) / _2n$8;
      const powered = Fp.pow(n, p1mod2);
      const yes = Fp.eql(powered, Fp.ONE);
      const zero = Fp.eql(powered, Fp.ZERO);
      const no = Fp.eql(powered, Fp.neg(Fp.ONE));
      if (!yes && !zero && !no)
          throw new Error('invalid Legendre symbol result');
      return yes ? 1 : zero ? 0 : -1;
  }
  // CURVE.n lengths
  function nLength$2(n, nBitLength) {
      // Bit size, byte size of CURVE.n
      if (nBitLength !== undefined)
          anumber$3(nBitLength);
      const _nBitLength = nBitLength !== undefined ? nBitLength : n.toString(2).length;
      const nByteLength = Math.ceil(_nBitLength / 8);
      return { nBitLength: _nBitLength, nByteLength };
  }
  class _Field {
      ORDER;
      BITS;
      BYTES;
      isLE;
      ZERO = _0n$b;
      ONE = _1n$b;
      _lengths;
      _sqrt; // cached sqrt
      _mod;
      constructor(ORDER, opts = {}) {
          if (ORDER <= _0n$b)
              throw new Error('invalid field: expected ORDER > 0, got ' + ORDER);
          let _nbitLength = undefined;
          this.isLE = false;
          if (opts != null && typeof opts === 'object') {
              if (typeof opts.BITS === 'number')
                  _nbitLength = opts.BITS;
              if (typeof opts.sqrt === 'function')
                  this.sqrt = opts.sqrt;
              if (typeof opts.isLE === 'boolean')
                  this.isLE = opts.isLE;
              if (opts.allowedLengths)
                  this._lengths = opts.allowedLengths?.slice();
              if (typeof opts.modFromBytes === 'boolean')
                  this._mod = opts.modFromBytes;
          }
          const { nBitLength, nByteLength } = nLength$2(ORDER, _nbitLength);
          if (nByteLength > 2048)
              throw new Error('invalid field: expected ORDER of <= 2048 bytes');
          this.ORDER = ORDER;
          this.BITS = nBitLength;
          this.BYTES = nByteLength;
          this._sqrt = undefined;
          Object.preventExtensions(this);
      }
      create(num) {
          return mod$3(num, this.ORDER);
      }
      isValid(num) {
          if (typeof num !== 'bigint')
              throw new Error('invalid field element: expected bigint, got ' + typeof num);
          return _0n$b <= num && num < this.ORDER; // 0 is valid element, but it's not invertible
      }
      is0(num) {
          return num === _0n$b;
      }
      // is valid and invertible
      isValidNot0(num) {
          return !this.is0(num) && this.isValid(num);
      }
      isOdd(num) {
          return (num & _1n$b) === _1n$b;
      }
      neg(num) {
          return mod$3(-num, this.ORDER);
      }
      eql(lhs, rhs) {
          return lhs === rhs;
      }
      sqr(num) {
          return mod$3(num * num, this.ORDER);
      }
      add(lhs, rhs) {
          return mod$3(lhs + rhs, this.ORDER);
      }
      sub(lhs, rhs) {
          return mod$3(lhs - rhs, this.ORDER);
      }
      mul(lhs, rhs) {
          return mod$3(lhs * rhs, this.ORDER);
      }
      pow(num, power) {
          return FpPow$2(this, num, power);
      }
      div(lhs, rhs) {
          return mod$3(lhs * invert$3(rhs, this.ORDER), this.ORDER);
      }
      // Same as above, but doesn't normalize
      sqrN(num) {
          return num * num;
      }
      addN(lhs, rhs) {
          return lhs + rhs;
      }
      subN(lhs, rhs) {
          return lhs - rhs;
      }
      mulN(lhs, rhs) {
          return lhs * rhs;
      }
      inv(num) {
          return invert$3(num, this.ORDER);
      }
      sqrt(num) {
          // Caching _sqrt speeds up sqrt9mod16 by 5x and tonneli-shanks by 10%
          if (!this._sqrt)
              this._sqrt = FpSqrt$2(this.ORDER);
          return this._sqrt(this, num);
      }
      toBytes(num) {
          return this.isLE ? numberToBytesLE$2(num, this.BYTES) : numberToBytesBE$3(num, this.BYTES);
      }
      fromBytes(bytes, skipValidation = false) {
          abytes$3(bytes);
          const { _lengths: allowedLengths, BYTES, isLE, ORDER, _mod: modFromBytes } = this;
          if (allowedLengths) {
              if (!allowedLengths.includes(bytes.length) || bytes.length > BYTES) {
                  throw new Error('Field.fromBytes: expected ' + allowedLengths + ' bytes, got ' + bytes.length);
              }
              const padded = new Uint8Array(BYTES);
              // isLE add 0 to right, !isLE to the left.
              padded.set(bytes, isLE ? 0 : padded.length - bytes.length);
              bytes = padded;
          }
          if (bytes.length !== BYTES)
              throw new Error('Field.fromBytes: expected ' + BYTES + ' bytes, got ' + bytes.length);
          let scalar = isLE ? bytesToNumberLE$2(bytes) : bytesToNumberBE$3(bytes);
          if (modFromBytes)
              scalar = mod$3(scalar, ORDER);
          if (!skipValidation)
              if (!this.isValid(scalar))
                  throw new Error('invalid field element: outside of range 0..ORDER');
          // NOTE: we don't validate scalar here, please use isValid. This done such way because some
          // protocol may allow non-reduced scalar that reduced later or changed some other way.
          return scalar;
      }
      // TODO: we don't need it here, move out to separate fn
      invertBatch(lst) {
          return FpInvertBatch$2(this, lst);
      }
      // We can't move this out because Fp6, Fp12 implement it
      // and it's unclear what to return in there.
      cmov(a, b, condition) {
          return condition ? b : a;
      }
  }
  /**
   * Creates a finite field. Major performance optimizations:
   * * 1. Denormalized operations like mulN instead of mul.
   * * 2. Identical object shape: never add or remove keys.
   * * 3. `Object.freeze`.
   * Fragile: always run a benchmark on a change.
   * Security note: operations don't check 'isValid' for all elements for performance reasons,
   * it is caller responsibility to check this.
   * This is low-level code, please make sure you know what you're doing.
   *
   * Note about field properties:
   * * CHARACTERISTIC p = prime number, number of elements in main subgroup.
   * * ORDER q = similar to cofactor in curves, may be composite `q = p^m`.
   *
   * @param ORDER field order, probably prime, or could be composite
   * @param bitLen how many bits the field consumes
   * @param isLE (default: false) if encoding / decoding should be in little-endian
   * @param redef optional faster redefinitions of sqrt and other methods
   */
  function Field$2(ORDER, opts = {}) {
      return new _Field(ORDER, opts);
  }
  /**
   * Returns total number of bytes consumed by the field element.
   * For example, 32 bytes for usual 256-bit weierstrass curve.
   * @param fieldOrder number of field elements, usually CURVE.n
   * @returns byte length of field
   */
  function getFieldBytesLength$2(fieldOrder) {
      if (typeof fieldOrder !== 'bigint')
          throw new Error('field order must be bigint');
      const bitLength = fieldOrder.toString(2).length;
      return Math.ceil(bitLength / 8);
  }
  /**
   * Returns minimal amount of bytes that can be safely reduced
   * by field order.
   * Should be 2^-128 for 128-bit curve such as P256.
   * @param fieldOrder number of field elements, usually CURVE.n
   * @returns byte length of target hash
   */
  function getMinHashLength$2(fieldOrder) {
      const length = getFieldBytesLength$2(fieldOrder);
      return length + Math.ceil(length / 2);
  }
  /**
   * "Constant-time" private key generation utility.
   * Can take (n + n/2) or more bytes of uniform input e.g. from CSPRNG or KDF
   * and convert them into private scalar, with the modulo bias being negligible.
   * Needs at least 48 bytes of input for 32-byte private key.
   * https://research.kudelskisecurity.com/2020/07/28/the-definitive-guide-to-modulo-bias-and-how-to-avoid-it/
   * FIPS 186-5, A.2 https://csrc.nist.gov/publications/detail/fips/186/5/final
   * RFC 9380, https://www.rfc-editor.org/rfc/rfc9380#section-5
   * @param hash hash output from SHA3 or a similar function
   * @param groupOrder size of subgroup - (e.g. secp256k1.Point.Fn.ORDER)
   * @param isLE interpret hash bytes as LE num
   * @returns valid private scalar
   */
  function mapHashToField$2(key, fieldOrder, isLE = false) {
      abytes$3(key);
      const len = key.length;
      const fieldLen = getFieldBytesLength$2(fieldOrder);
      const minLen = getMinHashLength$2(fieldOrder);
      // No small numbers: need to understand bias story. No huge numbers: easier to detect JS timings.
      if (len < 16 || len < minLen || len > 1024)
          throw new Error('expected ' + minLen + '-1024 bytes of input, got ' + len);
      const num = isLE ? bytesToNumberLE$2(key) : bytesToNumberBE$3(key);
      // `mod(x, 11)` can sometimes produce 0. `mod(x, 10) + 1` is the same, but no 0
      const reduced = mod$3(num, fieldOrder - _1n$b) + _1n$b;
      return isLE ? numberToBytesLE$2(reduced, fieldLen) : numberToBytesBE$3(reduced, fieldLen);
  }

  /**
   * Methods for elliptic curve multiplication by scalars.
   * Contains wNAF, pippenger.
   * @module
   */
  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  const _0n$a = /* @__PURE__ */ BigInt(0);
  const _1n$a = /* @__PURE__ */ BigInt(1);
  function negateCt$1(condition, item) {
      const neg = item.negate();
      return condition ? neg : item;
  }
  /**
   * Takes a bunch of Projective Points but executes only one
   * inversion on all of them. Inversion is very slow operation,
   * so this improves performance massively.
   * Optimization: converts a list of projective points to a list of identical points with Z=1.
   */
  function normalizeZ$1(c, points) {
      const invertedZs = FpInvertBatch$2(c.Fp, points.map((p) => p.Z));
      return points.map((p, i) => c.fromAffine(p.toAffine(invertedZs[i])));
  }
  function validateW$1(W, bits) {
      if (!Number.isSafeInteger(W) || W <= 0 || W > bits)
          throw new Error('invalid window size, expected [1..' + bits + '], got W=' + W);
  }
  function calcWOpts$1(W, scalarBits) {
      validateW$1(W, scalarBits);
      const windows = Math.ceil(scalarBits / W) + 1; // W=8 33. Not 32, because we skip zero
      const windowSize = 2 ** (W - 1); // W=8 128. Not 256, because we skip zero
      const maxNumber = 2 ** W; // W=8 256
      const mask = bitMask$2(W); // W=8 255 == mask 0b11111111
      const shiftBy = BigInt(W); // W=8 8
      return { windows, windowSize, mask, maxNumber, shiftBy };
  }
  function calcOffsets$1(n, window, wOpts) {
      const { windowSize, mask, maxNumber, shiftBy } = wOpts;
      let wbits = Number(n & mask); // extract W bits.
      let nextN = n >> shiftBy; // shift number by W bits.
      // What actually happens here:
      // const highestBit = Number(mask ^ (mask >> 1n));
      // let wbits2 = wbits - 1; // skip zero
      // if (wbits2 & highestBit) { wbits2 ^= Number(mask); // (~);
      // split if bits > max: +224 => 256-32
      if (wbits > windowSize) {
          // we skip zero, which means instead of `>= size-1`, we do `> size`
          wbits -= maxNumber; // -32, can be maxNumber - wbits, but then we need to set isNeg here.
          nextN += _1n$a; // +256 (carry)
      }
      const offsetStart = window * windowSize;
      const offset = offsetStart + Math.abs(wbits) - 1; // -1 because we skip zero
      const isZero = wbits === 0; // is current window slice a 0?
      const isNeg = wbits < 0; // is current window slice negative?
      const isNegF = window % 2 !== 0; // fake random statement for noise
      const offsetF = offsetStart; // fake offset for noise
      return { nextN, offset, isZero, isNeg, isNegF, offsetF };
  }
  // Since points in different groups cannot be equal (different object constructor),
  // we can have single place to store precomputes.
  // Allows to make points frozen / immutable.
  const pointPrecomputes$1 = new WeakMap();
  const pointWindowSizes$1 = new WeakMap();
  function getW$1(P) {
      // To disable precomputes:
      // return 1;
      return pointWindowSizes$1.get(P) || 1;
  }
  function assert0$1(n) {
      if (n !== _0n$a)
          throw new Error('invalid wNAF');
  }
  /**
   * Elliptic curve multiplication of Point by scalar. Fragile.
   * Table generation takes **30MB of ram and 10ms on high-end CPU**,
   * but may take much longer on slow devices. Actual generation will happen on
   * first call of `multiply()`. By default, `BASE` point is precomputed.
   *
   * Scalars should always be less than curve order: this should be checked inside of a curve itself.
   * Creates precomputation tables for fast multiplication:
   * - private scalar is split by fixed size windows of W bits
   * - every window point is collected from window's table & added to accumulator
   * - since windows are different, same point inside tables won't be accessed more than once per calc
   * - each multiplication is 'Math.ceil(CURVE_ORDER / 𝑊) + 1' point additions (fixed for any scalar)
   * - +1 window is neccessary for wNAF
   * - wNAF reduces table size: 2x less memory + 2x faster generation, but 10% slower multiplication
   *
   * @todo Research returning 2d JS array of windows, instead of a single window.
   * This would allow windows to be in different memory locations
   */
  let wNAF$2 = class wNAF {
      BASE;
      ZERO;
      Fn;
      bits;
      // Parametrized with a given Point class (not individual point)
      constructor(Point, bits) {
          this.BASE = Point.BASE;
          this.ZERO = Point.ZERO;
          this.Fn = Point.Fn;
          this.bits = bits;
      }
      // non-const time multiplication ladder
      _unsafeLadder(elm, n, p = this.ZERO) {
          let d = elm;
          while (n > _0n$a) {
              if (n & _1n$a)
                  p = p.add(d);
              d = d.double();
              n >>= _1n$a;
          }
          return p;
      }
      /**
       * Creates a wNAF precomputation window. Used for caching.
       * Default window size is set by `utils.precompute()` and is equal to 8.
       * Number of precomputed points depends on the curve size:
       * 2^(𝑊−1) * (Math.ceil(𝑛 / 𝑊) + 1), where:
       * - 𝑊 is the window size
       * - 𝑛 is the bitlength of the curve order.
       * For a 256-bit curve and window size 8, the number of precomputed points is 128 * 33 = 4224.
       * @param point Point instance
       * @param W window size
       * @returns precomputed point tables flattened to a single array
       */
      precomputeWindow(point, W) {
          const { windows, windowSize } = calcWOpts$1(W, this.bits);
          const points = [];
          let p = point;
          let base = p;
          for (let window = 0; window < windows; window++) {
              base = p;
              points.push(base);
              // i=1, bc we skip 0
              for (let i = 1; i < windowSize; i++) {
                  base = base.add(p);
                  points.push(base);
              }
              p = base.double();
          }
          return points;
      }
      /**
       * Implements ec multiplication using precomputed tables and w-ary non-adjacent form.
       * More compact implementation:
       * https://github.com/paulmillr/noble-secp256k1/blob/47cb1669b6e506ad66b35fe7d76132ae97465da2/index.ts#L502-L541
       * @returns real and fake (for const-time) points
       */
      wNAF(W, precomputes, n) {
          // Scalar should be smaller than field order
          if (!this.Fn.isValid(n))
              throw new Error('invalid scalar');
          // Accumulators
          let p = this.ZERO;
          let f = this.BASE;
          // This code was first written with assumption that 'f' and 'p' will never be infinity point:
          // since each addition is multiplied by 2 ** W, it cannot cancel each other. However,
          // there is negate now: it is possible that negated element from low value
          // would be the same as high element, which will create carry into next window.
          // It's not obvious how this can fail, but still worth investigating later.
          const wo = calcWOpts$1(W, this.bits);
          for (let window = 0; window < wo.windows; window++) {
              // (n === _0n) is handled and not early-exited. isEven and offsetF are used for noise
              const { nextN, offset, isZero, isNeg, isNegF, offsetF } = calcOffsets$1(n, window, wo);
              n = nextN;
              if (isZero) {
                  // bits are 0: add garbage to fake point
                  // Important part for const-time getPublicKey: add random "noise" point to f.
                  f = f.add(negateCt$1(isNegF, precomputes[offsetF]));
              }
              else {
                  // bits are 1: add to result point
                  p = p.add(negateCt$1(isNeg, precomputes[offset]));
              }
          }
          assert0$1(n);
          // Return both real and fake points: JIT won't eliminate f.
          // At this point there is a way to F be infinity-point even if p is not,
          // which makes it less const-time: around 1 bigint multiply.
          return { p, f };
      }
      /**
       * Implements ec unsafe (non const-time) multiplication using precomputed tables and w-ary non-adjacent form.
       * @param acc accumulator point to add result of multiplication
       * @returns point
       */
      wNAFUnsafe(W, precomputes, n, acc = this.ZERO) {
          const wo = calcWOpts$1(W, this.bits);
          for (let window = 0; window < wo.windows; window++) {
              if (n === _0n$a)
                  break; // Early-exit, skip 0 value
              const { nextN, offset, isZero, isNeg } = calcOffsets$1(n, window, wo);
              n = nextN;
              if (isZero) {
                  // Window bits are 0: skip processing.
                  // Move to next window.
                  continue;
              }
              else {
                  const item = precomputes[offset];
                  acc = acc.add(isNeg ? item.negate() : item); // Re-using acc allows to save adds in MSM
              }
          }
          assert0$1(n);
          return acc;
      }
      getPrecomputes(W, point, transform) {
          // Calculate precomputes on a first run, reuse them after
          let comp = pointPrecomputes$1.get(point);
          if (!comp) {
              comp = this.precomputeWindow(point, W);
              if (W !== 1) {
                  // Doing transform outside of if brings 15% perf hit
                  if (typeof transform === 'function')
                      comp = transform(comp);
                  pointPrecomputes$1.set(point, comp);
              }
          }
          return comp;
      }
      cached(point, scalar, transform) {
          const W = getW$1(point);
          return this.wNAF(W, this.getPrecomputes(W, point, transform), scalar);
      }
      unsafe(point, scalar, transform, prev) {
          const W = getW$1(point);
          if (W === 1)
              return this._unsafeLadder(point, scalar, prev); // For W=1 ladder is ~x2 faster
          return this.wNAFUnsafe(W, this.getPrecomputes(W, point, transform), scalar, prev);
      }
      // We calculate precomputes for elliptic curve point multiplication
      // using windowed method. This specifies window size and
      // stores precomputed values. Usually only base point would be precomputed.
      createCache(P, W) {
          validateW$1(W, this.bits);
          pointWindowSizes$1.set(P, W);
          pointPrecomputes$1.delete(P);
      }
      hasCache(elm) {
          return getW$1(elm) !== 1;
      }
  };
  /**
   * Endomorphism-specific multiplication for Koblitz curves.
   * Cost: 128 dbl, 0-256 adds.
   */
  function mulEndoUnsafe$1(Point, point, k1, k2) {
      let acc = point;
      let p1 = Point.ZERO;
      let p2 = Point.ZERO;
      while (k1 > _0n$a || k2 > _0n$a) {
          if (k1 & _1n$a)
              p1 = p1.add(acc);
          if (k2 & _1n$a)
              p2 = p2.add(acc);
          acc = acc.double();
          k1 >>= _1n$a;
          k2 >>= _1n$a;
      }
      return { p1, p2 };
  }
  function createField$1(order, field, isLE) {
      if (field) {
          if (field.ORDER !== order)
              throw new Error('Field.ORDER must match order: Fp == p, Fn == n');
          validateField$2(field);
          return field;
      }
      else {
          return Field$2(order, { isLE });
      }
  }
  /** Validates CURVE opts and creates fields */
  function createCurveFields(type, CURVE, curveOpts = {}, FpFnLE) {
      if (FpFnLE === undefined)
          FpFnLE = type === 'edwards';
      if (!CURVE || typeof CURVE !== 'object')
          throw new Error(`expected valid ${type} CURVE object`);
      for (const p of ['p', 'n', 'h']) {
          const val = CURVE[p];
          if (!(typeof val === 'bigint' && val > _0n$a))
              throw new Error(`CURVE.${p} must be positive bigint`);
      }
      const Fp = createField$1(CURVE.p, curveOpts.Fp, FpFnLE);
      const Fn = createField$1(CURVE.n, curveOpts.Fn, FpFnLE);
      const _b = 'b' ;
      const params = ['Gx', 'Gy', 'a', _b];
      for (const p of params) {
          // @ts-ignore
          if (!Fp.isValid(CURVE[p]))
              throw new Error(`CURVE.${p} must be valid field element of CURVE.Fp`);
      }
      CURVE = Object.freeze(Object.assign({}, CURVE));
      return { CURVE, Fp, Fn };
  }
  function createKeygen(randomSecretKey, getPublicKey) {
      return function keygen(seed) {
          const secretKey = randomSecretKey(seed);
          return { secretKey, publicKey: getPublicKey(secretKey) };
      };
  }

  /**
   * HMAC: RFC2104 message authentication code.
   * @module
   */
  /** Internal class for HMAC. */
  class _HMAC {
      oHash;
      iHash;
      blockLen;
      outputLen;
      finished = false;
      destroyed = false;
      constructor(hash, key) {
          ahash$1(hash);
          abytes$3(key, undefined, 'key');
          this.iHash = hash.create();
          if (typeof this.iHash.update !== 'function')
              throw new Error('Expected instance of class which extends utils.Hash');
          this.blockLen = this.iHash.blockLen;
          this.outputLen = this.iHash.outputLen;
          const blockLen = this.blockLen;
          const pad = new Uint8Array(blockLen);
          // blockLen can be bigger than outputLen
          pad.set(key.length > blockLen ? hash.create().update(key).digest() : key);
          for (let i = 0; i < pad.length; i++)
              pad[i] ^= 0x36;
          this.iHash.update(pad);
          // By doing update (processing of first block) of outer hash here we can re-use it between multiple calls via clone
          this.oHash = hash.create();
          // Undo internal XOR && apply outer XOR
          for (let i = 0; i < pad.length; i++)
              pad[i] ^= 0x36 ^ 0x5c;
          this.oHash.update(pad);
          clean$2(pad);
      }
      update(buf) {
          aexists$2(this);
          this.iHash.update(buf);
          return this;
      }
      digestInto(out) {
          aexists$2(this);
          abytes$3(out, this.outputLen, 'output');
          this.finished = true;
          this.iHash.digestInto(out);
          this.oHash.update(out);
          this.oHash.digestInto(out);
          this.destroy();
      }
      digest() {
          const out = new Uint8Array(this.oHash.outputLen);
          this.digestInto(out);
          return out;
      }
      _cloneInto(to) {
          // Create new instance without calling constructor since key already in state and we don't know it.
          to ||= Object.create(Object.getPrototypeOf(this), {});
          const { oHash, iHash, finished, destroyed, blockLen, outputLen } = this;
          to = to;
          to.finished = finished;
          to.destroyed = destroyed;
          to.blockLen = blockLen;
          to.outputLen = outputLen;
          to.oHash = oHash._cloneInto(to.oHash);
          to.iHash = iHash._cloneInto(to.iHash);
          return to;
      }
      clone() {
          return this._cloneInto();
      }
      destroy() {
          this.destroyed = true;
          this.oHash.destroy();
          this.iHash.destroy();
      }
  }
  /**
   * HMAC: RFC2104 message authentication code.
   * @param hash - function that would be used e.g. sha256
   * @param key - message key
   * @param message - message data
   * @example
   * import { hmac } from '@noble/hashes/hmac';
   * import { sha256 } from '@noble/hashes/sha2';
   * const mac1 = hmac(sha256, 'key', 'message');
   */
  const hmac$2 = (hash, key, message) => new _HMAC(hash, key).update(message).digest();
  hmac$2.create = (hash, key) => new _HMAC(hash, key);

  /**
   * Short Weierstrass curve methods. The formula is: y² = x³ + ax + b.
   *
   * ### Design rationale for types
   *
   * * Interaction between classes from different curves should fail:
   *   `k256.Point.BASE.add(p256.Point.BASE)`
   * * For this purpose we want to use `instanceof` operator, which is fast and works during runtime
   * * Different calls of `curve()` would return different classes -
   *   `curve(params) !== curve(params)`: if somebody decided to monkey-patch their curve,
   *   it won't affect others
   *
   * TypeScript can't infer types for classes created inside a function. Classes is one instance
   * of nominative types in TypeScript and interfaces only check for shape, so it's hard to create
   * unique type for every function call.
   *
   * We can use generic types via some param, like curve opts, but that would:
   *     1. Enable interaction between `curve(params)` and `curve(params)` (curves of same params)
   *     which is hard to debug.
   *     2. Params can be generic and we can't enforce them to be constant value:
   *     if somebody creates curve from non-constant params,
   *     it would be allowed to interact with other curves with non-constant params
   *
   * @todo https://www.typescriptlang.org/docs/handbook/release-notes/typescript-2-7.html#unique-symbol
   * @module
   */
  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // We construct basis in such way that den is always positive and equals n, but num sign depends on basis (not on secret value)
  const divNearest$2 = (num, den) => (num + (num >= 0 ? den : -den) / _2n$7) / den;
  /**
   * Splits scalar for GLV endomorphism.
   */
  function _splitEndoScalar$1(k, basis, n) {
      // Split scalar into two such that part is ~half bits: `abs(part) < sqrt(N)`
      // Since part can be negative, we need to do this on point.
      // TODO: verifyScalar function which consumes lambda
      const [[a1, b1], [a2, b2]] = basis;
      const c1 = divNearest$2(b2 * k, n);
      const c2 = divNearest$2(-b1 * k, n);
      // |k1|/|k2| is < sqrt(N), but can be negative.
      // If we do `k1 mod N`, we'll get big scalar (`> sqrt(N)`): so, we do cheaper negation instead.
      let k1 = k - c1 * a1 - c2 * a2;
      let k2 = -c1 * b1 - c2 * b2;
      const k1neg = k1 < _0n$9;
      const k2neg = k2 < _0n$9;
      if (k1neg)
          k1 = -k1;
      if (k2neg)
          k2 = -k2;
      // Double check that resulting scalar less than half bits of N: otherwise wNAF will fail.
      // This should only happen on wrong basises. Also, math inside is too complex and I don't trust it.
      const MAX_NUM = bitMask$2(Math.ceil(bitLen$1(n) / 2)) + _1n$9; // Half bits of N
      if (k1 < _0n$9 || k1 >= MAX_NUM || k2 < _0n$9 || k2 >= MAX_NUM) {
          throw new Error('splitScalar (endomorphism): failed, k=' + k);
      }
      return { k1neg, k1, k2neg, k2 };
  }
  function validateSigFormat$1(format) {
      if (!['compact', 'recovered', 'der'].includes(format))
          throw new Error('Signature format must be "compact", "recovered", or "der"');
      return format;
  }
  function validateSigOpts$1(opts, def) {
      const optsn = {};
      for (let optName of Object.keys(def)) {
          // @ts-ignore
          optsn[optName] = opts[optName] === undefined ? def[optName] : opts[optName];
      }
      abool$1(optsn.lowS, 'lowS');
      abool$1(optsn.prehash, 'prehash');
      if (optsn.format !== undefined)
          validateSigFormat$1(optsn.format);
      return optsn;
  }
  let DERErr$1 = class DERErr extends Error {
      constructor(m = '') {
          super(m);
      }
  };
  /**
   * ASN.1 DER encoding utilities. ASN is very complex & fragile. Format:
   *
   *     [0x30 (SEQUENCE), bytelength, 0x02 (INTEGER), intLength, R, 0x02 (INTEGER), intLength, S]
   *
   * Docs: https://letsencrypt.org/docs/a-warm-welcome-to-asn1-and-der/, https://luca.ntop.org/Teaching/Appunti/asn1.html
   */
  const DER$2 = {
      // asn.1 DER encoding utils
      Err: DERErr$1,
      // Basic building block is TLV (Tag-Length-Value)
      _tlv: {
          encode: (tag, data) => {
              const { Err: E } = DER$2;
              if (tag < 0 || tag > 256)
                  throw new E('tlv.encode: wrong tag');
              if (data.length & 1)
                  throw new E('tlv.encode: unpadded data');
              const dataLen = data.length / 2;
              const len = numberToHexUnpadded$1(dataLen);
              if ((len.length / 2) & 0b1000_0000)
                  throw new E('tlv.encode: long form length too big');
              // length of length with long form flag
              const lenLen = dataLen > 127 ? numberToHexUnpadded$1((len.length / 2) | 0b1000_0000) : '';
              const t = numberToHexUnpadded$1(tag);
              return t + lenLen + len + data;
          },
          // v - value, l - left bytes (unparsed)
          decode(tag, data) {
              const { Err: E } = DER$2;
              let pos = 0;
              if (tag < 0 || tag > 256)
                  throw new E('tlv.encode: wrong tag');
              if (data.length < 2 || data[pos++] !== tag)
                  throw new E('tlv.decode: wrong tlv');
              const first = data[pos++];
              const isLong = !!(first & 0b1000_0000); // First bit of first length byte is flag for short/long form
              let length = 0;
              if (!isLong)
                  length = first;
              else {
                  // Long form: [longFlag(1bit), lengthLength(7bit), length (BE)]
                  const lenLen = first & 0b0111_1111;
                  if (!lenLen)
                      throw new E('tlv.decode(long): indefinite length not supported');
                  if (lenLen > 4)
                      throw new E('tlv.decode(long): byte length is too big'); // this will overflow u32 in js
                  const lengthBytes = data.subarray(pos, pos + lenLen);
                  if (lengthBytes.length !== lenLen)
                      throw new E('tlv.decode: length bytes not complete');
                  if (lengthBytes[0] === 0)
                      throw new E('tlv.decode(long): zero leftmost byte');
                  for (const b of lengthBytes)
                      length = (length << 8) | b;
                  pos += lenLen;
                  if (length < 128)
                      throw new E('tlv.decode(long): not minimal encoding');
              }
              const v = data.subarray(pos, pos + length);
              if (v.length !== length)
                  throw new E('tlv.decode: wrong value length');
              return { v, l: data.subarray(pos + length) };
          },
      },
      // https://crypto.stackexchange.com/a/57734 Leftmost bit of first byte is 'negative' flag,
      // since we always use positive integers here. It must always be empty:
      // - add zero byte if exists
      // - if next byte doesn't have a flag, leading zero is not allowed (minimal encoding)
      _int: {
          encode(num) {
              const { Err: E } = DER$2;
              if (num < _0n$9)
                  throw new E('integer: negative integers are not allowed');
              let hex = numberToHexUnpadded$1(num);
              // Pad with zero byte if negative flag is present
              if (Number.parseInt(hex[0], 16) & 0b1000)
                  hex = '00' + hex;
              if (hex.length & 1)
                  throw new E('unexpected DER parsing assertion: unpadded hex');
              return hex;
          },
          decode(data) {
              const { Err: E } = DER$2;
              if (data[0] & 0b1000_0000)
                  throw new E('invalid signature integer: negative');
              if (data[0] === 0x00 && !(data[1] & 0b1000_0000))
                  throw new E('invalid signature integer: unnecessary leading zero');
              return bytesToNumberBE$3(data);
          },
      },
      toSig(bytes) {
          // parse DER signature
          const { Err: E, _int: int, _tlv: tlv } = DER$2;
          const data = abytes$3(bytes, undefined, 'signature');
          const { v: seqBytes, l: seqLeftBytes } = tlv.decode(0x30, data);
          if (seqLeftBytes.length)
              throw new E('invalid signature: left bytes after parsing');
          const { v: rBytes, l: rLeftBytes } = tlv.decode(0x02, seqBytes);
          const { v: sBytes, l: sLeftBytes } = tlv.decode(0x02, rLeftBytes);
          if (sLeftBytes.length)
              throw new E('invalid signature: left bytes after parsing');
          return { r: int.decode(rBytes), s: int.decode(sBytes) };
      },
      hexFromSig(sig) {
          const { _tlv: tlv, _int: int } = DER$2;
          const rs = tlv.encode(0x02, int.encode(sig.r));
          const ss = tlv.encode(0x02, int.encode(sig.s));
          const seq = rs + ss;
          return tlv.encode(0x30, seq);
      },
  };
  // Be friendly to bad ECMAScript parsers by not using bigint literals
  // prettier-ignore
  const _0n$9 = BigInt(0), _1n$9 = BigInt(1), _2n$7 = BigInt(2), _3n$4 = BigInt(3), _4n$3 = BigInt(4);
  /**
   * Creates weierstrass Point constructor, based on specified curve options.
   *
   * See {@link WeierstrassOpts}.
   *
   * @example
  ```js
  const opts = {
    p: 0xfffffffffffffffffffffffffffffffeffffac73n,
    n: 0x100000000000000000001b8fa16dfab9aca16b6b3n,
    h: 1n,
    a: 0n,
    b: 7n,
    Gx: 0x3b4c382ce37aa192a4019e763036f4f5dd4d7ebbn,
    Gy: 0x938cf935318fdced6bc28286531733c3f03c4feen,
  };
  const secp160k1_Point = weierstrass(opts);
  ```
   */
  function weierstrass$2(params, extraOpts = {}) {
      const validated = createCurveFields('weierstrass', params, extraOpts);
      const { Fp, Fn } = validated;
      let CURVE = validated.CURVE;
      const { h: cofactor, n: CURVE_ORDER } = CURVE;
      validateObject$1(extraOpts, {}, {
          allowInfinityPoint: 'boolean',
          clearCofactor: 'function',
          isTorsionFree: 'function',
          fromBytes: 'function',
          toBytes: 'function',
          endo: 'object',
      });
      const { endo } = extraOpts;
      if (endo) {
          // validateObject(endo, { beta: 'bigint', splitScalar: 'function' });
          if (!Fp.is0(CURVE.a) || typeof endo.beta !== 'bigint' || !Array.isArray(endo.basises)) {
              throw new Error('invalid endo: expected "beta": bigint and "basises": array');
          }
      }
      const lengths = getWLengths$1(Fp, Fn);
      function assertCompressionIsSupported() {
          if (!Fp.isOdd)
              throw new Error('compression is not supported: Field does not have .isOdd()');
      }
      // Implements IEEE P1363 point encoding
      function pointToBytes(_c, point, isCompressed) {
          const { x, y } = point.toAffine();
          const bx = Fp.toBytes(x);
          abool$1(isCompressed, 'isCompressed');
          if (isCompressed) {
              assertCompressionIsSupported();
              const hasEvenY = !Fp.isOdd(y);
              return concatBytes$3(pprefix$1(hasEvenY), bx);
          }
          else {
              return concatBytes$3(Uint8Array.of(0x04), bx, Fp.toBytes(y));
          }
      }
      function pointFromBytes(bytes) {
          abytes$3(bytes, undefined, 'Point');
          const { publicKey: comp, publicKeyUncompressed: uncomp } = lengths; // e.g. for 32-byte: 33, 65
          const length = bytes.length;
          const head = bytes[0];
          const tail = bytes.subarray(1);
          // No actual validation is done here: use .assertValidity()
          if (length === comp && (head === 0x02 || head === 0x03)) {
              const x = Fp.fromBytes(tail);
              if (!Fp.isValid(x))
                  throw new Error('bad point: is not on curve, wrong x');
              const y2 = weierstrassEquation(x); // y² = x³ + ax + b
              let y;
              try {
                  y = Fp.sqrt(y2); // y = y² ^ (p+1)/4
              }
              catch (sqrtError) {
                  const err = sqrtError instanceof Error ? ': ' + sqrtError.message : '';
                  throw new Error('bad point: is not on curve, sqrt error' + err);
              }
              assertCompressionIsSupported();
              const evenY = Fp.isOdd(y);
              const evenH = (head & 1) === 1; // ECDSA-specific
              if (evenH !== evenY)
                  y = Fp.neg(y);
              return { x, y };
          }
          else if (length === uncomp && head === 0x04) {
              // TODO: more checks
              const L = Fp.BYTES;
              const x = Fp.fromBytes(tail.subarray(0, L));
              const y = Fp.fromBytes(tail.subarray(L, L * 2));
              if (!isValidXY(x, y))
                  throw new Error('bad point: is not on curve');
              return { x, y };
          }
          else {
              throw new Error(`bad point: got length ${length}, expected compressed=${comp} or uncompressed=${uncomp}`);
          }
      }
      const encodePoint = extraOpts.toBytes || pointToBytes;
      const decodePoint = extraOpts.fromBytes || pointFromBytes;
      function weierstrassEquation(x) {
          const x2 = Fp.sqr(x); // x * x
          const x3 = Fp.mul(x2, x); // x² * x
          return Fp.add(Fp.add(x3, Fp.mul(x, CURVE.a)), CURVE.b); // x³ + a * x + b
      }
      // TODO: move top-level
      /** Checks whether equation holds for given x, y: y² == x³ + ax + b */
      function isValidXY(x, y) {
          const left = Fp.sqr(y); // y²
          const right = weierstrassEquation(x); // x³ + ax + b
          return Fp.eql(left, right);
      }
      // Validate whether the passed curve params are valid.
      // Test 1: equation y² = x³ + ax + b should work for generator point.
      if (!isValidXY(CURVE.Gx, CURVE.Gy))
          throw new Error('bad curve params: generator point');
      // Test 2: discriminant Δ part should be non-zero: 4a³ + 27b² != 0.
      // Guarantees curve is genus-1, smooth (non-singular).
      const _4a3 = Fp.mul(Fp.pow(CURVE.a, _3n$4), _4n$3);
      const _27b2 = Fp.mul(Fp.sqr(CURVE.b), BigInt(27));
      if (Fp.is0(Fp.add(_4a3, _27b2)))
          throw new Error('bad curve params: a or b');
      /** Asserts coordinate is valid: 0 <= n < Fp.ORDER. */
      function acoord(title, n, banZero = false) {
          if (!Fp.isValid(n) || (banZero && Fp.is0(n)))
              throw new Error(`bad point coordinate ${title}`);
          return n;
      }
      function aprjpoint(other) {
          if (!(other instanceof Point))
              throw new Error('Weierstrass Point expected');
      }
      function splitEndoScalarN(k) {
          if (!endo || !endo.basises)
              throw new Error('no endo');
          return _splitEndoScalar$1(k, endo.basises, Fn.ORDER);
      }
      // Memoized toAffine / validity check. They are heavy. Points are immutable.
      // Converts Projective point to affine (x, y) coordinates.
      // Can accept precomputed Z^-1 - for example, from invertBatch.
      // (X, Y, Z) ∋ (x=X/Z, y=Y/Z)
      const toAffineMemo = memoized$1((p, iz) => {
          const { X, Y, Z } = p;
          // Fast-path for normalized points
          if (Fp.eql(Z, Fp.ONE))
              return { x: X, y: Y };
          const is0 = p.is0();
          // If invZ was 0, we return zero point. However we still want to execute
          // all operations, so we replace invZ with a random number, 1.
          if (iz == null)
              iz = is0 ? Fp.ONE : Fp.inv(Z);
          const x = Fp.mul(X, iz);
          const y = Fp.mul(Y, iz);
          const zz = Fp.mul(Z, iz);
          if (is0)
              return { x: Fp.ZERO, y: Fp.ZERO };
          if (!Fp.eql(zz, Fp.ONE))
              throw new Error('invZ was invalid');
          return { x, y };
      });
      // NOTE: on exception this will crash 'cached' and no value will be set.
      // Otherwise true will be return
      const assertValidMemo = memoized$1((p) => {
          if (p.is0()) {
              // (0, 1, 0) aka ZERO is invalid in most contexts.
              // In BLS, ZERO can be serialized, so we allow it.
              // (0, 0, 0) is invalid representation of ZERO.
              if (extraOpts.allowInfinityPoint && !Fp.is0(p.Y))
                  return;
              throw new Error('bad point: ZERO');
          }
          // Some 3rd-party test vectors require different wording between here & `fromCompressedHex`
          const { x, y } = p.toAffine();
          if (!Fp.isValid(x) || !Fp.isValid(y))
              throw new Error('bad point: x or y not field elements');
          if (!isValidXY(x, y))
              throw new Error('bad point: equation left != right');
          if (!p.isTorsionFree())
              throw new Error('bad point: not in prime-order subgroup');
          return true;
      });
      function finishEndo(endoBeta, k1p, k2p, k1neg, k2neg) {
          k2p = new Point(Fp.mul(k2p.X, endoBeta), k2p.Y, k2p.Z);
          k1p = negateCt$1(k1neg, k1p);
          k2p = negateCt$1(k2neg, k2p);
          return k1p.add(k2p);
      }
      /**
       * Projective Point works in 3d / projective (homogeneous) coordinates:(X, Y, Z) ∋ (x=X/Z, y=Y/Z).
       * Default Point works in 2d / affine coordinates: (x, y).
       * We're doing calculations in projective, because its operations don't require costly inversion.
       */
      class Point {
          // base / generator point
          static BASE = new Point(CURVE.Gx, CURVE.Gy, Fp.ONE);
          // zero / infinity / identity point
          static ZERO = new Point(Fp.ZERO, Fp.ONE, Fp.ZERO); // 0, 1, 0
          // math field
          static Fp = Fp;
          // scalar field
          static Fn = Fn;
          X;
          Y;
          Z;
          /** Does NOT validate if the point is valid. Use `.assertValidity()`. */
          constructor(X, Y, Z) {
              this.X = acoord('x', X);
              this.Y = acoord('y', Y, true);
              this.Z = acoord('z', Z);
              Object.freeze(this);
          }
          static CURVE() {
              return CURVE;
          }
          /** Does NOT validate if the point is valid. Use `.assertValidity()`. */
          static fromAffine(p) {
              const { x, y } = p || {};
              if (!p || !Fp.isValid(x) || !Fp.isValid(y))
                  throw new Error('invalid affine point');
              if (p instanceof Point)
                  throw new Error('projective point not allowed');
              // (0, 0) would've produced (0, 0, 1) - instead, we need (0, 1, 0)
              if (Fp.is0(x) && Fp.is0(y))
                  return Point.ZERO;
              return new Point(x, y, Fp.ONE);
          }
          static fromBytes(bytes) {
              const P = Point.fromAffine(decodePoint(abytes$3(bytes, undefined, 'point')));
              P.assertValidity();
              return P;
          }
          static fromHex(hex) {
              return Point.fromBytes(hexToBytes$3(hex));
          }
          get x() {
              return this.toAffine().x;
          }
          get y() {
              return this.toAffine().y;
          }
          /**
           *
           * @param windowSize
           * @param isLazy true will defer table computation until the first multiplication
           * @returns
           */
          precompute(windowSize = 8, isLazy = true) {
              wnaf.createCache(this, windowSize);
              if (!isLazy)
                  this.multiply(_3n$4); // random number
              return this;
          }
          // TODO: return `this`
          /** A point on curve is valid if it conforms to equation. */
          assertValidity() {
              assertValidMemo(this);
          }
          hasEvenY() {
              const { y } = this.toAffine();
              if (!Fp.isOdd)
                  throw new Error("Field doesn't support isOdd");
              return !Fp.isOdd(y);
          }
          /** Compare one point to another. */
          equals(other) {
              aprjpoint(other);
              const { X: X1, Y: Y1, Z: Z1 } = this;
              const { X: X2, Y: Y2, Z: Z2 } = other;
              const U1 = Fp.eql(Fp.mul(X1, Z2), Fp.mul(X2, Z1));
              const U2 = Fp.eql(Fp.mul(Y1, Z2), Fp.mul(Y2, Z1));
              return U1 && U2;
          }
          /** Flips point to one corresponding to (x, -y) in Affine coordinates. */
          negate() {
              return new Point(this.X, Fp.neg(this.Y), this.Z);
          }
          // Renes-Costello-Batina exception-free doubling formula.
          // There is 30% faster Jacobian formula, but it is not complete.
          // https://eprint.iacr.org/2015/1060, algorithm 3
          // Cost: 8M + 3S + 3*a + 2*b3 + 15add.
          double() {
              const { a, b } = CURVE;
              const b3 = Fp.mul(b, _3n$4);
              const { X: X1, Y: Y1, Z: Z1 } = this;
              let X3 = Fp.ZERO, Y3 = Fp.ZERO, Z3 = Fp.ZERO; // prettier-ignore
              let t0 = Fp.mul(X1, X1); // step 1
              let t1 = Fp.mul(Y1, Y1);
              let t2 = Fp.mul(Z1, Z1);
              let t3 = Fp.mul(X1, Y1);
              t3 = Fp.add(t3, t3); // step 5
              Z3 = Fp.mul(X1, Z1);
              Z3 = Fp.add(Z3, Z3);
              X3 = Fp.mul(a, Z3);
              Y3 = Fp.mul(b3, t2);
              Y3 = Fp.add(X3, Y3); // step 10
              X3 = Fp.sub(t1, Y3);
              Y3 = Fp.add(t1, Y3);
              Y3 = Fp.mul(X3, Y3);
              X3 = Fp.mul(t3, X3);
              Z3 = Fp.mul(b3, Z3); // step 15
              t2 = Fp.mul(a, t2);
              t3 = Fp.sub(t0, t2);
              t3 = Fp.mul(a, t3);
              t3 = Fp.add(t3, Z3);
              Z3 = Fp.add(t0, t0); // step 20
              t0 = Fp.add(Z3, t0);
              t0 = Fp.add(t0, t2);
              t0 = Fp.mul(t0, t3);
              Y3 = Fp.add(Y3, t0);
              t2 = Fp.mul(Y1, Z1); // step 25
              t2 = Fp.add(t2, t2);
              t0 = Fp.mul(t2, t3);
              X3 = Fp.sub(X3, t0);
              Z3 = Fp.mul(t2, t1);
              Z3 = Fp.add(Z3, Z3); // step 30
              Z3 = Fp.add(Z3, Z3);
              return new Point(X3, Y3, Z3);
          }
          // Renes-Costello-Batina exception-free addition formula.
          // There is 30% faster Jacobian formula, but it is not complete.
          // https://eprint.iacr.org/2015/1060, algorithm 1
          // Cost: 12M + 0S + 3*a + 3*b3 + 23add.
          add(other) {
              aprjpoint(other);
              const { X: X1, Y: Y1, Z: Z1 } = this;
              const { X: X2, Y: Y2, Z: Z2 } = other;
              let X3 = Fp.ZERO, Y3 = Fp.ZERO, Z3 = Fp.ZERO; // prettier-ignore
              const a = CURVE.a;
              const b3 = Fp.mul(CURVE.b, _3n$4);
              let t0 = Fp.mul(X1, X2); // step 1
              let t1 = Fp.mul(Y1, Y2);
              let t2 = Fp.mul(Z1, Z2);
              let t3 = Fp.add(X1, Y1);
              let t4 = Fp.add(X2, Y2); // step 5
              t3 = Fp.mul(t3, t4);
              t4 = Fp.add(t0, t1);
              t3 = Fp.sub(t3, t4);
              t4 = Fp.add(X1, Z1);
              let t5 = Fp.add(X2, Z2); // step 10
              t4 = Fp.mul(t4, t5);
              t5 = Fp.add(t0, t2);
              t4 = Fp.sub(t4, t5);
              t5 = Fp.add(Y1, Z1);
              X3 = Fp.add(Y2, Z2); // step 15
              t5 = Fp.mul(t5, X3);
              X3 = Fp.add(t1, t2);
              t5 = Fp.sub(t5, X3);
              Z3 = Fp.mul(a, t4);
              X3 = Fp.mul(b3, t2); // step 20
              Z3 = Fp.add(X3, Z3);
              X3 = Fp.sub(t1, Z3);
              Z3 = Fp.add(t1, Z3);
              Y3 = Fp.mul(X3, Z3);
              t1 = Fp.add(t0, t0); // step 25
              t1 = Fp.add(t1, t0);
              t2 = Fp.mul(a, t2);
              t4 = Fp.mul(b3, t4);
              t1 = Fp.add(t1, t2);
              t2 = Fp.sub(t0, t2); // step 30
              t2 = Fp.mul(a, t2);
              t4 = Fp.add(t4, t2);
              t0 = Fp.mul(t1, t4);
              Y3 = Fp.add(Y3, t0);
              t0 = Fp.mul(t5, t4); // step 35
              X3 = Fp.mul(t3, X3);
              X3 = Fp.sub(X3, t0);
              t0 = Fp.mul(t3, t1);
              Z3 = Fp.mul(t5, Z3);
              Z3 = Fp.add(Z3, t0); // step 40
              return new Point(X3, Y3, Z3);
          }
          subtract(other) {
              return this.add(other.negate());
          }
          is0() {
              return this.equals(Point.ZERO);
          }
          /**
           * Constant time multiplication.
           * Uses wNAF method. Windowed method may be 10% faster,
           * but takes 2x longer to generate and consumes 2x memory.
           * Uses precomputes when available.
           * Uses endomorphism for Koblitz curves.
           * @param scalar by which the point would be multiplied
           * @returns New point
           */
          multiply(scalar) {
              const { endo } = extraOpts;
              if (!Fn.isValidNot0(scalar))
                  throw new Error('invalid scalar: out of range'); // 0 is invalid
              let point, fake; // Fake point is used to const-time mult
              const mul = (n) => wnaf.cached(this, n, (p) => normalizeZ$1(Point, p));
              /** See docs for {@link EndomorphismOpts} */
              if (endo) {
                  const { k1neg, k1, k2neg, k2 } = splitEndoScalarN(scalar);
                  const { p: k1p, f: k1f } = mul(k1);
                  const { p: k2p, f: k2f } = mul(k2);
                  fake = k1f.add(k2f);
                  point = finishEndo(endo.beta, k1p, k2p, k1neg, k2neg);
              }
              else {
                  const { p, f } = mul(scalar);
                  point = p;
                  fake = f;
              }
              // Normalize `z` for both points, but return only real one
              return normalizeZ$1(Point, [point, fake])[0];
          }
          /**
           * Non-constant-time multiplication. Uses double-and-add algorithm.
           * It's faster, but should only be used when you don't care about
           * an exposed secret key e.g. sig verification, which works over *public* keys.
           */
          multiplyUnsafe(sc) {
              const { endo } = extraOpts;
              const p = this;
              if (!Fn.isValid(sc))
                  throw new Error('invalid scalar: out of range'); // 0 is valid
              if (sc === _0n$9 || p.is0())
                  return Point.ZERO; // 0
              if (sc === _1n$9)
                  return p; // 1
              if (wnaf.hasCache(this))
                  return this.multiply(sc); // precomputes
              // We don't have method for double scalar multiplication (aP + bQ):
              // Even with using Strauss-Shamir trick, it's 35% slower than naïve mul+add.
              if (endo) {
                  const { k1neg, k1, k2neg, k2 } = splitEndoScalarN(sc);
                  const { p1, p2 } = mulEndoUnsafe$1(Point, p, k1, k2); // 30% faster vs wnaf.unsafe
                  return finishEndo(endo.beta, p1, p2, k1neg, k2neg);
              }
              else {
                  return wnaf.unsafe(p, sc);
              }
          }
          /**
           * Converts Projective point to affine (x, y) coordinates.
           * @param invertedZ Z^-1 (inverted zero) - optional, precomputation is useful for invertBatch
           */
          toAffine(invertedZ) {
              return toAffineMemo(this, invertedZ);
          }
          /**
           * Checks whether Point is free of torsion elements (is in prime subgroup).
           * Always torsion-free for cofactor=1 curves.
           */
          isTorsionFree() {
              const { isTorsionFree } = extraOpts;
              if (cofactor === _1n$9)
                  return true;
              if (isTorsionFree)
                  return isTorsionFree(Point, this);
              return wnaf.unsafe(this, CURVE_ORDER).is0();
          }
          clearCofactor() {
              const { clearCofactor } = extraOpts;
              if (cofactor === _1n$9)
                  return this; // Fast-path
              if (clearCofactor)
                  return clearCofactor(Point, this);
              return this.multiplyUnsafe(cofactor);
          }
          isSmallOrder() {
              // can we use this.clearCofactor()?
              return this.multiplyUnsafe(cofactor).is0();
          }
          toBytes(isCompressed = true) {
              abool$1(isCompressed, 'isCompressed');
              this.assertValidity();
              return encodePoint(Point, this, isCompressed);
          }
          toHex(isCompressed = true) {
              return bytesToHex$5(this.toBytes(isCompressed));
          }
          toString() {
              return `<Point ${this.is0() ? 'ZERO' : this.toHex()}>`;
          }
      }
      const bits = Fn.BITS;
      const wnaf = new wNAF$2(Point, extraOpts.endo ? Math.ceil(bits / 2) : bits);
      Point.BASE.precompute(8); // Enable precomputes. Slows down first publicKey computation by 20ms.
      return Point;
  }
  // Points start with byte 0x02 when y is even; otherwise 0x03
  function pprefix$1(hasEvenY) {
      return Uint8Array.of(hasEvenY ? 0x02 : 0x03);
  }
  function getWLengths$1(Fp, Fn) {
      return {
          secretKey: Fn.BYTES,
          publicKey: 1 + Fp.BYTES,
          publicKeyUncompressed: 1 + 2 * Fp.BYTES,
          publicKeyHasPrefix: true,
          signature: 2 * Fn.BYTES,
      };
  }
  /**
   * Sometimes users only need getPublicKey, getSharedSecret, and secret key handling.
   * This helper ensures no signature functionality is present. Less code, smaller bundle size.
   */
  function ecdh$1(Point, ecdhOpts = {}) {
      const { Fn } = Point;
      const randomBytes_ = ecdhOpts.randomBytes || randomBytes$2;
      const lengths = Object.assign(getWLengths$1(Point.Fp, Fn), { seed: getMinHashLength$2(Fn.ORDER) });
      function isValidSecretKey(secretKey) {
          try {
              const num = Fn.fromBytes(secretKey);
              return Fn.isValidNot0(num);
          }
          catch (error) {
              return false;
          }
      }
      function isValidPublicKey(publicKey, isCompressed) {
          const { publicKey: comp, publicKeyUncompressed } = lengths;
          try {
              const l = publicKey.length;
              if (isCompressed === true && l !== comp)
                  return false;
              if (isCompressed === false && l !== publicKeyUncompressed)
                  return false;
              return !!Point.fromBytes(publicKey);
          }
          catch (error) {
              return false;
          }
      }
      /**
       * Produces cryptographically secure secret key from random of size
       * (groupLen + ceil(groupLen / 2)) with modulo bias being negligible.
       */
      function randomSecretKey(seed = randomBytes_(lengths.seed)) {
          return mapHashToField$2(abytes$3(seed, lengths.seed, 'seed'), Fn.ORDER);
      }
      /**
       * Computes public key for a secret key. Checks for validity of the secret key.
       * @param isCompressed whether to return compact (default), or full key
       * @returns Public key, full when isCompressed=false; short when isCompressed=true
       */
      function getPublicKey(secretKey, isCompressed = true) {
          return Point.BASE.multiply(Fn.fromBytes(secretKey)).toBytes(isCompressed);
      }
      /**
       * Quick and dirty check for item being public key. Does not validate hex, or being on-curve.
       */
      function isProbPub(item) {
          const { secretKey, publicKey, publicKeyUncompressed } = lengths;
          if (!isBytes$3(item))
              return undefined;
          if (('_lengths' in Fn && Fn._lengths) || secretKey === publicKey)
              return undefined;
          const l = abytes$3(item, undefined, 'key').length;
          return l === publicKey || l === publicKeyUncompressed;
      }
      /**
       * ECDH (Elliptic Curve Diffie Hellman).
       * Computes shared public key from secret key A and public key B.
       * Checks: 1) secret key validity 2) shared key is on-curve.
       * Does NOT hash the result.
       * @param isCompressed whether to return compact (default), or full key
       * @returns shared public key
       */
      function getSharedSecret(secretKeyA, publicKeyB, isCompressed = true) {
          if (isProbPub(secretKeyA) === true)
              throw new Error('first arg must be private key');
          if (isProbPub(publicKeyB) === false)
              throw new Error('second arg must be public key');
          const s = Fn.fromBytes(secretKeyA);
          const b = Point.fromBytes(publicKeyB); // checks for being on-curve
          return b.multiply(s).toBytes(isCompressed);
      }
      const utils = {
          isValidSecretKey,
          isValidPublicKey,
          randomSecretKey,
      };
      const keygen = createKeygen(randomSecretKey, getPublicKey);
      return Object.freeze({ getPublicKey, getSharedSecret, keygen, Point, utils, lengths });
  }
  /**
   * Creates ECDSA signing interface for given elliptic curve `Point` and `hash` function.
   *
   * @param Point created using {@link weierstrass} function
   * @param hash used for 1) message prehash-ing 2) k generation in `sign`, using hmac_drbg(hash)
   * @param ecdsaOpts rarely needed, see {@link ECDSAOpts}
   *
   * @example
   * ```js
   * const p256_Point = weierstrass(...);
   * const p256_sha256 = ecdsa(p256_Point, sha256);
   * const p256_sha224 = ecdsa(p256_Point, sha224);
   * const p256_sha224_r = ecdsa(p256_Point, sha224, { randomBytes: (length) => { ... } });
   * ```
   */
  function ecdsa$1(Point, hash, ecdsaOpts = {}) {
      ahash$1(hash);
      validateObject$1(ecdsaOpts, {}, {
          hmac: 'function',
          lowS: 'boolean',
          randomBytes: 'function',
          bits2int: 'function',
          bits2int_modN: 'function',
      });
      ecdsaOpts = Object.assign({}, ecdsaOpts);
      const randomBytes = ecdsaOpts.randomBytes || randomBytes$2;
      const hmac = ecdsaOpts.hmac || ((key, msg) => hmac$2(hash, key, msg));
      const { Fp, Fn } = Point;
      const { ORDER: CURVE_ORDER, BITS: fnBits } = Fn;
      const { keygen, getPublicKey, getSharedSecret, utils, lengths } = ecdh$1(Point, ecdsaOpts);
      const defaultSigOpts = {
          prehash: true,
          lowS: typeof ecdsaOpts.lowS === 'boolean' ? ecdsaOpts.lowS : true,
          format: 'compact',
          extraEntropy: false,
      };
      const hasLargeCofactor = CURVE_ORDER * _2n$7 < Fp.ORDER; // Won't CURVE().h > 2n be more effective?
      function isBiggerThanHalfOrder(number) {
          const HALF = CURVE_ORDER >> _1n$9;
          return number > HALF;
      }
      function validateRS(title, num) {
          if (!Fn.isValidNot0(num))
              throw new Error(`invalid signature ${title}: out of range 1..Point.Fn.ORDER`);
          return num;
      }
      function assertSmallCofactor() {
          // ECDSA recovery is hard for cofactor > 1 curves.
          // In sign, `r = q.x mod n`, and here we recover q.x from r.
          // While recovering q.x >= n, we need to add r+n for cofactor=1 curves.
          // However, for cofactor>1, r+n may not get q.x:
          // r+n*i would need to be done instead where i is unknown.
          // To easily get i, we either need to:
          // a. increase amount of valid recid values (4, 5...); OR
          // b. prohibit non-prime-order signatures (recid > 1).
          if (hasLargeCofactor)
              throw new Error('"recovered" sig type is not supported for cofactor >2 curves');
      }
      function validateSigLength(bytes, format) {
          validateSigFormat$1(format);
          const size = lengths.signature;
          const sizer = format === 'compact' ? size : format === 'recovered' ? size + 1 : undefined;
          return abytes$3(bytes, sizer);
      }
      /**
       * ECDSA signature with its (r, s) properties. Supports compact, recovered & DER representations.
       */
      class Signature {
          r;
          s;
          recovery;
          constructor(r, s, recovery) {
              this.r = validateRS('r', r); // r in [1..N-1];
              this.s = validateRS('s', s); // s in [1..N-1];
              if (recovery != null) {
                  assertSmallCofactor();
                  if (![0, 1, 2, 3].includes(recovery))
                      throw new Error('invalid recovery id');
                  this.recovery = recovery;
              }
              Object.freeze(this);
          }
          static fromBytes(bytes, format = defaultSigOpts.format) {
              validateSigLength(bytes, format);
              let recid;
              if (format === 'der') {
                  const { r, s } = DER$2.toSig(abytes$3(bytes));
                  return new Signature(r, s);
              }
              if (format === 'recovered') {
                  recid = bytes[0];
                  format = 'compact';
                  bytes = bytes.subarray(1);
              }
              const L = lengths.signature / 2;
              const r = bytes.subarray(0, L);
              const s = bytes.subarray(L, L * 2);
              return new Signature(Fn.fromBytes(r), Fn.fromBytes(s), recid);
          }
          static fromHex(hex, format) {
              return this.fromBytes(hexToBytes$3(hex), format);
          }
          assertRecovery() {
              const { recovery } = this;
              if (recovery == null)
                  throw new Error('invalid recovery id: must be present');
              return recovery;
          }
          addRecoveryBit(recovery) {
              return new Signature(this.r, this.s, recovery);
          }
          recoverPublicKey(messageHash) {
              const { r, s } = this;
              const recovery = this.assertRecovery();
              const radj = recovery === 2 || recovery === 3 ? r + CURVE_ORDER : r;
              if (!Fp.isValid(radj))
                  throw new Error('invalid recovery id: sig.r+curve.n != R.x');
              const x = Fp.toBytes(radj);
              const R = Point.fromBytes(concatBytes$3(pprefix$1((recovery & 1) === 0), x));
              const ir = Fn.inv(radj); // r^-1
              const h = bits2int_modN(abytes$3(messageHash, undefined, 'msgHash')); // Truncate hash
              const u1 = Fn.create(-h * ir); // -hr^-1
              const u2 = Fn.create(s * ir); // sr^-1
              // (sr^-1)R-(hr^-1)G = -(hr^-1)G + (sr^-1). unsafe is fine: there is no private data.
              const Q = Point.BASE.multiplyUnsafe(u1).add(R.multiplyUnsafe(u2));
              if (Q.is0())
                  throw new Error('invalid recovery: point at infinify');
              Q.assertValidity();
              return Q;
          }
          // Signatures should be low-s, to prevent malleability.
          hasHighS() {
              return isBiggerThanHalfOrder(this.s);
          }
          toBytes(format = defaultSigOpts.format) {
              validateSigFormat$1(format);
              if (format === 'der')
                  return hexToBytes$3(DER$2.hexFromSig(this));
              const { r, s } = this;
              const rb = Fn.toBytes(r);
              const sb = Fn.toBytes(s);
              if (format === 'recovered') {
                  assertSmallCofactor();
                  return concatBytes$3(Uint8Array.of(this.assertRecovery()), rb, sb);
              }
              return concatBytes$3(rb, sb);
          }
          toHex(format) {
              return bytesToHex$5(this.toBytes(format));
          }
      }
      // RFC6979: ensure ECDSA msg is X bytes and < N. RFC suggests optional truncating via bits2octets.
      // FIPS 186-4 4.6 suggests the leftmost min(nBitLen, outLen) bits, which matches bits2int.
      // bits2int can produce res>N, we can do mod(res, N) since the bitLen is the same.
      // int2octets can't be used; pads small msgs with 0: unacceptatble for trunc as per RFC vectors
      const bits2int = ecdsaOpts.bits2int ||
          function bits2int_def(bytes) {
              // Our custom check "just in case", for protection against DoS
              if (bytes.length > 8192)
                  throw new Error('input is too large');
              // For curves with nBitLength % 8 !== 0: bits2octets(bits2octets(m)) !== bits2octets(m)
              // for some cases, since bytes.length * 8 is not actual bitLength.
              const num = bytesToNumberBE$3(bytes); // check for == u8 done here
              const delta = bytes.length * 8 - fnBits; // truncate to nBitLength leftmost bits
              return delta > 0 ? num >> BigInt(delta) : num;
          };
      const bits2int_modN = ecdsaOpts.bits2int_modN ||
          function bits2int_modN_def(bytes) {
              return Fn.create(bits2int(bytes)); // can't use bytesToNumberBE here
          };
      // Pads output with zero as per spec
      const ORDER_MASK = bitMask$2(fnBits);
      /** Converts to bytes. Checks if num in `[0..ORDER_MASK-1]` e.g.: `[0..2^256-1]`. */
      function int2octets(num) {
          // IMPORTANT: the check ensures working for case `Fn.BYTES != Fn.BITS * 8`
          aInRange$1('num < 2^' + fnBits, num, _0n$9, ORDER_MASK);
          return Fn.toBytes(num);
      }
      function validateMsgAndHash(message, prehash) {
          abytes$3(message, undefined, 'message');
          return prehash ? abytes$3(hash(message), undefined, 'prehashed message') : message;
      }
      /**
       * Steps A, D of RFC6979 3.2.
       * Creates RFC6979 seed; converts msg/privKey to numbers.
       * Used only in sign, not in verify.
       *
       * Warning: we cannot assume here that message has same amount of bytes as curve order,
       * this will be invalid at least for P521. Also it can be bigger for P224 + SHA256.
       */
      function prepSig(message, secretKey, opts) {
          const { lowS, prehash, extraEntropy } = validateSigOpts$1(opts, defaultSigOpts);
          message = validateMsgAndHash(message, prehash); // RFC6979 3.2 A: h1 = H(m)
          // We can't later call bits2octets, since nested bits2int is broken for curves
          // with fnBits % 8 !== 0. Because of that, we unwrap it here as int2octets call.
          // const bits2octets = (bits) => int2octets(bits2int_modN(bits))
          const h1int = bits2int_modN(message);
          const d = Fn.fromBytes(secretKey); // validate secret key, convert to bigint
          if (!Fn.isValidNot0(d))
              throw new Error('invalid private key');
          const seedArgs = [int2octets(d), int2octets(h1int)];
          // extraEntropy. RFC6979 3.6: additional k' (optional).
          if (extraEntropy != null && extraEntropy !== false) {
              // K = HMAC_K(V || 0x00 || int2octets(x) || bits2octets(h1) || k')
              // gen random bytes OR pass as-is
              const e = extraEntropy === true ? randomBytes(lengths.secretKey) : extraEntropy;
              seedArgs.push(abytes$3(e, undefined, 'extraEntropy')); // check for being bytes
          }
          const seed = concatBytes$3(...seedArgs); // Step D of RFC6979 3.2
          const m = h1int; // no need to call bits2int second time here, it is inside truncateHash!
          // Converts signature params into point w r/s, checks result for validity.
          // To transform k => Signature:
          // q = k⋅G
          // r = q.x mod n
          // s = k^-1(m + rd) mod n
          // Can use scalar blinding b^-1(bm + bdr) where b ∈ [1,q−1] according to
          // https://tches.iacr.org/index.php/TCHES/article/view/7337/6509. We've decided against it:
          // a) dependency on CSPRNG b) 15% slowdown c) doesn't really help since bigints are not CT
          function k2sig(kBytes) {
              // RFC 6979 Section 3.2, step 3: k = bits2int(T)
              // Important: all mod() calls here must be done over N
              const k = bits2int(kBytes); // Cannot use fields methods, since it is group element
              if (!Fn.isValidNot0(k))
                  return; // Valid scalars (including k) must be in 1..N-1
              const ik = Fn.inv(k); // k^-1 mod n
              const q = Point.BASE.multiply(k).toAffine(); // q = k⋅G
              const r = Fn.create(q.x); // r = q.x mod n
              if (r === _0n$9)
                  return;
              const s = Fn.create(ik * Fn.create(m + r * d)); // s = k^-1(m + rd) mod n
              if (s === _0n$9)
                  return;
              let recovery = (q.x === r ? 0 : 2) | Number(q.y & _1n$9); // recovery bit (2 or 3 when q.x>n)
              let normS = s;
              if (lowS && isBiggerThanHalfOrder(s)) {
                  normS = Fn.neg(s); // if lowS was passed, ensure s is always in the bottom half of N
                  recovery ^= 1;
              }
              return new Signature(r, normS, hasLargeCofactor ? undefined : recovery);
          }
          return { seed, k2sig };
      }
      /**
       * Signs message hash with a secret key.
       *
       * ```
       * sign(m, d) where
       *   k = rfc6979_hmac_drbg(m, d)
       *   (x, y) = G × k
       *   r = x mod n
       *   s = (m + dr) / k mod n
       * ```
       */
      function sign(message, secretKey, opts = {}) {
          const { seed, k2sig } = prepSig(message, secretKey, opts); // Steps A, D of RFC6979 3.2.
          const drbg = createHmacDrbg$2(hash.outputLen, Fn.BYTES, hmac);
          const sig = drbg(seed, k2sig); // Steps B, C, D, E, F, G
          return sig.toBytes(opts.format);
      }
      /**
       * Verifies a signature against message and public key.
       * Rejects lowS signatures by default: see {@link ECDSAVerifyOpts}.
       * Implements section 4.1.4 from https://www.secg.org/sec1-v2.pdf:
       *
       * ```
       * verify(r, s, h, P) where
       *   u1 = hs^-1 mod n
       *   u2 = rs^-1 mod n
       *   R = u1⋅G + u2⋅P
       *   mod(R.x, n) == r
       * ```
       */
      function verify(signature, message, publicKey, opts = {}) {
          const { lowS, prehash, format } = validateSigOpts$1(opts, defaultSigOpts);
          publicKey = abytes$3(publicKey, undefined, 'publicKey');
          message = validateMsgAndHash(message, prehash);
          if (!isBytes$3(signature)) {
              const end = signature instanceof Signature ? ', use sig.toBytes()' : '';
              throw new Error('verify expects Uint8Array signature' + end);
          }
          validateSigLength(signature, format); // execute this twice because we want loud error
          try {
              const sig = Signature.fromBytes(signature, format);
              const P = Point.fromBytes(publicKey);
              if (lowS && sig.hasHighS())
                  return false;
              const { r, s } = sig;
              const h = bits2int_modN(message); // mod n, not mod p
              const is = Fn.inv(s); // s^-1 mod n
              const u1 = Fn.create(h * is); // u1 = hs^-1 mod n
              const u2 = Fn.create(r * is); // u2 = rs^-1 mod n
              const R = Point.BASE.multiplyUnsafe(u1).add(P.multiplyUnsafe(u2)); // u1⋅G + u2⋅P
              if (R.is0())
                  return false;
              const v = Fn.create(R.x); // v = r.x mod n
              return v === r;
          }
          catch (e) {
              return false;
          }
      }
      function recoverPublicKey(signature, message, opts = {}) {
          const { prehash } = validateSigOpts$1(opts, defaultSigOpts);
          message = validateMsgAndHash(message, prehash);
          return Signature.fromBytes(signature, 'recovered').recoverPublicKey(message).toBytes();
      }
      return Object.freeze({
          keygen,
          getPublicKey,
          getSharedSecret,
          utils,
          lengths,
          Point,
          sign,
          verify,
          recoverPublicKey,
          Signature,
          hash,
      });
  }

  /**
   * SECG secp256k1. See [pdf](https://www.secg.org/sec2-v2.pdf).
   *
   * Belongs to Koblitz curves: it has efficiently-computable GLV endomorphism ψ,
   * check out {@link EndomorphismOpts}. Seems to be rigid (not backdoored).
   * @module
   */
  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // Seems like generator was produced from some seed:
  // `Pointk1.BASE.multiply(Pointk1.Fn.inv(2n, N)).toAffine().x`
  // // gives short x 0x3b78ce563f89a0ed9414f5aa28ad0d96d6795f9c63n
  const secp256k1_CURVE$1 = {
      p: BigInt('0xfffffffffffffffffffffffffffffffffffffffffffffffffffffffefffffc2f'),
      n: BigInt('0xfffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141'),
      h: BigInt(1),
      a: BigInt(0),
      b: BigInt(7),
      Gx: BigInt('0x79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798'),
      Gy: BigInt('0x483ada7726a3c4655da4fbfc0e1108a8fd17b448a68554199c47d08ffb10d4b8'),
  };
  const secp256k1_ENDO$1 = {
      beta: BigInt('0x7ae96a2b657c07106e64479eac3434e99cf0497512f58995c1396c28719501ee'),
      basises: [
          [BigInt('0x3086d221a7d46bcde86c90e49284eb15'), -BigInt('0xe4437ed6010e88286f547fa90abfe4c3')],
          [BigInt('0x114ca50f7a8e2f3f657c1108d9d44cfd8'), BigInt('0x3086d221a7d46bcde86c90e49284eb15')],
      ],
  };
  const _0n$8 = /* @__PURE__ */ BigInt(0);
  const _2n$6 = /* @__PURE__ */ BigInt(2);
  /**
   * √n = n^((p+1)/4) for fields p = 3 mod 4. We unwrap the loop and multiply bit-by-bit.
   * (P+1n/4n).toString(2) would produce bits [223x 1, 0, 22x 1, 4x 0, 11, 00]
   */
  function sqrtMod$2(y) {
      const P = secp256k1_CURVE$1.p;
      // prettier-ignore
      const _3n = BigInt(3), _6n = BigInt(6), _11n = BigInt(11), _22n = BigInt(22);
      // prettier-ignore
      const _23n = BigInt(23), _44n = BigInt(44), _88n = BigInt(88);
      const b2 = (y * y * y) % P; // x^3, 11
      const b3 = (b2 * b2 * y) % P; // x^7
      const b6 = (pow2$2(b3, _3n, P) * b3) % P;
      const b9 = (pow2$2(b6, _3n, P) * b3) % P;
      const b11 = (pow2$2(b9, _2n$6, P) * b2) % P;
      const b22 = (pow2$2(b11, _11n, P) * b11) % P;
      const b44 = (pow2$2(b22, _22n, P) * b22) % P;
      const b88 = (pow2$2(b44, _44n, P) * b44) % P;
      const b176 = (pow2$2(b88, _88n, P) * b88) % P;
      const b220 = (pow2$2(b176, _44n, P) * b44) % P;
      const b223 = (pow2$2(b220, _3n, P) * b3) % P;
      const t1 = (pow2$2(b223, _23n, P) * b22) % P;
      const t2 = (pow2$2(t1, _6n, P) * b2) % P;
      const root = pow2$2(t2, _2n$6, P);
      if (!Fpk1$1.eql(Fpk1$1.sqr(root), y))
          throw new Error('Cannot find square root');
      return root;
  }
  const Fpk1$1 = Field$2(secp256k1_CURVE$1.p, { sqrt: sqrtMod$2 });
  const Pointk1 = /* @__PURE__ */ weierstrass$2(secp256k1_CURVE$1, {
      Fp: Fpk1$1,
      endo: secp256k1_ENDO$1,
  });
  /**
   * secp256k1 curve: ECDSA and ECDH methods.
   *
   * Uses sha256 to hash messages. To use a different hash,
   * pass `{ prehash: false }` to sign / verify.
   *
   * @example
   * ```js
   * import { secp256k1 } from '@noble/curves/secp256k1.js';
   * const { secretKey, publicKey } = secp256k1.keygen();
   * // const publicKey = secp256k1.getPublicKey(secretKey);
   * const msg = new TextEncoder().encode('hello noble');
   * const sig = secp256k1.sign(msg, secretKey);
   * const isValid = secp256k1.verify(sig, msg, publicKey);
   * // const sigKeccak = secp256k1.sign(keccak256(msg), secretKey, { prehash: false });
   * ```
   */
  const secp256k1$2 = /* @__PURE__ */ ecdsa$1(Pointk1, sha256$3);
  // Schnorr signatures are superior to ECDSA from above. Below is Schnorr-specific BIP0340 code.
  // https://github.com/bitcoin/bips/blob/master/bip-0340.mediawiki
  /** An object mapping tags to their tagged hash prefix of [SHA256(tag) | SHA256(tag)] */
  const TAGGED_HASH_PREFIXES$1 = {};
  function taggedHash$1(tag, ...messages) {
      let tagP = TAGGED_HASH_PREFIXES$1[tag];
      if (tagP === undefined) {
          const tagH = sha256$3(asciiToBytes(tag));
          tagP = concatBytes$3(tagH, tagH);
          TAGGED_HASH_PREFIXES$1[tag] = tagP;
      }
      return sha256$3(concatBytes$3(tagP, ...messages));
  }
  // ECDSA compact points are 33-byte. Schnorr is 32: we strip first byte 0x02 or 0x03
  const pointToBytes$1 = (point) => point.toBytes(true).slice(1);
  const hasEven = (y) => y % _2n$6 === _0n$8;
  // Calculate point, scalar and bytes
  function schnorrGetExtPubKey$1(priv) {
      const { Fn, BASE } = Pointk1;
      const d_ = Fn.fromBytes(priv);
      const p = BASE.multiply(d_); // P = d'⋅G; 0 < d' < n check is done inside
      const scalar = hasEven(p.y) ? d_ : Fn.neg(d_);
      return { scalar, bytes: pointToBytes$1(p) };
  }
  /**
   * lift_x from BIP340. Convert 32-byte x coordinate to elliptic curve point.
   * @returns valid point checked for being on-curve
   */
  function lift_x$1(x) {
      const Fp = Fpk1$1;
      if (!Fp.isValidNot0(x))
          throw new Error('invalid x: Fail if x ≥ p');
      const xx = Fp.create(x * x);
      const c = Fp.create(xx * x + BigInt(7)); // Let c = x³ + 7 mod p.
      let y = Fp.sqrt(c); // Let y = c^(p+1)/4 mod p. Same as sqrt().
      // Return the unique point P such that x(P) = x and
      // y(P) = y if y mod 2 = 0 or y(P) = p-y otherwise.
      if (!hasEven(y))
          y = Fp.neg(y);
      const p = Pointk1.fromAffine({ x, y });
      p.assertValidity();
      return p;
  }
  const num = bytesToNumberBE$3;
  /**
   * Create tagged hash, convert it to bigint, reduce modulo-n.
   */
  function challenge$1(...args) {
      return Pointk1.Fn.create(num(taggedHash$1('BIP0340/challenge', ...args)));
  }
  /**
   * Schnorr public key is just `x` coordinate of Point as per BIP340.
   */
  function schnorrGetPublicKey$1(secretKey) {
      return schnorrGetExtPubKey$1(secretKey).bytes; // d'=int(sk). Fail if d'=0 or d'≥n. Ret bytes(d'⋅G)
  }
  /**
   * Creates Schnorr signature as per BIP340. Verifies itself before returning anything.
   * auxRand is optional and is not the sole source of k generation: bad CSPRNG won't be dangerous.
   */
  function schnorrSign$1(message, secretKey, auxRand = randomBytes$2(32)) {
      const { Fn } = Pointk1;
      const m = abytes$3(message, undefined, 'message');
      const { bytes: px, scalar: d } = schnorrGetExtPubKey$1(secretKey); // checks for isWithinCurveOrder
      const a = abytes$3(auxRand, 32, 'auxRand'); // Auxiliary random data a: a 32-byte array
      const t = Fn.toBytes(d ^ num(taggedHash$1('BIP0340/aux', a))); // Let t be the byte-wise xor of bytes(d) and hash/aux(a)
      const rand = taggedHash$1('BIP0340/nonce', t, px, m); // Let rand = hash/nonce(t || bytes(P) || m)
      // Let k' = int(rand) mod n. Fail if k' = 0. Let R = k'⋅G
      const { bytes: rx, scalar: k } = schnorrGetExtPubKey$1(rand);
      const e = challenge$1(rx, px, m); // Let e = int(hash/challenge(bytes(R) || bytes(P) || m)) mod n.
      const sig = new Uint8Array(64); // Let sig = bytes(R) || bytes((k + ed) mod n).
      sig.set(rx, 0);
      sig.set(Fn.toBytes(Fn.create(k + e * d)), 32);
      // If Verify(bytes(P), m, sig) (see below) returns failure, abort
      if (!schnorrVerify$1(sig, m, px))
          throw new Error('sign: Invalid signature produced');
      return sig;
  }
  /**
   * Verifies Schnorr signature.
   * Will swallow errors & return false except for initial type validation of arguments.
   */
  function schnorrVerify$1(signature, message, publicKey) {
      const { Fp, Fn, BASE } = Pointk1;
      const sig = abytes$3(signature, 64, 'signature');
      const m = abytes$3(message, undefined, 'message');
      const pub = abytes$3(publicKey, 32, 'publicKey');
      try {
          const P = lift_x$1(num(pub)); // P = lift_x(int(pk)); fail if that fails
          const r = num(sig.subarray(0, 32)); // Let r = int(sig[0:32]); fail if r ≥ p.
          if (!Fp.isValidNot0(r))
              return false;
          const s = num(sig.subarray(32, 64)); // Let s = int(sig[32:64]); fail if s ≥ n.
          if (!Fn.isValidNot0(s))
              return false;
          const e = challenge$1(Fn.toBytes(r), pointToBytes$1(P), m); // int(challenge(bytes(r)||bytes(P)||m))%n
          // R = s⋅G - e⋅P, where -eP == (n-e)P
          const R = BASE.multiplyUnsafe(s).add(P.multiplyUnsafe(Fn.neg(e)));
          const { x, y } = R.toAffine();
          // Fail if is_infinite(R) / not has_even_y(R) / x(R) ≠ r.
          if (R.is0() || !hasEven(y) || x !== r)
              return false;
          return true;
      }
      catch (error) {
          return false;
      }
  }
  /**
   * Schnorr signatures over secp256k1.
   * https://github.com/bitcoin/bips/blob/master/bip-0340.mediawiki
   * @example
   * ```js
   * import { schnorr } from '@noble/curves/secp256k1.js';
   * const { secretKey, publicKey } = schnorr.keygen();
   * // const publicKey = schnorr.getPublicKey(secretKey);
   * const msg = new TextEncoder().encode('hello');
   * const sig = schnorr.sign(msg, secretKey);
   * const isValid = schnorr.verify(sig, msg, publicKey);
   * ```
   */
  const schnorr$1 = /* @__PURE__ */ (() => {
      const size = 32;
      const seedLength = 48;
      const randomSecretKey = (seed = randomBytes$2(seedLength)) => {
          return mapHashToField$2(seed, secp256k1_CURVE$1.n);
      };
      return {
          keygen: createKeygen(randomSecretKey, schnorrGetPublicKey$1),
          getPublicKey: schnorrGetPublicKey$1,
          sign: schnorrSign$1,
          verify: schnorrVerify$1,
          Point: Pointk1,
          utils: {
              randomSecretKey,
              taggedHash: taggedHash$1,
              lift_x: lift_x$1,
              pointToBytes: pointToBytes$1,
          },
          lengths: {
              secretKey: size,
              publicKey: size,
              publicKeyHasPrefix: false,
              signature: size * 2,
              seed: seedLength,
          },
      };
  })();

  // pure.ts

  // core.ts
  var verifiedSymbol$3 = Symbol("verified");
  var isRecord$3 = (obj) => obj instanceof Object;
  function validateEvent$3(event) {
    if (!isRecord$3(event))
      return false;
    if (typeof event.kind !== "number")
      return false;
    if (typeof event.content !== "string")
      return false;
    if (typeof event.created_at !== "number")
      return false;
    if (typeof event.pubkey !== "string")
      return false;
    if (!event.pubkey.match(/^[a-f0-9]{64}$/))
      return false;
    if (!Array.isArray(event.tags))
      return false;
    for (let i2 = 0; i2 < event.tags.length; i2++) {
      let tag = event.tags[i2];
      if (!Array.isArray(tag))
        return false;
      for (let j = 0; j < tag.length; j++) {
        if (typeof tag[j] !== "string")
          return false;
      }
    }
    return true;
  }
  new TextDecoder("utf-8");
  var utf8Encoder$3 = new TextEncoder();

  // pure.ts
  var JS$3 = class JS {
    generateSecretKey() {
      return schnorr$1.utils.randomSecretKey();
    }
    getPublicKey(secretKey) {
      return bytesToHex$5(schnorr$1.getPublicKey(secretKey));
    }
    finalizeEvent(t, secretKey) {
      const event = t;
      event.pubkey = bytesToHex$5(schnorr$1.getPublicKey(secretKey));
      event.id = getEventHash$3(event);
      event.sig = bytesToHex$5(schnorr$1.sign(hexToBytes$3(getEventHash$3(event)), secretKey));
      event[verifiedSymbol$3] = true;
      return event;
    }
    verifyEvent(event) {
      if (typeof event[verifiedSymbol$3] === "boolean")
        return event[verifiedSymbol$3];
      try {
        const hash = getEventHash$3(event);
        if (hash !== event.id) {
          event[verifiedSymbol$3] = false;
          return false;
        }
        const valid = schnorr$1.verify(hexToBytes$3(event.sig), hexToBytes$3(hash), hexToBytes$3(event.pubkey));
        event[verifiedSymbol$3] = valid;
        return valid;
      } catch (err) {
        event[verifiedSymbol$3] = false;
        return false;
      }
    }
  };
  function serializeEvent$3(evt) {
    if (!validateEvent$3(evt))
      throw new Error("can't serialize event with wrong or missing properties");
    return JSON.stringify([0, evt.pubkey, evt.created_at, evt.kind, evt.tags, evt.content]);
  }
  function getEventHash$3(event) {
    let eventHash = sha256$3(utf8Encoder$3.encode(serializeEvent$3(event)));
    return bytesToHex$5(eventHash);
  }
  var i$3 = new JS$3();
  var generateSecretKey$1 = i$3.generateSecretKey;
  var getPublicKey$1 = i$3.getPublicKey;
  var finalizeEvent$1 = i$3.finalizeEvent;
  i$3.verifyEvent;

  /*! scure-base - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  function isBytes$2(a) {
      return a instanceof Uint8Array || (ArrayBuffer.isView(a) && a.constructor.name === 'Uint8Array');
  }
  /** Asserts something is Uint8Array. */
  function abytes$2(b) {
      if (!isBytes$2(b))
          throw new Error('Uint8Array expected');
  }
  function isArrayOf(isString, arr) {
      if (!Array.isArray(arr))
          return false;
      if (arr.length === 0)
          return true;
      if (isString) {
          return arr.every((item) => typeof item === 'string');
      }
      else {
          return arr.every((item) => Number.isSafeInteger(item));
      }
  }
  function afn(input) {
      if (typeof input !== 'function')
          throw new Error('function expected');
      return true;
  }
  function astr(label, input) {
      if (typeof input !== 'string')
          throw new Error(`${label}: string expected`);
      return true;
  }
  function anumber$2(n) {
      if (!Number.isSafeInteger(n))
          throw new Error(`invalid integer: ${n}`);
  }
  function aArr(input) {
      if (!Array.isArray(input))
          throw new Error('array expected');
  }
  function astrArr(label, input) {
      if (!isArrayOf(true, input))
          throw new Error(`${label}: array of strings expected`);
  }
  function anumArr(label, input) {
      if (!isArrayOf(false, input))
          throw new Error(`${label}: array of numbers expected`);
  }
  /**
   * @__NO_SIDE_EFFECTS__
   */
  function chain$1(...args) {
      const id = (a) => a;
      // Wrap call in closure so JIT can inline calls
      const wrap = (a, b) => (c) => a(b(c));
      // Construct chain of args[-1].encode(args[-2].encode([...]))
      const encode = args.map((x) => x.encode).reduceRight(wrap, id);
      // Construct chain of args[0].decode(args[1].decode(...))
      const decode = args.map((x) => x.decode).reduce(wrap, id);
      return { encode, decode };
  }
  /**
   * Encodes integer radix representation to array of strings using alphabet and back.
   * Could also be array of strings.
   * @__NO_SIDE_EFFECTS__
   */
  function alphabet$1(letters) {
      // mapping 1 to "b"
      const lettersA = typeof letters === 'string' ? letters.split('') : letters;
      const len = lettersA.length;
      astrArr('alphabet', lettersA);
      // mapping "b" to 1
      const indexes = new Map(lettersA.map((l, i) => [l, i]));
      return {
          encode: (digits) => {
              aArr(digits);
              return digits.map((i) => {
                  if (!Number.isSafeInteger(i) || i < 0 || i >= len)
                      throw new Error(`alphabet.encode: digit index outside alphabet "${i}". Allowed: ${letters}`);
                  return lettersA[i];
              });
          },
          decode: (input) => {
              aArr(input);
              return input.map((letter) => {
                  astr('alphabet.decode', letter);
                  const i = indexes.get(letter);
                  if (i === undefined)
                      throw new Error(`Unknown letter: "${letter}". Allowed: ${letters}`);
                  return i;
              });
          },
      };
  }
  /**
   * @__NO_SIDE_EFFECTS__
   */
  function join$1(separator = '') {
      astr('join', separator);
      return {
          encode: (from) => {
              astrArr('join.decode', from);
              return from.join(separator);
          },
          decode: (to) => {
              astr('join.decode', to);
              return to.split(separator);
          },
      };
  }
  /**
   * Pad strings array so it has integer number of bits
   * @__NO_SIDE_EFFECTS__
   */
  function padding$1(bits, chr = '=') {
      anumber$2(bits);
      astr('padding', chr);
      return {
          encode(data) {
              astrArr('padding.encode', data);
              while ((data.length * bits) % 8)
                  data.push(chr);
              return data;
          },
          decode(input) {
              astrArr('padding.decode', input);
              let end = input.length;
              if ((end * bits) % 8)
                  throw new Error('padding: invalid, string should have whole number of bytes');
              for (; end > 0 && input[end - 1] === chr; end--) {
                  const last = end - 1;
                  const byte = last * bits;
                  if (byte % 8 === 0)
                      throw new Error('padding: invalid, string has too much padding');
              }
              return input.slice(0, end);
          },
      };
  }
  const gcd$1 = (a, b) => (b === 0 ? a : gcd$1(b, a % b));
  const radix2carry$1 = /* @__NO_SIDE_EFFECTS__ */ (from, to) => from + (to - gcd$1(from, to));
  const powers = /* @__PURE__ */ (() => {
      let res = [];
      for (let i = 0; i < 40; i++)
          res.push(2 ** i);
      return res;
  })();
  /**
   * Implemented with numbers, because BigInt is 5x slower
   */
  function convertRadix2$1(data, from, to, padding) {
      aArr(data);
      if (from <= 0 || from > 32)
          throw new Error(`convertRadix2: wrong from=${from}`);
      if (to <= 0 || to > 32)
          throw new Error(`convertRadix2: wrong to=${to}`);
      if (radix2carry$1(from, to) > 32) {
          throw new Error(`convertRadix2: carry overflow from=${from} to=${to} carryBits=${radix2carry$1(from, to)}`);
      }
      let carry = 0;
      let pos = 0; // bitwise position in current element
      const max = powers[from];
      const mask = powers[to] - 1;
      const res = [];
      for (const n of data) {
          anumber$2(n);
          if (n >= max)
              throw new Error(`convertRadix2: invalid data word=${n} from=${from}`);
          carry = (carry << from) | n;
          if (pos + from > 32)
              throw new Error(`convertRadix2: carry overflow pos=${pos} from=${from}`);
          pos += from;
          for (; pos >= to; pos -= to)
              res.push(((carry >> (pos - to)) & mask) >>> 0);
          const pow = powers[pos];
          if (pow === undefined)
              throw new Error('invalid carry');
          carry &= pow - 1; // clean carry, otherwise it will cause overflow
      }
      carry = (carry << (to - pos)) & mask;
      if (!padding && pos >= from)
          throw new Error('Excess padding');
      if (!padding && carry > 0)
          throw new Error(`Non-zero padding: ${carry}`);
      if (padding && pos > 0)
          res.push(carry >>> 0);
      return res;
  }
  /**
   * If both bases are power of same number (like `2**8 <-> 2**64`),
   * there is a linear algorithm. For now we have implementation for power-of-two bases only.
   * @__NO_SIDE_EFFECTS__
   */
  function radix2$1(bits, revPadding = false) {
      anumber$2(bits);
      if (bits <= 0 || bits > 32)
          throw new Error('radix2: bits should be in (0..32]');
      if (radix2carry$1(8, bits) > 32 || radix2carry$1(bits, 8) > 32)
          throw new Error('radix2: carry overflow');
      return {
          encode: (bytes) => {
              if (!isBytes$2(bytes))
                  throw new Error('radix2.encode input should be Uint8Array');
              return convertRadix2$1(Array.from(bytes), 8, bits, !revPadding);
          },
          decode: (digits) => {
              anumArr('radix2.decode', digits);
              return Uint8Array.from(convertRadix2$1(digits, bits, 8, revPadding));
          },
      };
  }
  function unsafeWrapper$1(fn) {
      afn(fn);
      return function (...args) {
          try {
              return fn.apply(null, args);
          }
          catch (e) { }
      };
  }
  // Built-in base64 conversion https://caniuse.com/mdn-javascript_builtins_uint8array_frombase64
  // prettier-ignore
  const hasBase64Builtin = /* @__PURE__ */ (() => typeof Uint8Array.from([]).toBase64 === 'function' &&
      typeof Uint8Array.fromBase64 === 'function')();
  const decodeBase64Builtin = (s, isUrl) => {
      astr('base64', s);
      const re = /^[A-Za-z0-9=+/]+$/;
      const alphabet = 'base64';
      if (s.length > 0 && !re.test(s))
          throw new Error('invalid base64');
      return Uint8Array.fromBase64(s, { alphabet, lastChunkHandling: 'strict' });
  };
  /**
   * base64 from RFC 4648. Padded.
   * Use `base64nopad` for unpadded version.
   * Also check out `base64url`, `base64urlnopad`.
   * Falls back to built-in function, when available.
   * @example
   * ```js
   * base64.encode(Uint8Array.from([0x12, 0xab]));
   * // => 'Eqs='
   * base64.decode('Eqs=');
   * // => Uint8Array.from([0x12, 0xab])
   * ```
   */
  // prettier-ignore
  const base64$1 = hasBase64Builtin ? {
      encode(b) { abytes$2(b); return b.toBase64(); },
      decode(s) { return decodeBase64Builtin(s); },
  } : chain$1(radix2$1(6), alphabet$1('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'), padding$1(6), join$1(''));
  const BECH_ALPHABET$1 = chain$1(alphabet$1('qpzry9x8gf2tvdw0s3jn54khce6mua7l'), join$1(''));
  const POLYMOD_GENERATORS$1 = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
  function bech32Polymod$1(pre) {
      const b = pre >> 25;
      let chk = (pre & 0x1ffffff) << 5;
      for (let i = 0; i < POLYMOD_GENERATORS$1.length; i++) {
          if (((b >> i) & 1) === 1)
              chk ^= POLYMOD_GENERATORS$1[i];
      }
      return chk;
  }
  function bechChecksum$1(prefix, words, encodingConst = 1) {
      const len = prefix.length;
      let chk = 1;
      for (let i = 0; i < len; i++) {
          const c = prefix.charCodeAt(i);
          if (c < 33 || c > 126)
              throw new Error(`Invalid prefix (${prefix})`);
          chk = bech32Polymod$1(chk) ^ (c >> 5);
      }
      chk = bech32Polymod$1(chk);
      for (let i = 0; i < len; i++)
          chk = bech32Polymod$1(chk) ^ (prefix.charCodeAt(i) & 0x1f);
      for (let v of words)
          chk = bech32Polymod$1(chk) ^ v;
      for (let i = 0; i < 6; i++)
          chk = bech32Polymod$1(chk);
      chk ^= encodingConst;
      return BECH_ALPHABET$1.encode(convertRadix2$1([chk % powers[30]], 30, 5, false));
  }
  /**
   * @__NO_SIDE_EFFECTS__
   */
  function genBech32$1(encoding) {
      const ENCODING_CONST = encoding === 'bech32' ? 1 : 0x2bc830a3;
      const _words = radix2$1(5);
      const fromWords = _words.decode;
      const toWords = _words.encode;
      const fromWordsUnsafe = unsafeWrapper$1(fromWords);
      function encode(prefix, words, limit = 90) {
          astr('bech32.encode prefix', prefix);
          if (isBytes$2(words))
              words = Array.from(words);
          anumArr('bech32.encode', words);
          const plen = prefix.length;
          if (plen === 0)
              throw new TypeError(`Invalid prefix length ${plen}`);
          const actualLength = plen + 7 + words.length;
          if (limit !== false && actualLength > limit)
              throw new TypeError(`Length ${actualLength} exceeds limit ${limit}`);
          const lowered = prefix.toLowerCase();
          const sum = bechChecksum$1(lowered, words, ENCODING_CONST);
          return `${lowered}1${BECH_ALPHABET$1.encode(words)}${sum}`;
      }
      function decode(str, limit = 90) {
          astr('bech32.decode input', str);
          const slen = str.length;
          if (slen < 8 || (limit !== false && slen > limit))
              throw new TypeError(`invalid string length: ${slen} (${str}). Expected (8..${limit})`);
          // don't allow mixed case
          const lowered = str.toLowerCase();
          if (str !== lowered && str !== str.toUpperCase())
              throw new Error(`String must be lowercase or uppercase`);
          const sepIndex = lowered.lastIndexOf('1');
          if (sepIndex === 0 || sepIndex === -1)
              throw new Error(`Letter "1" must be present between prefix and data only`);
          const prefix = lowered.slice(0, sepIndex);
          const data = lowered.slice(sepIndex + 1);
          if (data.length < 6)
              throw new Error('Data must be at least 6 characters long');
          const words = BECH_ALPHABET$1.decode(data).slice(0, -6);
          const sum = bechChecksum$1(prefix, words, ENCODING_CONST);
          if (!data.endsWith(sum))
              throw new Error(`Invalid checksum in ${str}: expected "${sum}"`);
          return { prefix, words };
      }
      const decodeUnsafe = unsafeWrapper$1(decode);
      function decodeToBytes(str) {
          const { prefix, words } = decode(str, false);
          return { prefix, words, bytes: fromWords(words) };
      }
      function encodeFromBytes(prefix, bytes) {
          return encode(prefix, toWords(bytes));
      }
      return {
          encode,
          decode,
          encodeFromBytes,
          decodeToBytes,
          decodeUnsafe,
          fromWords,
          fromWordsUnsafe,
          toWords,
      };
  }
  /**
   * bech32 from BIP 173. Operates on words.
   * For high-level, check out scure-btc-signer:
   * https://github.com/paulmillr/scure-btc-signer.
   */
  const bech32 = genBech32$1('bech32');

  // nip19.ts
  var utf8Decoder$1 = new TextDecoder("utf-8");
  new TextEncoder();
  var Bech32MaxSize$2 = 5e3;
  function decode$1(code) {
    let { prefix, words } = bech32.decode(code, Bech32MaxSize$2);
    let data = new Uint8Array(bech32.fromWords(words));
    switch (prefix) {
      case "nprofile": {
        let tlv = parseTLV$1(data);
        if (!tlv[0]?.[0])
          throw new Error("missing TLV 0 for nprofile");
        if (tlv[0][0].length !== 32)
          throw new Error("TLV 0 should be 32 bytes");
        return {
          type: "nprofile",
          data: {
            pubkey: bytesToHex$5(tlv[0][0]),
            relays: tlv[1] ? tlv[1].map((d) => utf8Decoder$1.decode(d)) : []
          }
        };
      }
      case "nevent": {
        let tlv = parseTLV$1(data);
        if (!tlv[0]?.[0])
          throw new Error("missing TLV 0 for nevent");
        if (tlv[0][0].length !== 32)
          throw new Error("TLV 0 should be 32 bytes");
        if (tlv[2] && tlv[2][0].length !== 32)
          throw new Error("TLV 2 should be 32 bytes");
        if (tlv[3] && tlv[3][0].length !== 4)
          throw new Error("TLV 3 should be 4 bytes");
        return {
          type: "nevent",
          data: {
            id: bytesToHex$5(tlv[0][0]),
            relays: tlv[1] ? tlv[1].map((d) => utf8Decoder$1.decode(d)) : [],
            author: tlv[2]?.[0] ? bytesToHex$5(tlv[2][0]) : void 0,
            kind: tlv[3]?.[0] ? parseInt(bytesToHex$5(tlv[3][0]), 16) : void 0
          }
        };
      }
      case "naddr": {
        let tlv = parseTLV$1(data);
        if (!tlv[0]?.[0])
          throw new Error("missing TLV 0 for naddr");
        if (!tlv[2]?.[0])
          throw new Error("missing TLV 2 for naddr");
        if (tlv[2][0].length !== 32)
          throw new Error("TLV 2 should be 32 bytes");
        if (!tlv[3]?.[0])
          throw new Error("missing TLV 3 for naddr");
        if (tlv[3][0].length !== 4)
          throw new Error("TLV 3 should be 4 bytes");
        return {
          type: "naddr",
          data: {
            identifier: utf8Decoder$1.decode(tlv[0][0]),
            pubkey: bytesToHex$5(tlv[2][0]),
            kind: parseInt(bytesToHex$5(tlv[3][0]), 16),
            relays: tlv[1] ? tlv[1].map((d) => utf8Decoder$1.decode(d)) : []
          }
        };
      }
      case "nsec":
        return { type: prefix, data };
      case "npub":
      case "note":
        return { type: prefix, data: bytesToHex$5(data) };
      default:
        throw new Error(`unknown prefix ${prefix}`);
    }
  }
  function parseTLV$1(data) {
    let result = {};
    let rest = data;
    while (rest.length > 0) {
      let t = rest[0];
      let l = rest[1];
      let v = rest.slice(2, 2 + l);
      rest = rest.slice(2 + l);
      if (v.length < l)
        throw new Error(`not enough data to read on TLV ${t}`);
      result[t] = result[t] || [];
      result[t].push(v);
    }
    return result;
  }
  function nsecEncode$1(key) {
    return encodeBytes$2("nsec", key);
  }
  function npubEncode$1(hex) {
    return encodeBytes$2("npub", hexToBytes$3(hex));
  }
  function encodeBech32$2(prefix, data) {
    let words = bech32.toWords(data);
    return bech32.encode(prefix, words, Bech32MaxSize$2);
  }
  function encodeBytes$2(prefix, bytes) {
    return encodeBech32$2(prefix, bytes);
  }

  /**
   * MILL — crypto.js
   * Real key encoding + AES-256-GCM session encryption.
   * Requires nostr-tools (bundled in UMD, peer dep otherwise).
   */


  // ── Hex helpers ───────────────────────────────────────────────────────────────
  function hexToBytes$2(hex) {
    if (hex.length % 2) throw new Error('Odd-length hex');
    const bytes = new Uint8Array(hex.length / 2);
    for (let i = 0; i < hex.length; i += 2)
      bytes[i / 2] = parseInt(hex.slice(i, i + 2), 16);
    return bytes;
  }

  function bytesToHex$4(bytes) {
    return [...bytes].map(b => b.toString(16).padStart(2, '0')).join('');
  }

  // ── nsec / npub via nostr-tools nip19 (checksum-validated) ────────────────────
  function nsecToHex(nsec) {
    if (/^[0-9a-f]{64}$/i.test(nsec)) return nsec.toLowerCase();
    const decoded = decode$1(nsec.trim());
    if (decoded.type !== 'nsec') throw new Error('Not an nsec');
    return bytesToHex$4(decoded.data);
  }

  function npubToHex(npub) {
    if (/^[0-9a-f]{64}$/i.test(npub)) return npub.toLowerCase();
    const decoded = decode$1(npub.trim());
    if (decoded.type !== 'npub') throw new Error('Not an npub');
    return decoded.data;
  }

  function hexToNsec(hex) { return nsecEncode$1(hexToBytes$2(hex)); }
  function hexToNpub(hex) { return npubEncode$1(hex); }

  function isValidNsec(v) {
    if (!v) return false;
    if (/^[0-9a-f]{64}$/i.test(v)) return true;
    try { return decode$1(v.trim()).type === 'nsec'; } catch { return false; }
  }

  function isValidNpub(v) {
    if (!v) return false;
    if (/^[0-9a-f]{64}$/i.test(v)) return true;
    try { return decode$1(v.trim()).type === 'npub'; } catch { return false; }
  }

  function isValidBunker(v) {
    return typeof v === 'string' && (/^bunker:\/\//.test(v) || /^nostrconnect:\/\//.test(v));
  }

  // ── Real keypair generation (secp256k1 via nostr-tools) ───────────────────────
  async function generateKeypair() {
    const privBytes = generateSecretKey$1();           // Uint8Array(32)
    const privHex   = bytesToHex$4(privBytes);
    const pubHex    = getPublicKey$1(privBytes);       // real schnorr x-only pubkey
    return {
      privBytes,
      privHex,
      pubHex,
      nsec: nsecEncode$1(privBytes),
      npub: npubEncode$1(pubHex),
    };
  }

  // ── AES-256-GCM session encryption for nsec at rest ───────────────────────────
  async function deriveKey(password, salt) {
    const enc = new TextEncoder();
    const keyMat = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveKey']);
    return crypto.subtle.deriveKey(
      { name: 'PBKDF2', salt, iterations: 100_000, hash: 'SHA-256' },
      keyMat, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']
    );
  }

  async function encryptNsec(nsecHex, password) {
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const iv   = crypto.getRandomValues(new Uint8Array(12));
    const key  = await deriveKey(password, salt);
    const ct   = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv }, key, new TextEncoder().encode(nsecHex)
    );
    const out = new Uint8Array(16 + 12 + ct.byteLength);
    out.set(salt, 0); out.set(iv, 16); out.set(new Uint8Array(ct), 28);
    return btoa(String.fromCharCode(...out));
  }

  async function decryptNsec(b64, password) {
    const raw  = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
    const salt = raw.slice(0, 16);
    const iv   = raw.slice(16, 28);
    const ct   = raw.slice(28);
    const key  = await deriveKey(password, salt);
    const pt   = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, ct);
    return new TextDecoder().decode(pt);
  }

  const STORAGE_KEY$1 = 'mill:nsec:enc';
  function storeEncryptedNsec(encrypted) { sessionStorage.setItem(STORAGE_KEY$1, encrypted); }
  function loadEncryptedNsec()           { return sessionStorage.getItem(STORAGE_KEY$1); }
  function clearStoredNsec()             { sessionStorage.removeItem(STORAGE_KEY$1); }

  // ── Restore state (sessionStorage; wiped on tab close, same as the nsec blob) ──
  //
  // Persisted at login so MILL.restore() can rebuild a signer after a page reload
  // without re-opening the picker. Nothing here is the user's private key:
  //   - perms: the private-key signing-permission map (user's choices)
  //   - bunker: the NIP-46 client identity + remote pubkey/relays. The client
  //     secret is mill's own connection key, NOT the user's nsec, so persisting
  //     it only lets us re-present the same already-authorized client to the
  //     bunker. The user's key never leaves their bunker.

  const PERMS_KEY = 'mill:perms';
  function storeSignPerms(perms) { sessionStorage.setItem(PERMS_KEY, JSON.stringify(perms)); }
  function loadSignPerms()       { const s = sessionStorage.getItem(PERMS_KEY); try { return s ? JSON.parse(s) : null; } catch { return null; } }
  function clearSignPerms()      { sessionStorage.removeItem(PERMS_KEY); }

  const BUNKER_KEY = 'mill:nip46:state';
  function storeBunkerState(state) { sessionStorage.setItem(BUNKER_KEY, JSON.stringify(state)); }
  function loadBunkerState()       { const s = sessionStorage.getItem(BUNKER_KEY); try { return s ? JSON.parse(s) : null; } catch { return null; } }
  function clearBunkerState()      { sessionStorage.removeItem(BUNKER_KEY); }

  //---------------------------------------------------------------------
  //
  // QR Code Generator for JavaScript
  //
  // Copyright (c) 2009 Kazuhiko Arase
  //
  // URL: http://www.d-project.com/
  //
  // Licensed under the MIT license:
  //  http://www.opensource.org/licenses/mit-license.php
  //
  // The word 'QR Code' is registered trademark of
  // DENSO WAVE INCORPORATED
  //  http://www.denso-wave.com/qrcode/faqpatent-e.html
  //
  //---------------------------------------------------------------------

  //---------------------------------------------------------------------
  // qrcode
  //---------------------------------------------------------------------

  /**
   * qrcode
   * @param typeNumber 1 to 40
   * @param errorCorrectionLevel 'L','M','Q','H'
   */
  const qrcode = function(typeNumber, errorCorrectionLevel) {

    const PAD0 = 0xEC;
    const PAD1 = 0x11;

    let _typeNumber = typeNumber;
    const _errorCorrectionLevel = QRErrorCorrectionLevel[errorCorrectionLevel];
    let _modules = null;
    let _moduleCount = 0;
    let _dataCache = null;
    const _dataList = [];

    const _this = {};

    const makeImpl = function(test, maskPattern) {

      _moduleCount = _typeNumber * 4 + 17;
      _modules = function(moduleCount) {
        const modules = new Array(moduleCount);
        for (let row = 0; row < moduleCount; row += 1) {
          modules[row] = new Array(moduleCount);
          for (let col = 0; col < moduleCount; col += 1) {
            modules[row][col] = null;
          }
        }
        return modules;
      }(_moduleCount);

      setupPositionProbePattern(0, 0);
      setupPositionProbePattern(_moduleCount - 7, 0);
      setupPositionProbePattern(0, _moduleCount - 7);
      setupPositionAdjustPattern();
      setupTimingPattern();
      setupTypeInfo(test, maskPattern);

      if (_typeNumber >= 7) {
        setupTypeNumber(test);
      }

      if (_dataCache == null) {
        _dataCache = createData(_typeNumber, _errorCorrectionLevel, _dataList);
      }

      mapData(_dataCache, maskPattern);
    };

    const setupPositionProbePattern = function(row, col) {

      for (let r = -1; r <= 7; r += 1) {

        if (row + r <= -1 || _moduleCount <= row + r) continue;

        for (let c = -1; c <= 7; c += 1) {

          if (col + c <= -1 || _moduleCount <= col + c) continue;

          if ( (0 <= r && r <= 6 && (c == 0 || c == 6) )
              || (0 <= c && c <= 6 && (r == 0 || r == 6) )
              || (2 <= r && r <= 4 && 2 <= c && c <= 4) ) {
            _modules[row + r][col + c] = true;
          } else {
            _modules[row + r][col + c] = false;
          }
        }
      }
    };

    const getBestMaskPattern = function() {

      let minLostPoint = 0;
      let pattern = 0;

      for (let i = 0; i < 8; i += 1) {

        makeImpl(true, i);

        const lostPoint = QRUtil.getLostPoint(_this);

        if (i == 0 || minLostPoint > lostPoint) {
          minLostPoint = lostPoint;
          pattern = i;
        }
      }

      return pattern;
    };

    const setupTimingPattern = function() {

      for (let r = 8; r < _moduleCount - 8; r += 1) {
        if (_modules[r][6] != null) {
          continue;
        }
        _modules[r][6] = (r % 2 == 0);
      }

      for (let c = 8; c < _moduleCount - 8; c += 1) {
        if (_modules[6][c] != null) {
          continue;
        }
        _modules[6][c] = (c % 2 == 0);
      }
    };

    const setupPositionAdjustPattern = function() {

      const pos = QRUtil.getPatternPosition(_typeNumber);

      for (let i = 0; i < pos.length; i += 1) {

        for (let j = 0; j < pos.length; j += 1) {

          const row = pos[i];
          const col = pos[j];

          if (_modules[row][col] != null) {
            continue;
          }

          for (let r = -2; r <= 2; r += 1) {

            for (let c = -2; c <= 2; c += 1) {

              if (r == -2 || r == 2 || c == -2 || c == 2
                  || (r == 0 && c == 0) ) {
                _modules[row + r][col + c] = true;
              } else {
                _modules[row + r][col + c] = false;
              }
            }
          }
        }
      }
    };

    const setupTypeNumber = function(test) {

      const bits = QRUtil.getBCHTypeNumber(_typeNumber);

      for (let i = 0; i < 18; i += 1) {
        const mod = (!test && ( (bits >> i) & 1) == 1);
        _modules[Math.floor(i / 3)][i % 3 + _moduleCount - 8 - 3] = mod;
      }

      for (let i = 0; i < 18; i += 1) {
        const mod = (!test && ( (bits >> i) & 1) == 1);
        _modules[i % 3 + _moduleCount - 8 - 3][Math.floor(i / 3)] = mod;
      }
    };

    const setupTypeInfo = function(test, maskPattern) {

      const data = (_errorCorrectionLevel << 3) | maskPattern;
      const bits = QRUtil.getBCHTypeInfo(data);

      // vertical
      for (let i = 0; i < 15; i += 1) {

        const mod = (!test && ( (bits >> i) & 1) == 1);

        if (i < 6) {
          _modules[i][8] = mod;
        } else if (i < 8) {
          _modules[i + 1][8] = mod;
        } else {
          _modules[_moduleCount - 15 + i][8] = mod;
        }
      }

      // horizontal
      for (let i = 0; i < 15; i += 1) {

        const mod = (!test && ( (bits >> i) & 1) == 1);

        if (i < 8) {
          _modules[8][_moduleCount - i - 1] = mod;
        } else if (i < 9) {
          _modules[8][15 - i - 1 + 1] = mod;
        } else {
          _modules[8][15 - i - 1] = mod;
        }
      }

      // fixed module
      _modules[_moduleCount - 8][8] = (!test);
    };

    const mapData = function(data, maskPattern) {

      let inc = -1;
      let row = _moduleCount - 1;
      let bitIndex = 7;
      let byteIndex = 0;
      const maskFunc = QRUtil.getMaskFunction(maskPattern);

      for (let col = _moduleCount - 1; col > 0; col -= 2) {

        if (col == 6) col -= 1;

        while (true) {

          for (let c = 0; c < 2; c += 1) {

            if (_modules[row][col - c] == null) {

              let dark = false;

              if (byteIndex < data.length) {
                dark = ( ( (data[byteIndex] >>> bitIndex) & 1) == 1);
              }

              const mask = maskFunc(row, col - c);

              if (mask) {
                dark = !dark;
              }

              _modules[row][col - c] = dark;
              bitIndex -= 1;

              if (bitIndex == -1) {
                byteIndex += 1;
                bitIndex = 7;
              }
            }
          }

          row += inc;

          if (row < 0 || _moduleCount <= row) {
            row -= inc;
            inc = -inc;
            break;
          }
        }
      }
    };

    const createBytes = function(buffer, rsBlocks) {

      let offset = 0;

      let maxDcCount = 0;
      let maxEcCount = 0;

      const dcdata = new Array(rsBlocks.length);
      const ecdata = new Array(rsBlocks.length);

      for (let r = 0; r < rsBlocks.length; r += 1) {

        const dcCount = rsBlocks[r].dataCount;
        const ecCount = rsBlocks[r].totalCount - dcCount;

        maxDcCount = Math.max(maxDcCount, dcCount);
        maxEcCount = Math.max(maxEcCount, ecCount);

        dcdata[r] = new Array(dcCount);

        for (let i = 0; i < dcdata[r].length; i += 1) {
          dcdata[r][i] = 0xff & buffer.getBuffer()[i + offset];
        }
        offset += dcCount;

        const rsPoly = QRUtil.getErrorCorrectPolynomial(ecCount);
        const rawPoly = qrPolynomial(dcdata[r], rsPoly.getLength() - 1);

        const modPoly = rawPoly.mod(rsPoly);
        ecdata[r] = new Array(rsPoly.getLength() - 1);
        for (let i = 0; i < ecdata[r].length; i += 1) {
          const modIndex = i + modPoly.getLength() - ecdata[r].length;
          ecdata[r][i] = (modIndex >= 0)? modPoly.getAt(modIndex) : 0;
        }
      }

      let totalCodeCount = 0;
      for (let i = 0; i < rsBlocks.length; i += 1) {
        totalCodeCount += rsBlocks[i].totalCount;
      }

      const data = new Array(totalCodeCount);
      let index = 0;

      for (let i = 0; i < maxDcCount; i += 1) {
        for (let r = 0; r < rsBlocks.length; r += 1) {
          if (i < dcdata[r].length) {
            data[index] = dcdata[r][i];
            index += 1;
          }
        }
      }

      for (let i = 0; i < maxEcCount; i += 1) {
        for (let r = 0; r < rsBlocks.length; r += 1) {
          if (i < ecdata[r].length) {
            data[index] = ecdata[r][i];
            index += 1;
          }
        }
      }

      return data;
    };

    const createData = function(typeNumber, errorCorrectionLevel, dataList) {

      const rsBlocks = QRRSBlock.getRSBlocks(typeNumber, errorCorrectionLevel);

      const buffer = qrBitBuffer();

      for (let i = 0; i < dataList.length; i += 1) {
        const data = dataList[i];
        buffer.put(data.getMode(), 4);
        buffer.put(data.getLength(), QRUtil.getLengthInBits(data.getMode(), typeNumber) );
        data.write(buffer);
      }

      // calc num max data.
      let totalDataCount = 0;
      for (let i = 0; i < rsBlocks.length; i += 1) {
        totalDataCount += rsBlocks[i].dataCount;
      }

      if (buffer.getLengthInBits() > totalDataCount * 8) {
        throw 'code length overflow. ('
          + buffer.getLengthInBits()
          + '>'
          + totalDataCount * 8
          + ')';
      }

      // end code
      if (buffer.getLengthInBits() + 4 <= totalDataCount * 8) {
        buffer.put(0, 4);
      }

      // padding
      while (buffer.getLengthInBits() % 8 != 0) {
        buffer.putBit(false);
      }

      // padding
      while (true) {

        if (buffer.getLengthInBits() >= totalDataCount * 8) {
          break;
        }
        buffer.put(PAD0, 8);

        if (buffer.getLengthInBits() >= totalDataCount * 8) {
          break;
        }
        buffer.put(PAD1, 8);
      }

      return createBytes(buffer, rsBlocks);
    };

    _this.addData = function(data, mode) {

      mode = mode || 'Byte';

      let newData = null;

      switch(mode) {
      case 'Numeric' :
        newData = qrNumber(data);
        break;
      case 'Alphanumeric' :
        newData = qrAlphaNum(data);
        break;
      case 'Byte' :
        newData = qr8BitByte(data);
        break;
      case 'Kanji' :
        newData = qrKanji(data);
        break;
      default :
        throw 'mode:' + mode;
      }

      _dataList.push(newData);
      _dataCache = null;
    };

    _this.isDark = function(row, col) {
      if (row < 0 || _moduleCount <= row || col < 0 || _moduleCount <= col) {
        throw row + ',' + col;
      }
      return _modules[row][col];
    };

    _this.getModuleCount = function() {
      return _moduleCount;
    };

    _this.make = function() {
      if (_typeNumber < 1) {
        let typeNumber = 1;

        for (; typeNumber < 40; typeNumber++) {
          const rsBlocks = QRRSBlock.getRSBlocks(typeNumber, _errorCorrectionLevel);
          const buffer = qrBitBuffer();

          for (let i = 0; i < _dataList.length; i++) {
            const data = _dataList[i];
            buffer.put(data.getMode(), 4);
            buffer.put(data.getLength(), QRUtil.getLengthInBits(data.getMode(), typeNumber) );
            data.write(buffer);
          }

          let totalDataCount = 0;
          for (let i = 0; i < rsBlocks.length; i++) {
            totalDataCount += rsBlocks[i].dataCount;
          }

          if (buffer.getLengthInBits() <= totalDataCount * 8) {
            break;
          }
        }

        _typeNumber = typeNumber;
      }

      makeImpl(false, getBestMaskPattern() );
    };

    _this.createTableTag = function(cellSize, margin) {

      cellSize = cellSize || 2;
      margin = (typeof margin == 'undefined')? cellSize * 4 : margin;

      let qrHtml = '';

      qrHtml += '<table style="';
      qrHtml += ' border-width: 0px; border-style: none;';
      qrHtml += ' border-collapse: collapse;';
      qrHtml += ' padding: 0px; margin: ' + margin + 'px;';
      qrHtml += '">';
      qrHtml += '<tbody>';

      for (let r = 0; r < _this.getModuleCount(); r += 1) {

        qrHtml += '<tr>';

        for (let c = 0; c < _this.getModuleCount(); c += 1) {
          qrHtml += '<td style="';
          qrHtml += ' border-width: 0px; border-style: none;';
          qrHtml += ' border-collapse: collapse;';
          qrHtml += ' padding: 0px; margin: 0px;';
          qrHtml += ' width: ' + cellSize + 'px;';
          qrHtml += ' height: ' + cellSize + 'px;';
          qrHtml += ' background-color: ';
          qrHtml += _this.isDark(r, c)? '#000000' : '#ffffff';
          qrHtml += ';';
          qrHtml += '"/>';
        }

        qrHtml += '</tr>';
      }

      qrHtml += '</tbody>';
      qrHtml += '</table>';

      return qrHtml;
    };

    _this.createSvgTag = function(cellSize, margin, alt, title) {

      let opts = {};
      if (typeof arguments[0] == 'object') {
        // Called by options.
        opts = arguments[0];
        // overwrite cellSize and margin.
        cellSize = opts.cellSize;
        margin = opts.margin;
        alt = opts.alt;
        title = opts.title;
      }

      cellSize = cellSize || 2;
      margin = (typeof margin == 'undefined')? cellSize * 4 : margin;

      // Compose alt property surrogate
      alt = (typeof alt === 'string') ? {text: alt} : alt || {};
      alt.text = alt.text || null;
      alt.id = (alt.text) ? alt.id || 'qrcode-description' : null;

      // Compose title property surrogate
      title = (typeof title === 'string') ? {text: title} : title || {};
      title.text = title.text || null;
      title.id = (title.text) ? title.id || 'qrcode-title' : null;

      const size = _this.getModuleCount() * cellSize + margin * 2;
      let c, mc, r, mr, qrSvg='', rect;

      rect = 'l' + cellSize + ',0 0,' + cellSize +
        ' -' + cellSize + ',0 0,-' + cellSize + 'z ';

      qrSvg += '<svg version="1.1" xmlns="http://www.w3.org/2000/svg"';
      qrSvg += !opts.scalable ? ' width="' + size + 'px" height="' + size + 'px"' : '';
      qrSvg += ' viewBox="0 0 ' + size + ' ' + size + '" ';
      qrSvg += ' preserveAspectRatio="xMinYMin meet"';
      qrSvg += (title.text || alt.text) ? ' role="img" aria-labelledby="' +
          escapeXml([title.id, alt.id].join(' ').trim() ) + '"' : '';
      qrSvg += '>';
      qrSvg += (title.text) ? '<title id="' + escapeXml(title.id) + '">' +
          escapeXml(title.text) + '</title>' : '';
      qrSvg += (alt.text) ? '<description id="' + escapeXml(alt.id) + '">' +
          escapeXml(alt.text) + '</description>' : '';
      qrSvg += '<rect width="100%" height="100%" fill="white" cx="0" cy="0"/>';
      qrSvg += '<path d="';

      for (r = 0; r < _this.getModuleCount(); r += 1) {
        mr = r * cellSize + margin;
        for (c = 0; c < _this.getModuleCount(); c += 1) {
          if (_this.isDark(r, c) ) {
            mc = c*cellSize+margin;
            qrSvg += 'M' + mc + ',' + mr + rect;
          }
        }
      }

      qrSvg += '" stroke="transparent" fill="black"/>';
      qrSvg += '</svg>';

      return qrSvg;
    };

    _this.createDataURL = function(cellSize, margin) {

      cellSize = cellSize || 2;
      margin = (typeof margin == 'undefined')? cellSize * 4 : margin;

      const size = _this.getModuleCount() * cellSize + margin * 2;
      const min = margin;
      const max = size - margin;

      return createDataURL(size, size, function(x, y) {
        if (min <= x && x < max && min <= y && y < max) {
          const c = Math.floor( (x - min) / cellSize);
          const r = Math.floor( (y - min) / cellSize);
          return _this.isDark(r, c)? 0 : 1;
        } else {
          return 1;
        }
      } );
    };

    _this.createImgTag = function(cellSize, margin, alt) {

      cellSize = cellSize || 2;
      margin = (typeof margin == 'undefined')? cellSize * 4 : margin;

      const size = _this.getModuleCount() * cellSize + margin * 2;

      let img = '';
      img += '<img';
      img += '\u0020src="';
      img += _this.createDataURL(cellSize, margin);
      img += '"';
      img += '\u0020width="';
      img += size;
      img += '"';
      img += '\u0020height="';
      img += size;
      img += '"';
      if (alt) {
        img += '\u0020alt="';
        img += escapeXml(alt);
        img += '"';
      }
      img += '/>';

      return img;
    };

    const escapeXml = function(s) {
      let escaped = '';
      for (let i = 0; i < s.length; i += 1) {
        const c = s.charAt(i);
        switch(c) {
        case '<': escaped += '&lt;'; break;
        case '>': escaped += '&gt;'; break;
        case '&': escaped += '&amp;'; break;
        case '"': escaped += '&quot;'; break;
        default : escaped += c; break;
        }
      }
      return escaped;
    };

    const _createHalfASCII = function(margin) {
      const cellSize = 1;
      margin = (typeof margin == 'undefined')? cellSize * 2 : margin;

      const size = _this.getModuleCount() * cellSize + margin * 2;
      const min = margin;
      const max = size - margin;

      let y, x, r1, r2, p;

      const blocks = {
        '██': '█',
        '█ ': '▀',
        ' █': '▄',
        '  ': ' '
      };

      const blocksLastLineNoMargin = {
        '██': '▀',
        '█ ': '▀',
        ' █': ' ',
        '  ': ' '
      };

      let ascii = '';
      for (y = 0; y < size; y += 2) {
        r1 = Math.floor((y - min) / cellSize);
        r2 = Math.floor((y + 1 - min) / cellSize);
        for (x = 0; x < size; x += 1) {
          p = '█';

          if (min <= x && x < max && min <= y && y < max && _this.isDark(r1, Math.floor((x - min) / cellSize))) {
            p = ' ';
          }

          if (min <= x && x < max && min <= y+1 && y+1 < max && _this.isDark(r2, Math.floor((x - min) / cellSize))) {
            p += ' ';
          }
          else {
            p += '█';
          }

          // Output 2 characters per pixel, to create full square. 1 character per pixels gives only half width of square.
          ascii += (margin < 1 && y+1 >= max) ? blocksLastLineNoMargin[p] : blocks[p];
        }

        ascii += '\n';
      }

      if (size % 2 && margin > 0) {
        return ascii.substring(0, ascii.length - size - 1) + Array(size+1).join('▀');
      }

      return ascii.substring(0, ascii.length-1);
    };

    _this.createASCII = function(cellSize, margin) {
      cellSize = cellSize || 1;

      if (cellSize < 2) {
        return _createHalfASCII(margin);
      }

      cellSize -= 1;
      margin = (typeof margin == 'undefined')? cellSize * 2 : margin;

      const size = _this.getModuleCount() * cellSize + margin * 2;
      const min = margin;
      const max = size - margin;

      let y, x, r, p;

      const white = Array(cellSize+1).join('██');
      const black = Array(cellSize+1).join('  ');

      let ascii = '';
      let line = '';
      for (y = 0; y < size; y += 1) {
        r = Math.floor( (y - min) / cellSize);
        line = '';
        for (x = 0; x < size; x += 1) {
          p = 1;

          if (min <= x && x < max && min <= y && y < max && _this.isDark(r, Math.floor((x - min) / cellSize))) {
            p = 0;
          }

          // Output 2 characters per pixel, to create full square. 1 character per pixels gives only half width of square.
          line += p ? white : black;
        }

        for (r = 0; r < cellSize; r += 1) {
          ascii += line + '\n';
        }
      }

      return ascii.substring(0, ascii.length-1);
    };

    _this.renderTo2dContext = function(context, cellSize) {
      cellSize = cellSize || 2;
      const length = _this.getModuleCount();
      for (let row = 0; row < length; row++) {
        for (let col = 0; col < length; col++) {
          context.fillStyle = _this.isDark(row, col) ? 'black' : 'white';
          context.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
        }
      }
    };

    return _this;
  };

  //---------------------------------------------------------------------
  // qrcode.stringToBytes
  //---------------------------------------------------------------------

  qrcode.stringToBytes = function(s) {
    const bytes = [];
    for (let i = 0; i < s.length; i += 1) {
      const c = s.charCodeAt(i);
      bytes.push(c & 0xff);
    }
    return bytes;
  };

  //---------------------------------------------------------------------
  // qrcode.createStringToBytes
  //---------------------------------------------------------------------

  /**
   * @param unicodeData base64 string of byte array.
   * [16bit Unicode],[16bit Bytes], ...
   * @param numChars
   */
  qrcode.createStringToBytes = function(unicodeData, numChars) {

    // create conversion map.

    const unicodeMap = function() {

      const bin = base64DecodeInputStream(unicodeData);
      const read = function() {
        const b = bin.read();
        if (b == -1) throw 'eof';
        return b;
      };

      let count = 0;
      const unicodeMap = {};
      while (true) {
        const b0 = bin.read();
        if (b0 == -1) break;
        const b1 = read();
        const b2 = read();
        const b3 = read();
        const k = String.fromCharCode( (b0 << 8) | b1);
        const v = (b2 << 8) | b3;
        unicodeMap[k] = v;
        count += 1;
      }
      if (count != numChars) {
        throw count + ' != ' + numChars;
      }

      return unicodeMap;
    }();

    const unknownChar = '?'.charCodeAt(0);

    return function(s) {
      const bytes = [];
      for (let i = 0; i < s.length; i += 1) {
        const c = s.charCodeAt(i);
        if (c < 128) {
          bytes.push(c);
        } else {
          const b = unicodeMap[s.charAt(i)];
          if (typeof b == 'number') {
            if ( (b & 0xff) == b) {
              // 1byte
              bytes.push(b);
            } else {
              // 2bytes
              bytes.push(b >>> 8);
              bytes.push(b & 0xff);
            }
          } else {
            bytes.push(unknownChar);
          }
        }
      }
      return bytes;
    };
  };

  //---------------------------------------------------------------------
  // QRMode
  //---------------------------------------------------------------------

  const QRMode = {
    MODE_NUMBER :    1 << 0,
    MODE_ALPHA_NUM : 1 << 1,
    MODE_8BIT_BYTE : 1 << 2,
    MODE_KANJI :     1 << 3
  };

  //---------------------------------------------------------------------
  // QRErrorCorrectionLevel
  //---------------------------------------------------------------------

  const QRErrorCorrectionLevel = {
    L : 1,
    M : 0,
    Q : 3,
    H : 2
  };

  //---------------------------------------------------------------------
  // QRMaskPattern
  //---------------------------------------------------------------------

  const QRMaskPattern = {
    PATTERN000 : 0,
    PATTERN001 : 1,
    PATTERN010 : 2,
    PATTERN011 : 3,
    PATTERN100 : 4,
    PATTERN101 : 5,
    PATTERN110 : 6,
    PATTERN111 : 7
  };

  //---------------------------------------------------------------------
  // QRUtil
  //---------------------------------------------------------------------

  const QRUtil = function() {

    const PATTERN_POSITION_TABLE = [
      [],
      [6, 18],
      [6, 22],
      [6, 26],
      [6, 30],
      [6, 34],
      [6, 22, 38],
      [6, 24, 42],
      [6, 26, 46],
      [6, 28, 50],
      [6, 30, 54],
      [6, 32, 58],
      [6, 34, 62],
      [6, 26, 46, 66],
      [6, 26, 48, 70],
      [6, 26, 50, 74],
      [6, 30, 54, 78],
      [6, 30, 56, 82],
      [6, 30, 58, 86],
      [6, 34, 62, 90],
      [6, 28, 50, 72, 94],
      [6, 26, 50, 74, 98],
      [6, 30, 54, 78, 102],
      [6, 28, 54, 80, 106],
      [6, 32, 58, 84, 110],
      [6, 30, 58, 86, 114],
      [6, 34, 62, 90, 118],
      [6, 26, 50, 74, 98, 122],
      [6, 30, 54, 78, 102, 126],
      [6, 26, 52, 78, 104, 130],
      [6, 30, 56, 82, 108, 134],
      [6, 34, 60, 86, 112, 138],
      [6, 30, 58, 86, 114, 142],
      [6, 34, 62, 90, 118, 146],
      [6, 30, 54, 78, 102, 126, 150],
      [6, 24, 50, 76, 102, 128, 154],
      [6, 28, 54, 80, 106, 132, 158],
      [6, 32, 58, 84, 110, 136, 162],
      [6, 26, 54, 82, 110, 138, 166],
      [6, 30, 58, 86, 114, 142, 170]
    ];
    const G15 = (1 << 10) | (1 << 8) | (1 << 5) | (1 << 4) | (1 << 2) | (1 << 1) | (1 << 0);
    const G18 = (1 << 12) | (1 << 11) | (1 << 10) | (1 << 9) | (1 << 8) | (1 << 5) | (1 << 2) | (1 << 0);
    const G15_MASK = (1 << 14) | (1 << 12) | (1 << 10) | (1 << 4) | (1 << 1);

    const _this = {};

    const getBCHDigit = function(data) {
      let digit = 0;
      while (data != 0) {
        digit += 1;
        data >>>= 1;
      }
      return digit;
    };

    _this.getBCHTypeInfo = function(data) {
      let d = data << 10;
      while (getBCHDigit(d) - getBCHDigit(G15) >= 0) {
        d ^= (G15 << (getBCHDigit(d) - getBCHDigit(G15) ) );
      }
      return ( (data << 10) | d) ^ G15_MASK;
    };

    _this.getBCHTypeNumber = function(data) {
      let d = data << 12;
      while (getBCHDigit(d) - getBCHDigit(G18) >= 0) {
        d ^= (G18 << (getBCHDigit(d) - getBCHDigit(G18) ) );
      }
      return (data << 12) | d;
    };

    _this.getPatternPosition = function(typeNumber) {
      return PATTERN_POSITION_TABLE[typeNumber - 1];
    };

    _this.getMaskFunction = function(maskPattern) {

      switch (maskPattern) {

      case QRMaskPattern.PATTERN000 :
        return function(i, j) { return (i + j) % 2 == 0; };
      case QRMaskPattern.PATTERN001 :
        return function(i, j) { return i % 2 == 0; };
      case QRMaskPattern.PATTERN010 :
        return function(i, j) { return j % 3 == 0; };
      case QRMaskPattern.PATTERN011 :
        return function(i, j) { return (i + j) % 3 == 0; };
      case QRMaskPattern.PATTERN100 :
        return function(i, j) { return (Math.floor(i / 2) + Math.floor(j / 3) ) % 2 == 0; };
      case QRMaskPattern.PATTERN101 :
        return function(i, j) { return (i * j) % 2 + (i * j) % 3 == 0; };
      case QRMaskPattern.PATTERN110 :
        return function(i, j) { return ( (i * j) % 2 + (i * j) % 3) % 2 == 0; };
      case QRMaskPattern.PATTERN111 :
        return function(i, j) { return ( (i * j) % 3 + (i + j) % 2) % 2 == 0; };

      default :
        throw 'bad maskPattern:' + maskPattern;
      }
    };

    _this.getErrorCorrectPolynomial = function(errorCorrectLength) {
      let a = qrPolynomial([1], 0);
      for (let i = 0; i < errorCorrectLength; i += 1) {
        a = a.multiply(qrPolynomial([1, QRMath.gexp(i)], 0) );
      }
      return a;
    };

    _this.getLengthInBits = function(mode, type) {

      if (1 <= type && type < 10) {

        // 1 - 9

        switch(mode) {
        case QRMode.MODE_NUMBER    : return 10;
        case QRMode.MODE_ALPHA_NUM : return 9;
        case QRMode.MODE_8BIT_BYTE : return 8;
        case QRMode.MODE_KANJI     : return 8;
        default :
          throw 'mode:' + mode;
        }

      } else if (type < 27) {

        // 10 - 26

        switch(mode) {
        case QRMode.MODE_NUMBER    : return 12;
        case QRMode.MODE_ALPHA_NUM : return 11;
        case QRMode.MODE_8BIT_BYTE : return 16;
        case QRMode.MODE_KANJI     : return 10;
        default :
          throw 'mode:' + mode;
        }

      } else if (type < 41) {

        // 27 - 40

        switch(mode) {
        case QRMode.MODE_NUMBER    : return 14;
        case QRMode.MODE_ALPHA_NUM : return 13;
        case QRMode.MODE_8BIT_BYTE : return 16;
        case QRMode.MODE_KANJI     : return 12;
        default :
          throw 'mode:' + mode;
        }

      } else {
        throw 'type:' + type;
      }
    };

    _this.getLostPoint = function(qrcode) {

      const moduleCount = qrcode.getModuleCount();

      let lostPoint = 0;

      // LEVEL1

      for (let row = 0; row < moduleCount; row += 1) {
        for (let col = 0; col < moduleCount; col += 1) {

          let sameCount = 0;
          const dark = qrcode.isDark(row, col);

          for (let r = -1; r <= 1; r += 1) {

            if (row + r < 0 || moduleCount <= row + r) {
              continue;
            }

            for (let c = -1; c <= 1; c += 1) {

              if (col + c < 0 || moduleCount <= col + c) {
                continue;
              }

              if (r == 0 && c == 0) {
                continue;
              }

              if (dark == qrcode.isDark(row + r, col + c) ) {
                sameCount += 1;
              }
            }
          }

          if (sameCount > 5) {
            lostPoint += (3 + sameCount - 5);
          }
        }
      }
      // LEVEL2

      for (let row = 0; row < moduleCount - 1; row += 1) {
        for (let col = 0; col < moduleCount - 1; col += 1) {
          let count = 0;
          if (qrcode.isDark(row, col) ) count += 1;
          if (qrcode.isDark(row + 1, col) ) count += 1;
          if (qrcode.isDark(row, col + 1) ) count += 1;
          if (qrcode.isDark(row + 1, col + 1) ) count += 1;
          if (count == 0 || count == 4) {
            lostPoint += 3;
          }
        }
      }

      // LEVEL3

      for (let row = 0; row < moduleCount; row += 1) {
        for (let col = 0; col < moduleCount - 6; col += 1) {
          if (qrcode.isDark(row, col)
              && !qrcode.isDark(row, col + 1)
              &&  qrcode.isDark(row, col + 2)
              &&  qrcode.isDark(row, col + 3)
              &&  qrcode.isDark(row, col + 4)
              && !qrcode.isDark(row, col + 5)
              &&  qrcode.isDark(row, col + 6) ) {
            lostPoint += 40;
          }
        }
      }

      for (let col = 0; col < moduleCount; col += 1) {
        for (let row = 0; row < moduleCount - 6; row += 1) {
          if (qrcode.isDark(row, col)
              && !qrcode.isDark(row + 1, col)
              &&  qrcode.isDark(row + 2, col)
              &&  qrcode.isDark(row + 3, col)
              &&  qrcode.isDark(row + 4, col)
              && !qrcode.isDark(row + 5, col)
              &&  qrcode.isDark(row + 6, col) ) {
            lostPoint += 40;
          }
        }
      }

      // LEVEL4

      let darkCount = 0;

      for (let col = 0; col < moduleCount; col += 1) {
        for (let row = 0; row < moduleCount; row += 1) {
          if (qrcode.isDark(row, col) ) {
            darkCount += 1;
          }
        }
      }

      const ratio = Math.abs(100 * darkCount / moduleCount / moduleCount - 50) / 5;
      lostPoint += ratio * 10;

      return lostPoint;
    };

    return _this;
  }();

  //---------------------------------------------------------------------
  // QRMath
  //---------------------------------------------------------------------

  const QRMath = function() {

    const EXP_TABLE = new Array(256);
    const LOG_TABLE = new Array(256);

    // initialize tables
    for (let i = 0; i < 8; i += 1) {
      EXP_TABLE[i] = 1 << i;
    }
    for (let i = 8; i < 256; i += 1) {
      EXP_TABLE[i] = EXP_TABLE[i - 4]
        ^ EXP_TABLE[i - 5]
        ^ EXP_TABLE[i - 6]
        ^ EXP_TABLE[i - 8];
    }
    for (let i = 0; i < 255; i += 1) {
      LOG_TABLE[EXP_TABLE[i] ] = i;
    }

    const _this = {};

    _this.glog = function(n) {

      if (n < 1) {
        throw 'glog(' + n + ')';
      }

      return LOG_TABLE[n];
    };

    _this.gexp = function(n) {

      while (n < 0) {
        n += 255;
      }

      while (n >= 256) {
        n -= 255;
      }

      return EXP_TABLE[n];
    };

    return _this;
  }();

  //---------------------------------------------------------------------
  // qrPolynomial
  //---------------------------------------------------------------------

  const qrPolynomial = function(num, shift) {

    if (typeof num.length == 'undefined') {
      throw num.length + '/' + shift;
    }

    const _num = function() {
      let offset = 0;
      while (offset < num.length && num[offset] == 0) {
        offset += 1;
      }
      const _num = new Array(num.length - offset + shift);
      for (let i = 0; i < num.length - offset; i += 1) {
        _num[i] = num[i + offset];
      }
      return _num;
    }();

    const _this = {};

    _this.getAt = function(index) {
      return _num[index];
    };

    _this.getLength = function() {
      return _num.length;
    };

    _this.multiply = function(e) {

      const num = new Array(_this.getLength() + e.getLength() - 1);

      for (let i = 0; i < _this.getLength(); i += 1) {
        for (let j = 0; j < e.getLength(); j += 1) {
          num[i + j] ^= QRMath.gexp(QRMath.glog(_this.getAt(i) ) + QRMath.glog(e.getAt(j) ) );
        }
      }

      return qrPolynomial(num, 0);
    };

    _this.mod = function(e) {

      if (_this.getLength() - e.getLength() < 0) {
        return _this;
      }

      const ratio = QRMath.glog(_this.getAt(0) ) - QRMath.glog(e.getAt(0) );

      const num = new Array(_this.getLength() );
      for (let i = 0; i < _this.getLength(); i += 1) {
        num[i] = _this.getAt(i);
      }

      for (let i = 0; i < e.getLength(); i += 1) {
        num[i] ^= QRMath.gexp(QRMath.glog(e.getAt(i) ) + ratio);
      }

      // recursive call
      return qrPolynomial(num, 0).mod(e);
    };

    return _this;
  };

  //---------------------------------------------------------------------
  // QRRSBlock
  //---------------------------------------------------------------------

  const QRRSBlock = function() {

    const RS_BLOCK_TABLE = [

      // L
      // M
      // Q
      // H

      // 1
      [1, 26, 19],
      [1, 26, 16],
      [1, 26, 13],
      [1, 26, 9],

      // 2
      [1, 44, 34],
      [1, 44, 28],
      [1, 44, 22],
      [1, 44, 16],

      // 3
      [1, 70, 55],
      [1, 70, 44],
      [2, 35, 17],
      [2, 35, 13],

      // 4
      [1, 100, 80],
      [2, 50, 32],
      [2, 50, 24],
      [4, 25, 9],

      // 5
      [1, 134, 108],
      [2, 67, 43],
      [2, 33, 15, 2, 34, 16],
      [2, 33, 11, 2, 34, 12],

      // 6
      [2, 86, 68],
      [4, 43, 27],
      [4, 43, 19],
      [4, 43, 15],

      // 7
      [2, 98, 78],
      [4, 49, 31],
      [2, 32, 14, 4, 33, 15],
      [4, 39, 13, 1, 40, 14],

      // 8
      [2, 121, 97],
      [2, 60, 38, 2, 61, 39],
      [4, 40, 18, 2, 41, 19],
      [4, 40, 14, 2, 41, 15],

      // 9
      [2, 146, 116],
      [3, 58, 36, 2, 59, 37],
      [4, 36, 16, 4, 37, 17],
      [4, 36, 12, 4, 37, 13],

      // 10
      [2, 86, 68, 2, 87, 69],
      [4, 69, 43, 1, 70, 44],
      [6, 43, 19, 2, 44, 20],
      [6, 43, 15, 2, 44, 16],

      // 11
      [4, 101, 81],
      [1, 80, 50, 4, 81, 51],
      [4, 50, 22, 4, 51, 23],
      [3, 36, 12, 8, 37, 13],

      // 12
      [2, 116, 92, 2, 117, 93],
      [6, 58, 36, 2, 59, 37],
      [4, 46, 20, 6, 47, 21],
      [7, 42, 14, 4, 43, 15],

      // 13
      [4, 133, 107],
      [8, 59, 37, 1, 60, 38],
      [8, 44, 20, 4, 45, 21],
      [12, 33, 11, 4, 34, 12],

      // 14
      [3, 145, 115, 1, 146, 116],
      [4, 64, 40, 5, 65, 41],
      [11, 36, 16, 5, 37, 17],
      [11, 36, 12, 5, 37, 13],

      // 15
      [5, 109, 87, 1, 110, 88],
      [5, 65, 41, 5, 66, 42],
      [5, 54, 24, 7, 55, 25],
      [11, 36, 12, 7, 37, 13],

      // 16
      [5, 122, 98, 1, 123, 99],
      [7, 73, 45, 3, 74, 46],
      [15, 43, 19, 2, 44, 20],
      [3, 45, 15, 13, 46, 16],

      // 17
      [1, 135, 107, 5, 136, 108],
      [10, 74, 46, 1, 75, 47],
      [1, 50, 22, 15, 51, 23],
      [2, 42, 14, 17, 43, 15],

      // 18
      [5, 150, 120, 1, 151, 121],
      [9, 69, 43, 4, 70, 44],
      [17, 50, 22, 1, 51, 23],
      [2, 42, 14, 19, 43, 15],

      // 19
      [3, 141, 113, 4, 142, 114],
      [3, 70, 44, 11, 71, 45],
      [17, 47, 21, 4, 48, 22],
      [9, 39, 13, 16, 40, 14],

      // 20
      [3, 135, 107, 5, 136, 108],
      [3, 67, 41, 13, 68, 42],
      [15, 54, 24, 5, 55, 25],
      [15, 43, 15, 10, 44, 16],

      // 21
      [4, 144, 116, 4, 145, 117],
      [17, 68, 42],
      [17, 50, 22, 6, 51, 23],
      [19, 46, 16, 6, 47, 17],

      // 22
      [2, 139, 111, 7, 140, 112],
      [17, 74, 46],
      [7, 54, 24, 16, 55, 25],
      [34, 37, 13],

      // 23
      [4, 151, 121, 5, 152, 122],
      [4, 75, 47, 14, 76, 48],
      [11, 54, 24, 14, 55, 25],
      [16, 45, 15, 14, 46, 16],

      // 24
      [6, 147, 117, 4, 148, 118],
      [6, 73, 45, 14, 74, 46],
      [11, 54, 24, 16, 55, 25],
      [30, 46, 16, 2, 47, 17],

      // 25
      [8, 132, 106, 4, 133, 107],
      [8, 75, 47, 13, 76, 48],
      [7, 54, 24, 22, 55, 25],
      [22, 45, 15, 13, 46, 16],

      // 26
      [10, 142, 114, 2, 143, 115],
      [19, 74, 46, 4, 75, 47],
      [28, 50, 22, 6, 51, 23],
      [33, 46, 16, 4, 47, 17],

      // 27
      [8, 152, 122, 4, 153, 123],
      [22, 73, 45, 3, 74, 46],
      [8, 53, 23, 26, 54, 24],
      [12, 45, 15, 28, 46, 16],

      // 28
      [3, 147, 117, 10, 148, 118],
      [3, 73, 45, 23, 74, 46],
      [4, 54, 24, 31, 55, 25],
      [11, 45, 15, 31, 46, 16],

      // 29
      [7, 146, 116, 7, 147, 117],
      [21, 73, 45, 7, 74, 46],
      [1, 53, 23, 37, 54, 24],
      [19, 45, 15, 26, 46, 16],

      // 30
      [5, 145, 115, 10, 146, 116],
      [19, 75, 47, 10, 76, 48],
      [15, 54, 24, 25, 55, 25],
      [23, 45, 15, 25, 46, 16],

      // 31
      [13, 145, 115, 3, 146, 116],
      [2, 74, 46, 29, 75, 47],
      [42, 54, 24, 1, 55, 25],
      [23, 45, 15, 28, 46, 16],

      // 32
      [17, 145, 115],
      [10, 74, 46, 23, 75, 47],
      [10, 54, 24, 35, 55, 25],
      [19, 45, 15, 35, 46, 16],

      // 33
      [17, 145, 115, 1, 146, 116],
      [14, 74, 46, 21, 75, 47],
      [29, 54, 24, 19, 55, 25],
      [11, 45, 15, 46, 46, 16],

      // 34
      [13, 145, 115, 6, 146, 116],
      [14, 74, 46, 23, 75, 47],
      [44, 54, 24, 7, 55, 25],
      [59, 46, 16, 1, 47, 17],

      // 35
      [12, 151, 121, 7, 152, 122],
      [12, 75, 47, 26, 76, 48],
      [39, 54, 24, 14, 55, 25],
      [22, 45, 15, 41, 46, 16],

      // 36
      [6, 151, 121, 14, 152, 122],
      [6, 75, 47, 34, 76, 48],
      [46, 54, 24, 10, 55, 25],
      [2, 45, 15, 64, 46, 16],

      // 37
      [17, 152, 122, 4, 153, 123],
      [29, 74, 46, 14, 75, 47],
      [49, 54, 24, 10, 55, 25],
      [24, 45, 15, 46, 46, 16],

      // 38
      [4, 152, 122, 18, 153, 123],
      [13, 74, 46, 32, 75, 47],
      [48, 54, 24, 14, 55, 25],
      [42, 45, 15, 32, 46, 16],

      // 39
      [20, 147, 117, 4, 148, 118],
      [40, 75, 47, 7, 76, 48],
      [43, 54, 24, 22, 55, 25],
      [10, 45, 15, 67, 46, 16],

      // 40
      [19, 148, 118, 6, 149, 119],
      [18, 75, 47, 31, 76, 48],
      [34, 54, 24, 34, 55, 25],
      [20, 45, 15, 61, 46, 16]
    ];

    const qrRSBlock = function(totalCount, dataCount) {
      const _this = {};
      _this.totalCount = totalCount;
      _this.dataCount = dataCount;
      return _this;
    };

    const _this = {};

    const getRsBlockTable = function(typeNumber, errorCorrectionLevel) {

      switch(errorCorrectionLevel) {
      case QRErrorCorrectionLevel.L :
        return RS_BLOCK_TABLE[(typeNumber - 1) * 4 + 0];
      case QRErrorCorrectionLevel.M :
        return RS_BLOCK_TABLE[(typeNumber - 1) * 4 + 1];
      case QRErrorCorrectionLevel.Q :
        return RS_BLOCK_TABLE[(typeNumber - 1) * 4 + 2];
      case QRErrorCorrectionLevel.H :
        return RS_BLOCK_TABLE[(typeNumber - 1) * 4 + 3];
      default :
        return undefined;
      }
    };

    _this.getRSBlocks = function(typeNumber, errorCorrectionLevel) {

      const rsBlock = getRsBlockTable(typeNumber, errorCorrectionLevel);

      if (typeof rsBlock == 'undefined') {
        throw 'bad rs block @ typeNumber:' + typeNumber +
            '/errorCorrectionLevel:' + errorCorrectionLevel;
      }

      const length = rsBlock.length / 3;

      const list = [];

      for (let i = 0; i < length; i += 1) {

        const count = rsBlock[i * 3 + 0];
        const totalCount = rsBlock[i * 3 + 1];
        const dataCount = rsBlock[i * 3 + 2];

        for (let j = 0; j < count; j += 1) {
          list.push(qrRSBlock(totalCount, dataCount) );
        }
      }

      return list;
    };

    return _this;
  }();

  //---------------------------------------------------------------------
  // qrBitBuffer
  //---------------------------------------------------------------------

  const qrBitBuffer = function() {

    const _buffer = [];
    let _length = 0;

    const _this = {};

    _this.getBuffer = function() {
      return _buffer;
    };

    _this.getAt = function(index) {
      const bufIndex = Math.floor(index / 8);
      return ( (_buffer[bufIndex] >>> (7 - index % 8) ) & 1) == 1;
    };

    _this.put = function(num, length) {
      for (let i = 0; i < length; i += 1) {
        _this.putBit( ( (num >>> (length - i - 1) ) & 1) == 1);
      }
    };

    _this.getLengthInBits = function() {
      return _length;
    };

    _this.putBit = function(bit) {

      const bufIndex = Math.floor(_length / 8);
      if (_buffer.length <= bufIndex) {
        _buffer.push(0);
      }

      if (bit) {
        _buffer[bufIndex] |= (0x80 >>> (_length % 8) );
      }

      _length += 1;
    };

    return _this;
  };

  //---------------------------------------------------------------------
  // qrNumber
  //---------------------------------------------------------------------

  const qrNumber = function(data) {

    const _mode = QRMode.MODE_NUMBER;
    const _data = data;

    const _this = {};

    _this.getMode = function() {
      return _mode;
    };

    _this.getLength = function(buffer) {
      return _data.length;
    };

    _this.write = function(buffer) {

      const data = _data;

      let i = 0;

      while (i + 2 < data.length) {
        buffer.put(strToNum(data.substring(i, i + 3) ), 10);
        i += 3;
      }

      if (i < data.length) {
        if (data.length - i == 1) {
          buffer.put(strToNum(data.substring(i, i + 1) ), 4);
        } else if (data.length - i == 2) {
          buffer.put(strToNum(data.substring(i, i + 2) ), 7);
        }
      }
    };

    const strToNum = function(s) {
      let num = 0;
      for (let i = 0; i < s.length; i += 1) {
        num = num * 10 + chatToNum(s.charAt(i) );
      }
      return num;
    };

    const chatToNum = function(c) {
      if ('0' <= c && c <= '9') {
        return c.charCodeAt(0) - '0'.charCodeAt(0);
      }
      throw 'illegal char :' + c;
    };

    return _this;
  };

  //---------------------------------------------------------------------
  // qrAlphaNum
  //---------------------------------------------------------------------

  const qrAlphaNum = function(data) {

    const _mode = QRMode.MODE_ALPHA_NUM;
    const _data = data;

    const _this = {};

    _this.getMode = function() {
      return _mode;
    };

    _this.getLength = function(buffer) {
      return _data.length;
    };

    _this.write = function(buffer) {

      const s = _data;

      let i = 0;

      while (i + 1 < s.length) {
        buffer.put(
          getCode(s.charAt(i) ) * 45 +
          getCode(s.charAt(i + 1) ), 11);
        i += 2;
      }

      if (i < s.length) {
        buffer.put(getCode(s.charAt(i) ), 6);
      }
    };

    const getCode = function(c) {

      if ('0' <= c && c <= '9') {
        return c.charCodeAt(0) - '0'.charCodeAt(0);
      } else if ('A' <= c && c <= 'Z') {
        return c.charCodeAt(0) - 'A'.charCodeAt(0) + 10;
      } else {
        switch (c) {
        case '\u0020' : return 36;
        case '$' : return 37;
        case '%' : return 38;
        case '*' : return 39;
        case '+' : return 40;
        case '-' : return 41;
        case '.' : return 42;
        case '/' : return 43;
        case ':' : return 44;
        default :
          throw 'illegal char :' + c;
        }
      }
    };

    return _this;
  };

  //---------------------------------------------------------------------
  // qr8BitByte
  //---------------------------------------------------------------------

  const qr8BitByte = function(data) {

    const _mode = QRMode.MODE_8BIT_BYTE;
    const _bytes = qrcode.stringToBytes(data);

    const _this = {};

    _this.getMode = function() {
      return _mode;
    };

    _this.getLength = function(buffer) {
      return _bytes.length;
    };

    _this.write = function(buffer) {
      for (let i = 0; i < _bytes.length; i += 1) {
        buffer.put(_bytes[i], 8);
      }
    };

    return _this;
  };

  //---------------------------------------------------------------------
  // qrKanji
  //---------------------------------------------------------------------

  const qrKanji = function(data) {

    const _mode = QRMode.MODE_KANJI;

    const stringToBytes = qrcode.stringToBytes;
    !function(c, code) {
      // self test for sjis support.
      const test = stringToBytes(c);
      if (test.length != 2 || ( (test[0] << 8) | test[1]) != code) {
        throw 'sjis not supported.';
      }
    }('\u53cb', 0x9746);

    const _bytes = stringToBytes(data);

    const _this = {};

    _this.getMode = function() {
      return _mode;
    };

    _this.getLength = function(buffer) {
      return ~~(_bytes.length / 2);
    };

    _this.write = function(buffer) {

      const data = _bytes;

      let i = 0;

      while (i + 1 < data.length) {

        let c = ( (0xff & data[i]) << 8) | (0xff & data[i + 1]);

        if (0x8140 <= c && c <= 0x9FFC) {
          c -= 0x8140;
        } else if (0xE040 <= c && c <= 0xEBBF) {
          c -= 0xC140;
        } else {
          throw 'illegal char at ' + (i + 1) + '/' + c;
        }

        c = ( (c >>> 8) & 0xff) * 0xC0 + (c & 0xff);

        buffer.put(c, 13);

        i += 2;
      }

      if (i < data.length) {
        throw 'illegal char at ' + (i + 1);
      }
    };

    return _this;
  };

  //=====================================================================
  // GIF Support etc.
  //

  //---------------------------------------------------------------------
  // byteArrayOutputStream
  //---------------------------------------------------------------------

  const byteArrayOutputStream = function() {

    const _bytes = [];

    const _this = {};

    _this.writeByte = function(b) {
      _bytes.push(b & 0xff);
    };

    _this.writeShort = function(i) {
      _this.writeByte(i);
      _this.writeByte(i >>> 8);
    };

    _this.writeBytes = function(b, off, len) {
      off = off || 0;
      len = len || b.length;
      for (let i = 0; i < len; i += 1) {
        _this.writeByte(b[i + off]);
      }
    };

    _this.writeString = function(s) {
      for (let i = 0; i < s.length; i += 1) {
        _this.writeByte(s.charCodeAt(i) );
      }
    };

    _this.toByteArray = function() {
      return _bytes;
    };

    _this.toString = function() {
      let s = '';
      s += '[';
      for (let i = 0; i < _bytes.length; i += 1) {
        if (i > 0) {
          s += ',';
        }
        s += _bytes[i];
      }
      s += ']';
      return s;
    };

    return _this;
  };

  //---------------------------------------------------------------------
  // base64EncodeOutputStream
  //---------------------------------------------------------------------

  const base64EncodeOutputStream = function() {

    let _buffer = 0;
    let _buflen = 0;
    let _length = 0;
    let _base64 = '';

    const _this = {};

    const writeEncoded = function(b) {
      _base64 += String.fromCharCode(encode(b & 0x3f) );
    };

    const encode = function(n) {
      if (n < 0) {
        throw 'n:' + n;
      } else if (n < 26) {
        return 0x41 + n;
      } else if (n < 52) {
        return 0x61 + (n - 26);
      } else if (n < 62) {
        return 0x30 + (n - 52);
      } else if (n == 62) {
        return 0x2b;
      } else if (n == 63) {
        return 0x2f;
      } else {
        throw 'n:' + n;
      }
    };

    _this.writeByte = function(n) {

      _buffer = (_buffer << 8) | (n & 0xff);
      _buflen += 8;
      _length += 1;

      while (_buflen >= 6) {
        writeEncoded(_buffer >>> (_buflen - 6) );
        _buflen -= 6;
      }
    };

    _this.flush = function() {

      if (_buflen > 0) {
        writeEncoded(_buffer << (6 - _buflen) );
        _buffer = 0;
        _buflen = 0;
      }

      if (_length % 3 != 0) {
        // padding
        const padlen = 3 - _length % 3;
        for (let i = 0; i < padlen; i += 1) {
          _base64 += '=';
        }
      }
    };

    _this.toString = function() {
      return _base64;
    };

    return _this;
  };

  //---------------------------------------------------------------------
  // base64DecodeInputStream
  //---------------------------------------------------------------------

  const base64DecodeInputStream = function(str) {

    const _str = str;
    let _pos = 0;
    let _buffer = 0;
    let _buflen = 0;

    const _this = {};

    _this.read = function() {

      while (_buflen < 8) {

        if (_pos >= _str.length) {
          if (_buflen == 0) {
            return -1;
          }
          throw 'unexpected end of file./' + _buflen;
        }

        const c = _str.charAt(_pos);
        _pos += 1;

        if (c == '=') {
          _buflen = 0;
          return -1;
        } else if (c.match(/^\s$/) ) {
          // ignore if whitespace.
          continue;
        }

        _buffer = (_buffer << 6) | decode(c.charCodeAt(0) );
        _buflen += 6;
      }

      const n = (_buffer >>> (_buflen - 8) ) & 0xff;
      _buflen -= 8;
      return n;
    };

    const decode = function(c) {
      if (0x41 <= c && c <= 0x5a) {
        return c - 0x41;
      } else if (0x61 <= c && c <= 0x7a) {
        return c - 0x61 + 26;
      } else if (0x30 <= c && c <= 0x39) {
        return c - 0x30 + 52;
      } else if (c == 0x2b) {
        return 62;
      } else if (c == 0x2f) {
        return 63;
      } else {
        throw 'c:' + c;
      }
    };

    return _this;
  };

  //---------------------------------------------------------------------
  // gifImage (B/W)
  //---------------------------------------------------------------------

  const gifImage = function(width, height) {

    const _width = width;
    const _height = height;
    const _data = new Array(width * height);

    const _this = {};

    _this.setPixel = function(x, y, pixel) {
      _data[y * _width + x] = pixel;
    };

    _this.write = function(out) {

      //---------------------------------
      // GIF Signature

      out.writeString('GIF87a');

      //---------------------------------
      // Screen Descriptor

      out.writeShort(_width);
      out.writeShort(_height);

      out.writeByte(0x80); // 2bit
      out.writeByte(0);
      out.writeByte(0);

      //---------------------------------
      // Global Color Map

      // black
      out.writeByte(0x00);
      out.writeByte(0x00);
      out.writeByte(0x00);

      // white
      out.writeByte(0xff);
      out.writeByte(0xff);
      out.writeByte(0xff);

      //---------------------------------
      // Image Descriptor

      out.writeString(',');
      out.writeShort(0);
      out.writeShort(0);
      out.writeShort(_width);
      out.writeShort(_height);
      out.writeByte(0);

      //---------------------------------
      // Local Color Map

      //---------------------------------
      // Raster Data

      const lzwMinCodeSize = 2;
      const raster = getLZWRaster(lzwMinCodeSize);

      out.writeByte(lzwMinCodeSize);

      let offset = 0;

      while (raster.length - offset > 255) {
        out.writeByte(255);
        out.writeBytes(raster, offset, 255);
        offset += 255;
      }

      out.writeByte(raster.length - offset);
      out.writeBytes(raster, offset, raster.length - offset);
      out.writeByte(0x00);

      //---------------------------------
      // GIF Terminator
      out.writeString(';');
    };

    const bitOutputStream = function(out) {

      const _out = out;
      let _bitLength = 0;
      let _bitBuffer = 0;

      const _this = {};

      _this.write = function(data, length) {

        if ( (data >>> length) != 0) {
          throw 'length over';
        }

        while (_bitLength + length >= 8) {
          _out.writeByte(0xff & ( (data << _bitLength) | _bitBuffer) );
          length -= (8 - _bitLength);
          data >>>= (8 - _bitLength);
          _bitBuffer = 0;
          _bitLength = 0;
        }

        _bitBuffer = (data << _bitLength) | _bitBuffer;
        _bitLength = _bitLength + length;
      };

      _this.flush = function() {
        if (_bitLength > 0) {
          _out.writeByte(_bitBuffer);
        }
      };

      return _this;
    };

    const getLZWRaster = function(lzwMinCodeSize) {

      const clearCode = 1 << lzwMinCodeSize;
      const endCode = (1 << lzwMinCodeSize) + 1;
      let bitLength = lzwMinCodeSize + 1;

      // Setup LZWTable
      const table = lzwTable();

      for (let i = 0; i < clearCode; i += 1) {
        table.add(String.fromCharCode(i) );
      }
      table.add(String.fromCharCode(clearCode) );
      table.add(String.fromCharCode(endCode) );

      const byteOut = byteArrayOutputStream();
      const bitOut = bitOutputStream(byteOut);

      // clear code
      bitOut.write(clearCode, bitLength);

      let dataIndex = 0;

      let s = String.fromCharCode(_data[dataIndex]);
      dataIndex += 1;

      while (dataIndex < _data.length) {

        const c = String.fromCharCode(_data[dataIndex]);
        dataIndex += 1;

        if (table.contains(s + c) ) {

          s = s + c;

        } else {

          bitOut.write(table.indexOf(s), bitLength);

          if (table.size() < 0xfff) {

            if (table.size() == (1 << bitLength) ) {
              bitLength += 1;
            }

            table.add(s + c);
          }

          s = c;
        }
      }

      bitOut.write(table.indexOf(s), bitLength);

      // end code
      bitOut.write(endCode, bitLength);

      bitOut.flush();

      return byteOut.toByteArray();
    };

    const lzwTable = function() {

      const _map = {};
      let _size = 0;

      const _this = {};

      _this.add = function(key) {
        if (_this.contains(key) ) {
          throw 'dup key:' + key;
        }
        _map[key] = _size;
        _size += 1;
      };

      _this.size = function() {
        return _size;
      };

      _this.indexOf = function(key) {
        return _map[key];
      };

      _this.contains = function(key) {
        return typeof _map[key] != 'undefined';
      };

      return _this;
    };

    return _this;
  };

  const createDataURL = function(width, height, getPixel) {
    const gif = gifImage(width, height);
    for (let y = 0; y < height; y += 1) {
      for (let x = 0; x < width; x += 1) {
        gif.setPixel(x, y, getPixel(x, y) );
      }
    }

    const b = byteArrayOutputStream();
    gif.write(b);

    const base64 = base64EncodeOutputStream();
    const bytes = b.toByteArray();
    for (let i = 0; i < bytes.length; i += 1) {
      base64.writeByte(bytes[i]);
    }
    base64.flush();

    return 'data:image/gif;base64,' + base64;
  };

  qrcode.stringToBytes;

  /**
   * Utilities for hex, bytes, CSPRNG.
   * @module
   */
  /*! noble-ciphers - MIT License (c) 2023 Paul Miller (paulmillr.com) */
  /** Checks if something is Uint8Array. Be careful: nodejs Buffer will return true. */
  function isBytes$1(a) {
      return a instanceof Uint8Array || (ArrayBuffer.isView(a) && a.constructor.name === 'Uint8Array');
  }
  /** Asserts something is boolean. */
  function abool(b) {
      if (typeof b !== 'boolean')
          throw new Error(`boolean expected, not ${b}`);
  }
  /** Asserts something is positive integer. */
  function anumber$1(n) {
      if (!Number.isSafeInteger(n) || n < 0)
          throw new Error('positive integer expected, got ' + n);
  }
  /** Asserts something is Uint8Array. */
  function abytes$1(value, length, title = '') {
      const bytes = isBytes$1(value);
      const len = value?.length;
      const needsLen = length !== undefined;
      if (!bytes || (needsLen && len !== length)) {
          const prefix = title && `"${title}" `;
          const ofLen = needsLen ? ` of length ${length}` : '';
          const got = bytes ? `length=${len}` : `type=${typeof value}`;
          throw new Error(prefix + 'expected Uint8Array' + ofLen + ', got ' + got);
      }
      return value;
  }
  /** Asserts a hash instance has not been destroyed / finished */
  function aexists$1(instance, checkFinished = true) {
      if (instance.destroyed)
          throw new Error('Hash instance has been destroyed');
      if (checkFinished && instance.finished)
          throw new Error('Hash#digest() has already been called');
  }
  /** Asserts output is properly-sized byte array */
  function aoutput$1(out, instance) {
      abytes$1(out, undefined, 'output');
      const min = instance.outputLen;
      if (out.length < min) {
          throw new Error('digestInto() expects output buffer of length at least ' + min);
      }
  }
  /** Cast u8 / u16 / u32 to u32. */
  function u32(arr) {
      return new Uint32Array(arr.buffer, arr.byteOffset, Math.floor(arr.byteLength / 4));
  }
  /** Zeroize a byte array. Warning: JS provides no guarantees. */
  function clean$1(...arrays) {
      for (let i = 0; i < arrays.length; i++) {
          arrays[i].fill(0);
      }
  }
  /** Create DataView of an array for easy byte-level manipulation. */
  function createView$3(arr) {
      return new DataView(arr.buffer, arr.byteOffset, arr.byteLength);
  }
  /** Is current platform little-endian? Most are. Big-Endian platform: IBM */
  const isLE$2 = /* @__PURE__ */ (() => new Uint8Array(new Uint32Array([0x11223344]).buffer)[0] === 0x44)();
  /**
   * Checks if two U8A use same underlying buffer and overlaps.
   * This is invalid and can corrupt data.
   */
  function overlapBytes(a, b) {
      return (a.buffer === b.buffer && // best we can do, may fail with an obscure Proxy
          a.byteOffset < b.byteOffset + b.byteLength && // a starts before b end
          b.byteOffset < a.byteOffset + a.byteLength // b starts before a end
      );
  }
  /**
   * If input and output overlap and input starts before output, we will overwrite end of input before
   * we start processing it, so this is not supported for most ciphers (except chacha/salse, which designed with this)
   */
  function complexOverlapBytes(input, output) {
      // This is very cursed. It works somehow, but I'm completely unsure,
      // reasoning about overlapping aligned windows is very hard.
      if (overlapBytes(input, output) && input.byteOffset < output.byteOffset)
          throw new Error('complex overlap of input and output is not supported');
  }
  function checkOpts(defaults, opts) {
      if (opts == null || typeof opts !== 'object')
          throw new Error('options must be defined');
      const merged = Object.assign(defaults, opts);
      return merged;
  }
  /** Compares 2 uint8array-s in kinda constant time. */
  function equalBytes(a, b) {
      if (a.length !== b.length)
          return false;
      let diff = 0;
      for (let i = 0; i < a.length; i++)
          diff |= a[i] ^ b[i];
      return diff === 0;
  }
  /**
   * Wraps a cipher: validates args, ensures encrypt() can only be called once.
   * @__NO_SIDE_EFFECTS__
   */
  const wrapCipher = (params, constructor) => {
      function wrappedCipher(key, ...args) {
          // Validate key
          abytes$1(key, undefined, 'key');
          // Big-Endian hardware is rare. Just in case someone still decides to run ciphers:
          if (!isLE$2)
              throw new Error('Non little-endian hardware is not yet supported');
          // Validate nonce if nonceLength is present
          if (params.nonceLength !== undefined) {
              const nonce = args[0];
              abytes$1(nonce, params.varSizeNonce ? undefined : params.nonceLength, 'nonce');
          }
          // Validate AAD if tagLength present
          const tagl = params.tagLength;
          if (tagl && args[1] !== undefined)
              abytes$1(args[1], undefined, 'AAD');
          const cipher = constructor(key, ...args);
          const checkOutput = (fnLength, output) => {
              if (output !== undefined) {
                  if (fnLength !== 2)
                      throw new Error('cipher output not supported');
                  abytes$1(output, undefined, 'output');
              }
          };
          // Create wrapped cipher with validation and single-use encryption
          let called = false;
          const wrCipher = {
              encrypt(data, output) {
                  if (called)
                      throw new Error('cannot encrypt() twice with same key + nonce');
                  called = true;
                  abytes$1(data);
                  checkOutput(cipher.encrypt.length, output);
                  return cipher.encrypt(data, output);
              },
              decrypt(data, output) {
                  abytes$1(data);
                  if (tagl && data.length < tagl)
                      throw new Error('"ciphertext" expected length bigger than tagLength=' + tagl);
                  checkOutput(cipher.decrypt.length, output);
                  return cipher.decrypt(data, output);
              },
          };
          return wrCipher;
      }
      Object.assign(wrappedCipher, params);
      return wrappedCipher;
  };
  /**
   * By default, returns u8a of length.
   * When out is available, it checks it for validity and uses it.
   */
  function getOutput(expectedLength, out, onlyAligned = true) {
      if (out === undefined)
          return new Uint8Array(expectedLength);
      if (out.length !== expectedLength)
          throw new Error('"output" expected Uint8Array of length ' + expectedLength + ', got: ' + out.length);
      if (onlyAligned && !isAligned32$1(out))
          throw new Error('invalid output, must be aligned');
      return out;
  }
  function u64Lengths(dataLength, aadLength, isLE) {
      abool(isLE);
      const num = new Uint8Array(16);
      const view = createView$3(num);
      view.setBigUint64(0, BigInt(aadLength), isLE);
      view.setBigUint64(8, BigInt(dataLength), isLE);
      return num;
  }
  // Is byte array aligned to 4 byte offset (u32)?
  function isAligned32$1(bytes) {
      return bytes.byteOffset % 4 === 0;
  }
  // copy bytes to new u8a (aligned). Because Buffer.slice is broken.
  function copyBytes(bytes) {
      return Uint8Array.from(bytes);
  }

  /**
   * [AES](https://en.wikipedia.org/wiki/Advanced_Encryption_Standard)
   * a.k.a. Advanced Encryption Standard
   * is a variant of Rijndael block cipher, standardized by NIST in 2001.
   * We provide the fastest available pure JS implementation.
   *
   * `cipher = encrypt(block, key)`
   *
   * Data is split into 128-bit blocks. Encrypted in 10/12/14 rounds (128/192/256 bits). In every round:
   * 1. **S-box**, table substitution
   * 2. **Shift rows**, cyclic shift left of all rows of data array
   * 3. **Mix columns**, multiplying every column by fixed polynomial
   * 4. **Add round key**, round_key xor i-th column of array
   *
   * Check out [FIPS-197](https://csrc.nist.gov/files/pubs/fips/197/final/docs/fips-197.pdf),
   * [NIST 800-38G](https://nvlpubs.nist.gov/nistpubs/SpecialPublications/NIST.SP.800-38G.pdf)
   * and [original proposal](https://csrc.nist.gov/csrc/media/projects/cryptographic-standards-and-guidelines/documents/aes-development/rijndael-ammended.pdf)
   * @module
   */
  const BLOCK_SIZE = 16;
  const POLY = 0x11b; // 1 + x + x**3 + x**4 + x**8
  function validateKeyLength(key) {
      if (![16, 24, 32].includes(key.length))
          throw new Error('"aes key" expected Uint8Array of length 16/24/32, got length=' + key.length);
  }
  // TODO: remove multiplication, binary ops only
  function mul2$1(n) {
      return (n << 1) ^ (POLY & -(n >> 7));
  }
  function mul$1(a, b) {
      let res = 0;
      for (; b > 0; b >>= 1) {
          // Montgomery ladder
          res ^= a & -(b & 1); // if (b&1) res ^=a (but const-time).
          a = mul2$1(a); // a = 2*a
      }
      return res;
  }
  // AES S-box is generated using finite field inversion,
  // an affine transform, and xor of a constant 0x63.
  const sbox = /* @__PURE__ */ (() => {
      const t = new Uint8Array(256);
      for (let i = 0, x = 1; i < 256; i++, x ^= mul2$1(x))
          t[i] = x;
      const box = new Uint8Array(256);
      box[0] = 0x63; // first elm
      for (let i = 0; i < 255; i++) {
          let x = t[255 - i];
          x |= x << 8;
          box[t[i]] = (x ^ (x >> 4) ^ (x >> 5) ^ (x >> 6) ^ (x >> 7) ^ 0x63) & 0xff;
      }
      clean$1(t);
      return box;
  })();
  // Inverted S-box
  const invSbox = /* @__PURE__ */ sbox.map((_, j) => sbox.indexOf(j));
  // Rotate u32 by 8
  const rotr32_8 = (n) => (n << 24) | (n >>> 8);
  const rotl32_8 = (n) => (n << 8) | (n >>> 24);
  // T-table is optimization suggested in 5.2 of original proposal (missed from FIPS-197). Changes:
  // - LE instead of BE
  // - bigger tables: T0 and T1 are merged into T01 table and T2 & T3 into T23;
  //   so index is u16, instead of u8. This speeds up things, unexpectedly
  function genTtable(sbox, fn) {
      if (sbox.length !== 256)
          throw new Error('Wrong sbox length');
      const T0 = new Uint32Array(256).map((_, j) => fn(sbox[j]));
      const T1 = T0.map(rotl32_8);
      const T2 = T1.map(rotl32_8);
      const T3 = T2.map(rotl32_8);
      const T01 = new Uint32Array(256 * 256);
      const T23 = new Uint32Array(256 * 256);
      const sbox2 = new Uint16Array(256 * 256);
      for (let i = 0; i < 256; i++) {
          for (let j = 0; j < 256; j++) {
              const idx = i * 256 + j;
              T01[idx] = T0[i] ^ T1[j];
              T23[idx] = T2[i] ^ T3[j];
              sbox2[idx] = (sbox[i] << 8) | sbox[j];
          }
      }
      return { sbox, sbox2, T0, T1, T2, T3, T01, T23 };
  }
  const tableEncoding = /* @__PURE__ */ genTtable(sbox, (s) => (mul$1(s, 3) << 24) | (s << 16) | (s << 8) | mul$1(s, 2));
  const tableDecoding = /* @__PURE__ */ genTtable(invSbox, (s) => (mul$1(s, 11) << 24) | (mul$1(s, 13) << 16) | (mul$1(s, 9) << 8) | mul$1(s, 14));
  const xPowers = /* @__PURE__ */ (() => {
      const p = new Uint8Array(16);
      for (let i = 0, x = 1; i < 16; i++, x = mul2$1(x))
          p[i] = x;
      return p;
  })();
  /** Key expansion used in CTR. */
  function expandKeyLE(key) {
      abytes$1(key);
      const len = key.length;
      validateKeyLength(key);
      const { sbox2 } = tableEncoding;
      const toClean = [];
      if (!isAligned32$1(key))
          toClean.push((key = copyBytes(key)));
      const k32 = u32(key);
      const Nk = k32.length;
      const subByte = (n) => applySbox(sbox2, n, n, n, n);
      const xk = new Uint32Array(len + 28); // expanded key
      xk.set(k32);
      // 4.3.1 Key expansion
      for (let i = Nk; i < xk.length; i++) {
          let t = xk[i - 1];
          if (i % Nk === 0)
              t = subByte(rotr32_8(t)) ^ xPowers[i / Nk - 1];
          else if (Nk > 6 && i % Nk === 4)
              t = subByte(t);
          xk[i] = xk[i - Nk] ^ t;
      }
      clean$1(...toClean);
      return xk;
  }
  function expandKeyDecLE(key) {
      const encKey = expandKeyLE(key);
      const xk = encKey.slice();
      const Nk = encKey.length;
      const { sbox2 } = tableEncoding;
      const { T0, T1, T2, T3 } = tableDecoding;
      // Inverse key by chunks of 4 (rounds)
      for (let i = 0; i < Nk; i += 4) {
          for (let j = 0; j < 4; j++)
              xk[i + j] = encKey[Nk - i - 4 + j];
      }
      clean$1(encKey);
      // apply InvMixColumn except first & last round
      for (let i = 4; i < Nk - 4; i++) {
          const x = xk[i];
          const w = applySbox(sbox2, x, x, x, x);
          xk[i] = T0[w & 0xff] ^ T1[(w >>> 8) & 0xff] ^ T2[(w >>> 16) & 0xff] ^ T3[w >>> 24];
      }
      return xk;
  }
  // Apply tables
  function apply0123(T01, T23, s0, s1, s2, s3) {
      return (T01[((s0 << 8) & 0xff00) | ((s1 >>> 8) & 0xff)] ^
          T23[((s2 >>> 8) & 0xff00) | ((s3 >>> 24) & 0xff)]);
  }
  function applySbox(sbox2, s0, s1, s2, s3) {
      return (sbox2[(s0 & 0xff) | (s1 & 0xff00)] |
          (sbox2[((s2 >>> 16) & 0xff) | ((s3 >>> 16) & 0xff00)] << 16));
  }
  function encrypt$2(xk, s0, s1, s2, s3) {
      const { sbox2, T01, T23 } = tableEncoding;
      let k = 0;
      ((s0 ^= xk[k++]), (s1 ^= xk[k++]), (s2 ^= xk[k++]), (s3 ^= xk[k++]));
      const rounds = xk.length / 4 - 2;
      for (let i = 0; i < rounds; i++) {
          const t0 = xk[k++] ^ apply0123(T01, T23, s0, s1, s2, s3);
          const t1 = xk[k++] ^ apply0123(T01, T23, s1, s2, s3, s0);
          const t2 = xk[k++] ^ apply0123(T01, T23, s2, s3, s0, s1);
          const t3 = xk[k++] ^ apply0123(T01, T23, s3, s0, s1, s2);
          ((s0 = t0), (s1 = t1), (s2 = t2), (s3 = t3));
      }
      // last round (without mixcolumns, so using SBOX2 table)
      const t0 = xk[k++] ^ applySbox(sbox2, s0, s1, s2, s3);
      const t1 = xk[k++] ^ applySbox(sbox2, s1, s2, s3, s0);
      const t2 = xk[k++] ^ applySbox(sbox2, s2, s3, s0, s1);
      const t3 = xk[k++] ^ applySbox(sbox2, s3, s0, s1, s2);
      return { s0: t0, s1: t1, s2: t2, s3: t3 };
  }
  // Can't be merged with encrypt: arg positions for apply0123 / applySbox are different
  function decrypt$1(xk, s0, s1, s2, s3) {
      const { sbox2, T01, T23 } = tableDecoding;
      let k = 0;
      ((s0 ^= xk[k++]), (s1 ^= xk[k++]), (s2 ^= xk[k++]), (s3 ^= xk[k++]));
      const rounds = xk.length / 4 - 2;
      for (let i = 0; i < rounds; i++) {
          const t0 = xk[k++] ^ apply0123(T01, T23, s0, s3, s2, s1);
          const t1 = xk[k++] ^ apply0123(T01, T23, s1, s0, s3, s2);
          const t2 = xk[k++] ^ apply0123(T01, T23, s2, s1, s0, s3);
          const t3 = xk[k++] ^ apply0123(T01, T23, s3, s2, s1, s0);
          ((s0 = t0), (s1 = t1), (s2 = t2), (s3 = t3));
      }
      // Last round
      const t0 = xk[k++] ^ applySbox(sbox2, s0, s3, s2, s1);
      const t1 = xk[k++] ^ applySbox(sbox2, s1, s0, s3, s2);
      const t2 = xk[k++] ^ applySbox(sbox2, s2, s1, s0, s3);
      const t3 = xk[k++] ^ applySbox(sbox2, s3, s2, s1, s0);
      return { s0: t0, s1: t1, s2: t2, s3: t3 };
  }
  function validateBlockDecrypt(data) {
      abytes$1(data);
      if (data.length % BLOCK_SIZE !== 0) {
          throw new Error('aes-(cbc/ecb).decrypt ciphertext should consist of blocks with size ' + BLOCK_SIZE);
      }
  }
  function validateBlockEncrypt(plaintext, pcks5, dst) {
      abytes$1(plaintext);
      let outLen = plaintext.length;
      const remaining = outLen % BLOCK_SIZE;
      if (!pcks5 && remaining !== 0)
          throw new Error('aec/(cbc-ecb): unpadded plaintext with disabled padding');
      if (!isAligned32$1(plaintext))
          plaintext = copyBytes(plaintext);
      const b = u32(plaintext);
      if (pcks5) {
          let left = BLOCK_SIZE - remaining;
          if (!left)
              left = BLOCK_SIZE; // if no bytes left, create empty padding block
          outLen = outLen + left;
      }
      dst = getOutput(outLen, dst);
      complexOverlapBytes(plaintext, dst);
      const o = u32(dst);
      return { b, o, out: dst };
  }
  function validatePCKS(data, pcks5) {
      if (!pcks5)
          return data;
      const len = data.length;
      if (!len)
          throw new Error('aes/pcks5: empty ciphertext not allowed');
      const lastByte = data[len - 1];
      if (lastByte <= 0 || lastByte > 16)
          throw new Error('aes/pcks5: wrong padding');
      const out = data.subarray(0, -lastByte);
      for (let i = 0; i < lastByte; i++)
          if (data[len - i - 1] !== lastByte)
              throw new Error('aes/pcks5: wrong padding');
      return out;
  }
  function padPCKS(left) {
      const tmp = new Uint8Array(16);
      const tmp32 = u32(tmp);
      tmp.set(left);
      const paddingByte = BLOCK_SIZE - left.length;
      for (let i = BLOCK_SIZE - paddingByte; i < BLOCK_SIZE; i++)
          tmp[i] = paddingByte;
      return tmp32;
  }
  /**
   * **CBC** (Cipher Block Chaining): Each plaintext block is XORed with the
   * previous block of ciphertext before encryption.
   * Hard to use: requires proper padding and an IV. Unauthenticated: needs MAC.
   */
  const cbc = /* @__PURE__ */ wrapCipher({ blockSize: 16, nonceLength: 16 }, function aescbc(key, iv, opts = {}) {
      const pcks5 = !opts.disablePadding;
      return {
          encrypt(plaintext, dst) {
              const xk = expandKeyLE(key);
              const { b, o, out: _out } = validateBlockEncrypt(plaintext, pcks5, dst);
              let _iv = iv;
              const toClean = [xk];
              if (!isAligned32$1(_iv))
                  toClean.push((_iv = copyBytes(_iv)));
              const n32 = u32(_iv);
              // prettier-ignore
              let s0 = n32[0], s1 = n32[1], s2 = n32[2], s3 = n32[3];
              let i = 0;
              for (; i + 4 <= b.length;) {
                  ((s0 ^= b[i + 0]), (s1 ^= b[i + 1]), (s2 ^= b[i + 2]), (s3 ^= b[i + 3]));
                  ({ s0, s1, s2, s3 } = encrypt$2(xk, s0, s1, s2, s3));
                  ((o[i++] = s0), (o[i++] = s1), (o[i++] = s2), (o[i++] = s3));
              }
              if (pcks5) {
                  const tmp32 = padPCKS(plaintext.subarray(i * 4));
                  ((s0 ^= tmp32[0]), (s1 ^= tmp32[1]), (s2 ^= tmp32[2]), (s3 ^= tmp32[3]));
                  ({ s0, s1, s2, s3 } = encrypt$2(xk, s0, s1, s2, s3));
                  ((o[i++] = s0), (o[i++] = s1), (o[i++] = s2), (o[i++] = s3));
              }
              clean$1(...toClean);
              return _out;
          },
          decrypt(ciphertext, dst) {
              validateBlockDecrypt(ciphertext);
              const xk = expandKeyDecLE(key);
              let _iv = iv;
              const toClean = [xk];
              if (!isAligned32$1(_iv))
                  toClean.push((_iv = copyBytes(_iv)));
              const n32 = u32(_iv);
              dst = getOutput(ciphertext.length, dst);
              if (!isAligned32$1(ciphertext))
                  toClean.push((ciphertext = copyBytes(ciphertext)));
              complexOverlapBytes(ciphertext, dst);
              const b = u32(ciphertext);
              const o = u32(dst);
              // prettier-ignore
              let s0 = n32[0], s1 = n32[1], s2 = n32[2], s3 = n32[3];
              for (let i = 0; i + 4 <= b.length;) {
                  // prettier-ignore
                  const ps0 = s0, ps1 = s1, ps2 = s2, ps3 = s3;
                  ((s0 = b[i + 0]), (s1 = b[i + 1]), (s2 = b[i + 2]), (s3 = b[i + 3]));
                  const { s0: o0, s1: o1, s2: o2, s3: o3 } = decrypt$1(xk, s0, s1, s2, s3);
                  ((o[i++] = o0 ^ ps0), (o[i++] = o1 ^ ps1), (o[i++] = o2 ^ ps2), (o[i++] = o3 ^ ps3));
              }
              clean$1(...toClean);
              return validatePCKS(dst, pcks5);
          },
      };
  });

  /**
   * Basic utils for ARX (add-rotate-xor) salsa and chacha ciphers.

  RFC8439 requires multi-step cipher stream, where
  authKey starts with counter: 0, actual msg with counter: 1.

  For this, we need a way to re-use nonce / counter:

      const counter = new Uint8Array(4);
      chacha(..., counter, ...); // counter is now 1
      chacha(..., counter, ...); // counter is now 2

  This is complicated:

  - 32-bit counters are enough, no need for 64-bit: max ArrayBuffer size in JS is 4GB
  - Original papers don't allow mutating counters
  - Counter overflow is undefined [^1]
  - Idea A: allow providing (nonce | counter) instead of just nonce, re-use it
  - Caveat: Cannot be re-used through all cases:
  - * chacha has (counter | nonce)
  - * xchacha has (nonce16 | counter | nonce16)
  - Idea B: separate nonce / counter and provide separate API for counter re-use
  - Caveat: there are different counter sizes depending on an algorithm.
  - salsa & chacha also differ in structures of key & sigma:
    salsa20:      s[0] | k(4) | s[1] | nonce(2) | cnt(2) | s[2] | k(4) | s[3]
    chacha:       s(4) | k(8) | cnt(1) | nonce(3)
    chacha20orig: s(4) | k(8) | cnt(2) | nonce(2)
  - Idea C: helper method such as `setSalsaState(key, nonce, sigma, data)`
  - Caveat: we can't re-use counter array

  xchacha [^2] uses the subkey and remaining 8 byte nonce with ChaCha20 as normal
  (prefixed by 4 NUL bytes, since [RFC8439] specifies a 12-byte nonce).

  [^1]: https://mailarchive.ietf.org/arch/msg/cfrg/gsOnTJzcbgG6OqD8Sc0GO5aR_tU/
  [^2]: https://datatracker.ietf.org/doc/html/draft-irtf-cfrg-xchacha#appendix-A.2

   * @module
   */
  // Replaces `TextEncoder`, which is not available in all environments
  const encodeStr = (str) => Uint8Array.from(str.split(''), (c) => c.charCodeAt(0));
  const sigma16 = encodeStr('expand 16-byte k');
  const sigma32 = encodeStr('expand 32-byte k');
  const sigma16_32 = u32(sigma16);
  const sigma32_32 = u32(sigma32);
  /** Rotate left. */
  function rotl(a, b) {
      return (a << b) | (a >>> (32 - b));
  }
  // Is byte array aligned to 4 byte offset (u32)?
  function isAligned32(b) {
      return b.byteOffset % 4 === 0;
  }
  // Salsa and Chacha block length is always 512-bit
  const BLOCK_LEN = 64;
  const BLOCK_LEN32 = 16;
  // new Uint32Array([2**32])   // => Uint32Array(1) [ 0 ]
  // new Uint32Array([2**32-1]) // => Uint32Array(1) [ 4294967295 ]
  const MAX_COUNTER = 2 ** 32 - 1;
  const U32_EMPTY = Uint32Array.of();
  function runCipher(core, sigma, key, nonce, data, output, counter, rounds) {
      const len = data.length;
      const block = new Uint8Array(BLOCK_LEN);
      const b32 = u32(block);
      // Make sure that buffers aligned to 4 bytes
      const isAligned = isAligned32(data) && isAligned32(output);
      const d32 = isAligned ? u32(data) : U32_EMPTY;
      const o32 = isAligned ? u32(output) : U32_EMPTY;
      for (let pos = 0; pos < len; counter++) {
          core(sigma, key, nonce, b32, counter, rounds);
          if (counter >= MAX_COUNTER)
              throw new Error('arx: counter overflow');
          const take = Math.min(BLOCK_LEN, len - pos);
          // aligned to 4 bytes
          if (isAligned && take === BLOCK_LEN) {
              const pos32 = pos / 4;
              if (pos % 4 !== 0)
                  throw new Error('arx: invalid block position');
              for (let j = 0, posj; j < BLOCK_LEN32; j++) {
                  posj = pos32 + j;
                  o32[posj] = d32[posj] ^ b32[j];
              }
              pos += BLOCK_LEN;
              continue;
          }
          for (let j = 0, posj; j < take; j++) {
              posj = pos + j;
              output[posj] = data[posj] ^ block[j];
          }
          pos += take;
      }
  }
  /** Creates ARX-like (ChaCha, Salsa) cipher stream from core function. */
  function createCipher(core, opts) {
      const { allowShortKeys, extendNonceFn, counterLength, counterRight, rounds } = checkOpts({ allowShortKeys: false, counterLength: 8, counterRight: false, rounds: 20 }, opts);
      if (typeof core !== 'function')
          throw new Error('core must be a function');
      anumber$1(counterLength);
      anumber$1(rounds);
      abool(counterRight);
      abool(allowShortKeys);
      return (key, nonce, data, output, counter = 0) => {
          abytes$1(key, undefined, 'key');
          abytes$1(nonce, undefined, 'nonce');
          abytes$1(data, undefined, 'data');
          const len = data.length;
          if (output === undefined)
              output = new Uint8Array(len);
          abytes$1(output, undefined, 'output');
          anumber$1(counter);
          if (counter < 0 || counter >= MAX_COUNTER)
              throw new Error('arx: counter overflow');
          if (output.length < len)
              throw new Error(`arx: output (${output.length}) is shorter than data (${len})`);
          const toClean = [];
          // Key & sigma
          // key=16 -> sigma16, k=key|key
          // key=32 -> sigma32, k=key
          let l = key.length;
          let k;
          let sigma;
          if (l === 32) {
              toClean.push((k = copyBytes(key)));
              sigma = sigma32_32;
          }
          else if (l === 16 && allowShortKeys) {
              k = new Uint8Array(32);
              k.set(key);
              k.set(key, 16);
              sigma = sigma16_32;
              toClean.push(k);
          }
          else {
              abytes$1(key, 32, 'arx key');
              throw new Error('invalid key size');
              // throw new Error(`"arx key" expected Uint8Array of length 32, got length=${l}`);
          }
          // Nonce
          // salsa20:      8   (8-byte counter)
          // chacha20orig: 8   (8-byte counter)
          // chacha20:     12  (4-byte counter)
          // xsalsa20:     24  (16 -> hsalsa,  8 -> old nonce)
          // xchacha20:    24  (16 -> hchacha, 8 -> old nonce)
          // Align nonce to 4 bytes
          if (!isAligned32(nonce))
              toClean.push((nonce = copyBytes(nonce)));
          const k32 = u32(k);
          // hsalsa & hchacha: handle extended nonce
          if (extendNonceFn) {
              if (nonce.length !== 24)
                  throw new Error(`arx: extended nonce must be 24 bytes`);
              extendNonceFn(sigma, k32, u32(nonce.subarray(0, 16)), k32);
              nonce = nonce.subarray(16);
          }
          // Handle nonce counter
          const nonceNcLen = 16 - counterLength;
          if (nonceNcLen !== nonce.length)
              throw new Error(`arx: nonce must be ${nonceNcLen} or 16 bytes`);
          // Pad counter when nonce is 64 bit
          if (nonceNcLen !== 12) {
              const nc = new Uint8Array(12);
              nc.set(nonce, counterRight ? 0 : 12 - nonce.length);
              nonce = nc;
              toClean.push(nonce);
          }
          const n32 = u32(nonce);
          runCipher(core, sigma, k32, n32, data, output, counter, rounds);
          clean$1(...toClean);
          return output;
      };
  }

  /**
   * Poly1305 ([PDF](https://cr.yp.to/mac/poly1305-20050329.pdf),
   * [wiki](https://en.wikipedia.org/wiki/Poly1305))
   * is a fast and parallel secret-key message-authentication code suitable for
   * a wide variety of applications. It was standardized in
   * [RFC 8439](https://www.rfc-editor.org/rfc/rfc8439) and is now used in TLS 1.3.
   *
   * Polynomial MACs are not perfect for every situation:
   * they lack Random Key Robustness: the MAC can be forged, and can't be used in PAKE schemes.
   * See [invisible salamanders attack](https://keymaterial.net/2020/09/07/invisible-salamanders-in-aes-gcm-siv/).
   * To combat invisible salamanders, `hash(key)` can be included in ciphertext,
   * however, this would violate ciphertext indistinguishability:
   * an attacker would know which key was used - so `HKDF(key, i)`
   * could be used instead.
   *
   * Check out [original website](https://cr.yp.to/mac.html).
   * Based on Public Domain [poly1305-donna](https://github.com/floodyberry/poly1305-donna).
   * @module
   */
  // prettier-ignore
  function u8to16(a, i) {
      return (a[i++] & 0xff) | ((a[i++] & 0xff) << 8);
  }
  /** Poly1305 class. Prefer poly1305() function instead. */
  class Poly1305 {
      blockLen = 16;
      outputLen = 16;
      buffer = new Uint8Array(16);
      r = new Uint16Array(10); // Allocating 1 array with .subarray() here is slower than 3
      h = new Uint16Array(10);
      pad = new Uint16Array(8);
      pos = 0;
      finished = false;
      // Can be speed-up using BigUint64Array, at the cost of complexity
      constructor(key) {
          key = copyBytes(abytes$1(key, 32, 'key'));
          const t0 = u8to16(key, 0);
          const t1 = u8to16(key, 2);
          const t2 = u8to16(key, 4);
          const t3 = u8to16(key, 6);
          const t4 = u8to16(key, 8);
          const t5 = u8to16(key, 10);
          const t6 = u8to16(key, 12);
          const t7 = u8to16(key, 14);
          // https://github.com/floodyberry/poly1305-donna/blob/e6ad6e091d30d7f4ec2d4f978be1fcfcbce72781/poly1305-donna-16.h#L47
          this.r[0] = t0 & 0x1fff;
          this.r[1] = ((t0 >>> 13) | (t1 << 3)) & 0x1fff;
          this.r[2] = ((t1 >>> 10) | (t2 << 6)) & 0x1f03;
          this.r[3] = ((t2 >>> 7) | (t3 << 9)) & 0x1fff;
          this.r[4] = ((t3 >>> 4) | (t4 << 12)) & 0x00ff;
          this.r[5] = (t4 >>> 1) & 0x1ffe;
          this.r[6] = ((t4 >>> 14) | (t5 << 2)) & 0x1fff;
          this.r[7] = ((t5 >>> 11) | (t6 << 5)) & 0x1f81;
          this.r[8] = ((t6 >>> 8) | (t7 << 8)) & 0x1fff;
          this.r[9] = (t7 >>> 5) & 0x007f;
          for (let i = 0; i < 8; i++)
              this.pad[i] = u8to16(key, 16 + 2 * i);
      }
      process(data, offset, isLast = false) {
          const hibit = isLast ? 0 : 1 << 11;
          const { h, r } = this;
          const r0 = r[0];
          const r1 = r[1];
          const r2 = r[2];
          const r3 = r[3];
          const r4 = r[4];
          const r5 = r[5];
          const r6 = r[6];
          const r7 = r[7];
          const r8 = r[8];
          const r9 = r[9];
          const t0 = u8to16(data, offset + 0);
          const t1 = u8to16(data, offset + 2);
          const t2 = u8to16(data, offset + 4);
          const t3 = u8to16(data, offset + 6);
          const t4 = u8to16(data, offset + 8);
          const t5 = u8to16(data, offset + 10);
          const t6 = u8to16(data, offset + 12);
          const t7 = u8to16(data, offset + 14);
          let h0 = h[0] + (t0 & 0x1fff);
          let h1 = h[1] + (((t0 >>> 13) | (t1 << 3)) & 0x1fff);
          let h2 = h[2] + (((t1 >>> 10) | (t2 << 6)) & 0x1fff);
          let h3 = h[3] + (((t2 >>> 7) | (t3 << 9)) & 0x1fff);
          let h4 = h[4] + (((t3 >>> 4) | (t4 << 12)) & 0x1fff);
          let h5 = h[5] + ((t4 >>> 1) & 0x1fff);
          let h6 = h[6] + (((t4 >>> 14) | (t5 << 2)) & 0x1fff);
          let h7 = h[7] + (((t5 >>> 11) | (t6 << 5)) & 0x1fff);
          let h8 = h[8] + (((t6 >>> 8) | (t7 << 8)) & 0x1fff);
          let h9 = h[9] + ((t7 >>> 5) | hibit);
          let c = 0;
          let d0 = c + h0 * r0 + h1 * (5 * r9) + h2 * (5 * r8) + h3 * (5 * r7) + h4 * (5 * r6);
          c = d0 >>> 13;
          d0 &= 0x1fff;
          d0 += h5 * (5 * r5) + h6 * (5 * r4) + h7 * (5 * r3) + h8 * (5 * r2) + h9 * (5 * r1);
          c += d0 >>> 13;
          d0 &= 0x1fff;
          let d1 = c + h0 * r1 + h1 * r0 + h2 * (5 * r9) + h3 * (5 * r8) + h4 * (5 * r7);
          c = d1 >>> 13;
          d1 &= 0x1fff;
          d1 += h5 * (5 * r6) + h6 * (5 * r5) + h7 * (5 * r4) + h8 * (5 * r3) + h9 * (5 * r2);
          c += d1 >>> 13;
          d1 &= 0x1fff;
          let d2 = c + h0 * r2 + h1 * r1 + h2 * r0 + h3 * (5 * r9) + h4 * (5 * r8);
          c = d2 >>> 13;
          d2 &= 0x1fff;
          d2 += h5 * (5 * r7) + h6 * (5 * r6) + h7 * (5 * r5) + h8 * (5 * r4) + h9 * (5 * r3);
          c += d2 >>> 13;
          d2 &= 0x1fff;
          let d3 = c + h0 * r3 + h1 * r2 + h2 * r1 + h3 * r0 + h4 * (5 * r9);
          c = d3 >>> 13;
          d3 &= 0x1fff;
          d3 += h5 * (5 * r8) + h6 * (5 * r7) + h7 * (5 * r6) + h8 * (5 * r5) + h9 * (5 * r4);
          c += d3 >>> 13;
          d3 &= 0x1fff;
          let d4 = c + h0 * r4 + h1 * r3 + h2 * r2 + h3 * r1 + h4 * r0;
          c = d4 >>> 13;
          d4 &= 0x1fff;
          d4 += h5 * (5 * r9) + h6 * (5 * r8) + h7 * (5 * r7) + h8 * (5 * r6) + h9 * (5 * r5);
          c += d4 >>> 13;
          d4 &= 0x1fff;
          let d5 = c + h0 * r5 + h1 * r4 + h2 * r3 + h3 * r2 + h4 * r1;
          c = d5 >>> 13;
          d5 &= 0x1fff;
          d5 += h5 * r0 + h6 * (5 * r9) + h7 * (5 * r8) + h8 * (5 * r7) + h9 * (5 * r6);
          c += d5 >>> 13;
          d5 &= 0x1fff;
          let d6 = c + h0 * r6 + h1 * r5 + h2 * r4 + h3 * r3 + h4 * r2;
          c = d6 >>> 13;
          d6 &= 0x1fff;
          d6 += h5 * r1 + h6 * r0 + h7 * (5 * r9) + h8 * (5 * r8) + h9 * (5 * r7);
          c += d6 >>> 13;
          d6 &= 0x1fff;
          let d7 = c + h0 * r7 + h1 * r6 + h2 * r5 + h3 * r4 + h4 * r3;
          c = d7 >>> 13;
          d7 &= 0x1fff;
          d7 += h5 * r2 + h6 * r1 + h7 * r0 + h8 * (5 * r9) + h9 * (5 * r8);
          c += d7 >>> 13;
          d7 &= 0x1fff;
          let d8 = c + h0 * r8 + h1 * r7 + h2 * r6 + h3 * r5 + h4 * r4;
          c = d8 >>> 13;
          d8 &= 0x1fff;
          d8 += h5 * r3 + h6 * r2 + h7 * r1 + h8 * r0 + h9 * (5 * r9);
          c += d8 >>> 13;
          d8 &= 0x1fff;
          let d9 = c + h0 * r9 + h1 * r8 + h2 * r7 + h3 * r6 + h4 * r5;
          c = d9 >>> 13;
          d9 &= 0x1fff;
          d9 += h5 * r4 + h6 * r3 + h7 * r2 + h8 * r1 + h9 * r0;
          c += d9 >>> 13;
          d9 &= 0x1fff;
          c = ((c << 2) + c) | 0;
          c = (c + d0) | 0;
          d0 = c & 0x1fff;
          c = c >>> 13;
          d1 += c;
          h[0] = d0;
          h[1] = d1;
          h[2] = d2;
          h[3] = d3;
          h[4] = d4;
          h[5] = d5;
          h[6] = d6;
          h[7] = d7;
          h[8] = d8;
          h[9] = d9;
      }
      finalize() {
          const { h, pad } = this;
          const g = new Uint16Array(10);
          let c = h[1] >>> 13;
          h[1] &= 0x1fff;
          for (let i = 2; i < 10; i++) {
              h[i] += c;
              c = h[i] >>> 13;
              h[i] &= 0x1fff;
          }
          h[0] += c * 5;
          c = h[0] >>> 13;
          h[0] &= 0x1fff;
          h[1] += c;
          c = h[1] >>> 13;
          h[1] &= 0x1fff;
          h[2] += c;
          g[0] = h[0] + 5;
          c = g[0] >>> 13;
          g[0] &= 0x1fff;
          for (let i = 1; i < 10; i++) {
              g[i] = h[i] + c;
              c = g[i] >>> 13;
              g[i] &= 0x1fff;
          }
          g[9] -= 1 << 13;
          let mask = (c ^ 1) - 1;
          for (let i = 0; i < 10; i++)
              g[i] &= mask;
          mask = ~mask;
          for (let i = 0; i < 10; i++)
              h[i] = (h[i] & mask) | g[i];
          h[0] = (h[0] | (h[1] << 13)) & 0xffff;
          h[1] = ((h[1] >>> 3) | (h[2] << 10)) & 0xffff;
          h[2] = ((h[2] >>> 6) | (h[3] << 7)) & 0xffff;
          h[3] = ((h[3] >>> 9) | (h[4] << 4)) & 0xffff;
          h[4] = ((h[4] >>> 12) | (h[5] << 1) | (h[6] << 14)) & 0xffff;
          h[5] = ((h[6] >>> 2) | (h[7] << 11)) & 0xffff;
          h[6] = ((h[7] >>> 5) | (h[8] << 8)) & 0xffff;
          h[7] = ((h[8] >>> 8) | (h[9] << 5)) & 0xffff;
          let f = h[0] + pad[0];
          h[0] = f & 0xffff;
          for (let i = 1; i < 8; i++) {
              f = (((h[i] + pad[i]) | 0) + (f >>> 16)) | 0;
              h[i] = f & 0xffff;
          }
          clean$1(g);
      }
      update(data) {
          aexists$1(this);
          abytes$1(data);
          data = copyBytes(data);
          const { buffer, blockLen } = this;
          const len = data.length;
          for (let pos = 0; pos < len;) {
              const take = Math.min(blockLen - this.pos, len - pos);
              // Fast path: we have at least one block in input
              if (take === blockLen) {
                  for (; blockLen <= len - pos; pos += blockLen)
                      this.process(data, pos);
                  continue;
              }
              buffer.set(data.subarray(pos, pos + take), this.pos);
              this.pos += take;
              pos += take;
              if (this.pos === blockLen) {
                  this.process(buffer, 0, false);
                  this.pos = 0;
              }
          }
          return this;
      }
      destroy() {
          clean$1(this.h, this.r, this.buffer, this.pad);
      }
      digestInto(out) {
          aexists$1(this);
          aoutput$1(out, this);
          this.finished = true;
          const { buffer, h } = this;
          let { pos } = this;
          if (pos) {
              buffer[pos++] = 1;
              for (; pos < 16; pos++)
                  buffer[pos] = 0;
              this.process(buffer, 0, true);
          }
          this.finalize();
          let opos = 0;
          for (let i = 0; i < 8; i++) {
              out[opos++] = h[i] >>> 0;
              out[opos++] = h[i] >>> 8;
          }
          return out;
      }
      digest() {
          const { buffer, outputLen } = this;
          this.digestInto(buffer);
          const res = buffer.slice(0, outputLen);
          this.destroy();
          return res;
      }
  }
  function wrapConstructorWithKey(hashCons) {
      const hashC = (msg, key) => hashCons(key).update(msg).digest();
      const tmp = hashCons(new Uint8Array(32)); // tmp array, used just once below
      hashC.outputLen = tmp.outputLen;
      hashC.blockLen = tmp.blockLen;
      hashC.create = (key) => hashCons(key);
      return hashC;
  }
  /** Poly1305 MAC from RFC 8439. */
  const poly1305 = /** @__PURE__ */ (() => wrapConstructorWithKey((key) => new Poly1305(key)))();

  /**
   * ChaCha stream cipher, released
   * in 2008. Developed after Salsa20, ChaCha aims to increase diffusion per round.
   * It was standardized in [RFC 8439](https://www.rfc-editor.org/rfc/rfc8439) and
   * is now used in TLS 1.3.
   *
   * [XChaCha20](https://datatracker.ietf.org/doc/html/draft-irtf-cfrg-xchacha)
   * extended-nonce variant is also provided. Similar to XSalsa, it's safe to use with
   * randomly-generated nonces.
   *
   * Check out [PDF](http://cr.yp.to/chacha/chacha-20080128.pdf) and
   * [wiki](https://en.wikipedia.org/wiki/Salsa20) and
   * [website](https://cr.yp.to/chacha.html).
   *
   * @module
   */
  /** Identical to `chachaCore_small`. Unused. */
  // prettier-ignore
  function chachaCore(s, k, n, out, cnt, rounds = 20) {
      let y00 = s[0], y01 = s[1], y02 = s[2], y03 = s[3], // "expa"   "nd 3"  "2-by"  "te k"
      y04 = k[0], y05 = k[1], y06 = k[2], y07 = k[3], // Key      Key     Key     Key
      y08 = k[4], y09 = k[5], y10 = k[6], y11 = k[7], // Key      Key     Key     Key
      y12 = cnt, y13 = n[0], y14 = n[1], y15 = n[2]; // Counter  Counter	Nonce   Nonce
      // Save state to temporary variables
      let x00 = y00, x01 = y01, x02 = y02, x03 = y03, x04 = y04, x05 = y05, x06 = y06, x07 = y07, x08 = y08, x09 = y09, x10 = y10, x11 = y11, x12 = y12, x13 = y13, x14 = y14, x15 = y15;
      for (let r = 0; r < rounds; r += 2) {
          x00 = (x00 + x04) | 0;
          x12 = rotl(x12 ^ x00, 16);
          x08 = (x08 + x12) | 0;
          x04 = rotl(x04 ^ x08, 12);
          x00 = (x00 + x04) | 0;
          x12 = rotl(x12 ^ x00, 8);
          x08 = (x08 + x12) | 0;
          x04 = rotl(x04 ^ x08, 7);
          x01 = (x01 + x05) | 0;
          x13 = rotl(x13 ^ x01, 16);
          x09 = (x09 + x13) | 0;
          x05 = rotl(x05 ^ x09, 12);
          x01 = (x01 + x05) | 0;
          x13 = rotl(x13 ^ x01, 8);
          x09 = (x09 + x13) | 0;
          x05 = rotl(x05 ^ x09, 7);
          x02 = (x02 + x06) | 0;
          x14 = rotl(x14 ^ x02, 16);
          x10 = (x10 + x14) | 0;
          x06 = rotl(x06 ^ x10, 12);
          x02 = (x02 + x06) | 0;
          x14 = rotl(x14 ^ x02, 8);
          x10 = (x10 + x14) | 0;
          x06 = rotl(x06 ^ x10, 7);
          x03 = (x03 + x07) | 0;
          x15 = rotl(x15 ^ x03, 16);
          x11 = (x11 + x15) | 0;
          x07 = rotl(x07 ^ x11, 12);
          x03 = (x03 + x07) | 0;
          x15 = rotl(x15 ^ x03, 8);
          x11 = (x11 + x15) | 0;
          x07 = rotl(x07 ^ x11, 7);
          x00 = (x00 + x05) | 0;
          x15 = rotl(x15 ^ x00, 16);
          x10 = (x10 + x15) | 0;
          x05 = rotl(x05 ^ x10, 12);
          x00 = (x00 + x05) | 0;
          x15 = rotl(x15 ^ x00, 8);
          x10 = (x10 + x15) | 0;
          x05 = rotl(x05 ^ x10, 7);
          x01 = (x01 + x06) | 0;
          x12 = rotl(x12 ^ x01, 16);
          x11 = (x11 + x12) | 0;
          x06 = rotl(x06 ^ x11, 12);
          x01 = (x01 + x06) | 0;
          x12 = rotl(x12 ^ x01, 8);
          x11 = (x11 + x12) | 0;
          x06 = rotl(x06 ^ x11, 7);
          x02 = (x02 + x07) | 0;
          x13 = rotl(x13 ^ x02, 16);
          x08 = (x08 + x13) | 0;
          x07 = rotl(x07 ^ x08, 12);
          x02 = (x02 + x07) | 0;
          x13 = rotl(x13 ^ x02, 8);
          x08 = (x08 + x13) | 0;
          x07 = rotl(x07 ^ x08, 7);
          x03 = (x03 + x04) | 0;
          x14 = rotl(x14 ^ x03, 16);
          x09 = (x09 + x14) | 0;
          x04 = rotl(x04 ^ x09, 12);
          x03 = (x03 + x04) | 0;
          x14 = rotl(x14 ^ x03, 8);
          x09 = (x09 + x14) | 0;
          x04 = rotl(x04 ^ x09, 7);
      }
      // Write output
      let oi = 0;
      out[oi++] = (y00 + x00) | 0;
      out[oi++] = (y01 + x01) | 0;
      out[oi++] = (y02 + x02) | 0;
      out[oi++] = (y03 + x03) | 0;
      out[oi++] = (y04 + x04) | 0;
      out[oi++] = (y05 + x05) | 0;
      out[oi++] = (y06 + x06) | 0;
      out[oi++] = (y07 + x07) | 0;
      out[oi++] = (y08 + x08) | 0;
      out[oi++] = (y09 + x09) | 0;
      out[oi++] = (y10 + x10) | 0;
      out[oi++] = (y11 + x11) | 0;
      out[oi++] = (y12 + x12) | 0;
      out[oi++] = (y13 + x13) | 0;
      out[oi++] = (y14 + x14) | 0;
      out[oi++] = (y15 + x15) | 0;
  }
  /**
   * hchacha hashes key and nonce into key' and nonce' for xchacha20.
   * Identical to `hchacha_small`.
   * Need to find a way to merge it with `chachaCore` without 25% performance hit.
   */
  // prettier-ignore
  function hchacha(s, k, i, out) {
      let x00 = s[0], x01 = s[1], x02 = s[2], x03 = s[3], x04 = k[0], x05 = k[1], x06 = k[2], x07 = k[3], x08 = k[4], x09 = k[5], x10 = k[6], x11 = k[7], x12 = i[0], x13 = i[1], x14 = i[2], x15 = i[3];
      for (let r = 0; r < 20; r += 2) {
          x00 = (x00 + x04) | 0;
          x12 = rotl(x12 ^ x00, 16);
          x08 = (x08 + x12) | 0;
          x04 = rotl(x04 ^ x08, 12);
          x00 = (x00 + x04) | 0;
          x12 = rotl(x12 ^ x00, 8);
          x08 = (x08 + x12) | 0;
          x04 = rotl(x04 ^ x08, 7);
          x01 = (x01 + x05) | 0;
          x13 = rotl(x13 ^ x01, 16);
          x09 = (x09 + x13) | 0;
          x05 = rotl(x05 ^ x09, 12);
          x01 = (x01 + x05) | 0;
          x13 = rotl(x13 ^ x01, 8);
          x09 = (x09 + x13) | 0;
          x05 = rotl(x05 ^ x09, 7);
          x02 = (x02 + x06) | 0;
          x14 = rotl(x14 ^ x02, 16);
          x10 = (x10 + x14) | 0;
          x06 = rotl(x06 ^ x10, 12);
          x02 = (x02 + x06) | 0;
          x14 = rotl(x14 ^ x02, 8);
          x10 = (x10 + x14) | 0;
          x06 = rotl(x06 ^ x10, 7);
          x03 = (x03 + x07) | 0;
          x15 = rotl(x15 ^ x03, 16);
          x11 = (x11 + x15) | 0;
          x07 = rotl(x07 ^ x11, 12);
          x03 = (x03 + x07) | 0;
          x15 = rotl(x15 ^ x03, 8);
          x11 = (x11 + x15) | 0;
          x07 = rotl(x07 ^ x11, 7);
          x00 = (x00 + x05) | 0;
          x15 = rotl(x15 ^ x00, 16);
          x10 = (x10 + x15) | 0;
          x05 = rotl(x05 ^ x10, 12);
          x00 = (x00 + x05) | 0;
          x15 = rotl(x15 ^ x00, 8);
          x10 = (x10 + x15) | 0;
          x05 = rotl(x05 ^ x10, 7);
          x01 = (x01 + x06) | 0;
          x12 = rotl(x12 ^ x01, 16);
          x11 = (x11 + x12) | 0;
          x06 = rotl(x06 ^ x11, 12);
          x01 = (x01 + x06) | 0;
          x12 = rotl(x12 ^ x01, 8);
          x11 = (x11 + x12) | 0;
          x06 = rotl(x06 ^ x11, 7);
          x02 = (x02 + x07) | 0;
          x13 = rotl(x13 ^ x02, 16);
          x08 = (x08 + x13) | 0;
          x07 = rotl(x07 ^ x08, 12);
          x02 = (x02 + x07) | 0;
          x13 = rotl(x13 ^ x02, 8);
          x08 = (x08 + x13) | 0;
          x07 = rotl(x07 ^ x08, 7);
          x03 = (x03 + x04) | 0;
          x14 = rotl(x14 ^ x03, 16);
          x09 = (x09 + x14) | 0;
          x04 = rotl(x04 ^ x09, 12);
          x03 = (x03 + x04) | 0;
          x14 = rotl(x14 ^ x03, 8);
          x09 = (x09 + x14) | 0;
          x04 = rotl(x04 ^ x09, 7);
      }
      let oi = 0;
      out[oi++] = x00;
      out[oi++] = x01;
      out[oi++] = x02;
      out[oi++] = x03;
      out[oi++] = x12;
      out[oi++] = x13;
      out[oi++] = x14;
      out[oi++] = x15;
  }
  /**
   * ChaCha stream cipher. Conforms to RFC 8439 (IETF, TLS). 12-byte nonce, 4-byte counter.
   * With smaller nonce, it's not safe to make it random (CSPRNG), due to collision chance.
   */
  const chacha20 = /* @__PURE__ */ createCipher(chachaCore, {
      counterRight: false,
      counterLength: 4,
      allowShortKeys: false,
  });
  /**
   * XChaCha eXtended-nonce ChaCha. With 24-byte nonce, it's safe to make it random (CSPRNG).
   * See [IRTF draft](https://datatracker.ietf.org/doc/html/draft-irtf-cfrg-xchacha).
   */
  const xchacha20 = /* @__PURE__ */ createCipher(chachaCore, {
      counterRight: false,
      counterLength: 8,
      extendNonceFn: hchacha,
      allowShortKeys: false,
  });
  const ZEROS16 = /* @__PURE__ */ new Uint8Array(16);
  // Pad to digest size with zeros
  const updatePadded = (h, msg) => {
      h.update(msg);
      const leftover = msg.length % 16;
      if (leftover)
          h.update(ZEROS16.subarray(leftover));
  };
  const ZEROS32 = /* @__PURE__ */ new Uint8Array(32);
  function computeTag(fn, key, nonce, ciphertext, AAD) {
      if (AAD !== undefined)
          abytes$1(AAD, undefined, 'AAD');
      const authKey = fn(key, nonce, ZEROS32);
      const lengths = u64Lengths(ciphertext.length, AAD ? AAD.length : 0, true);
      // Methods below can be replaced with
      // return poly1305_computeTag_small(authKey, lengths, ciphertext, AAD)
      const h = poly1305.create(authKey);
      if (AAD)
          updatePadded(h, AAD);
      updatePadded(h, ciphertext);
      h.update(lengths);
      const res = h.digest();
      clean$1(authKey, lengths);
      return res;
  }
  /**
   * AEAD algorithm from RFC 8439.
   * Salsa20 and chacha (RFC 8439) use poly1305 differently.
   * We could have composed them, but it's hard because of authKey:
   * In salsa20, authKey changes position in salsa stream.
   * In chacha, authKey can't be computed inside computeTag, it modifies the counter.
   */
  const _poly1305_aead = (xorStream) => (key, nonce, AAD) => {
      const tagLength = 16;
      return {
          encrypt(plaintext, output) {
              const plength = plaintext.length;
              output = getOutput(plength + tagLength, output, false);
              output.set(plaintext);
              const oPlain = output.subarray(0, -tagLength);
              // Actual encryption
              xorStream(key, nonce, oPlain, oPlain, 1);
              const tag = computeTag(xorStream, key, nonce, oPlain, AAD);
              output.set(tag, plength); // append tag
              clean$1(tag);
              return output;
          },
          decrypt(ciphertext, output) {
              output = getOutput(ciphertext.length - tagLength, output, false);
              const data = ciphertext.subarray(0, -tagLength);
              const passedTag = ciphertext.subarray(-tagLength);
              const tag = computeTag(xorStream, key, nonce, data, AAD);
              if (!equalBytes(passedTag, tag))
                  throw new Error('invalid tag');
              output.set(ciphertext.subarray(0, -tagLength));
              // Actual decryption
              xorStream(key, nonce, output, output, 1); // start stream with i=1
              clean$1(tag);
              return output;
          },
      };
  };
  /**
   * XChaCha20-Poly1305 extended-nonce chacha.
   *
   * Can be safely used with random nonces (CSPRNG).
   * See [IRTF draft](https://datatracker.ietf.org/doc/html/draft-irtf-cfrg-xchacha).
   */
  const xchacha20poly1305 = /* @__PURE__ */ wrapCipher({ blockSize: 64, nonceLength: 24, tagLength: 16 }, _poly1305_aead(xchacha20));

  /**
   * HKDF (RFC 5869): extract + expand in one step.
   * See https://soatok.blog/2021/11/17/understanding-hkdf/.
   * @module
   */
  /**
   * HKDF-extract from spec. Less important part. `HKDF-Extract(IKM, salt) -> PRK`
   * Arguments position differs from spec (IKM is first one, since it is not optional)
   * @param hash - hash function that would be used (e.g. sha256)
   * @param ikm - input keying material, the initial key
   * @param salt - optional salt value (a non-secret random value)
   */
  function extract(hash, ikm, salt) {
      ahash$1(hash);
      // NOTE: some libraries treat zero-length array as 'not provided';
      // we don't, since we have undefined as 'not provided'
      // https://github.com/RustCrypto/KDFs/issues/15
      if (salt === undefined)
          salt = new Uint8Array(hash.outputLen);
      return hmac$2(hash, salt, ikm);
  }
  const HKDF_COUNTER = /* @__PURE__ */ Uint8Array.of(0);
  const EMPTY_BUFFER = /* @__PURE__ */ Uint8Array.of();
  /**
   * HKDF-expand from the spec. The most important part. `HKDF-Expand(PRK, info, L) -> OKM`
   * @param hash - hash function that would be used (e.g. sha256)
   * @param prk - a pseudorandom key of at least HashLen octets (usually, the output from the extract step)
   * @param info - optional context and application specific information (can be a zero-length string)
   * @param length - length of output keying material in bytes
   */
  function expand(hash, prk, info, length = 32) {
      ahash$1(hash);
      anumber$3(length, 'length');
      const olen = hash.outputLen;
      if (length > 255 * olen)
          throw new Error('Length must be <= 255*HashLen');
      const blocks = Math.ceil(length / olen);
      if (info === undefined)
          info = EMPTY_BUFFER;
      else
          abytes$3(info, undefined, 'info');
      // first L(ength) octets of T
      const okm = new Uint8Array(blocks * olen);
      // Re-use HMAC instance between blocks
      const HMAC = hmac$2.create(hash, prk);
      const HMACTmp = HMAC._cloneInto();
      const T = new Uint8Array(HMAC.outputLen);
      for (let counter = 0; counter < blocks; counter++) {
          HKDF_COUNTER[0] = counter + 1;
          // T(0) = empty string (zero length)
          // T(N) = HMAC-Hash(PRK, T(N-1) | info | N)
          HMACTmp.update(counter === 0 ? EMPTY_BUFFER : T)
              .update(info)
              .update(HKDF_COUNTER)
              .digestInto(T);
          okm.set(T, olen * counter);
          HMAC._cloneInto(HMACTmp);
      }
      HMAC.destroy();
      HMACTmp.destroy();
      clean$2(T, HKDF_COUNTER);
      return okm.slice(0, length);
  }

  var __defProp = Object.defineProperty;
  var __export = (target, all) => {
    for (var name in all)
      __defProp(target, name, { get: all[name], enumerable: true });
  };

  // core.ts
  var verifiedSymbol$2 = Symbol("verified");
  var isRecord$2 = (obj) => obj instanceof Object;
  function validateEvent$2(event) {
    if (!isRecord$2(event))
      return false;
    if (typeof event.kind !== "number")
      return false;
    if (typeof event.content !== "string")
      return false;
    if (typeof event.created_at !== "number")
      return false;
    if (typeof event.pubkey !== "string")
      return false;
    if (!event.pubkey.match(/^[a-f0-9]{64}$/))
      return false;
    if (!Array.isArray(event.tags))
      return false;
    for (let i2 = 0; i2 < event.tags.length; i2++) {
      let tag = event.tags[i2];
      if (!Array.isArray(tag))
        return false;
      for (let j = 0; j < tag.length; j++) {
        if (typeof tag[j] !== "string")
          return false;
      }
    }
    return true;
  }

  // utils.ts
  var utils_exports = {};
  __export(utils_exports, {
    binarySearch: () => binarySearch,
    bytesToHex: () => bytesToHex$5,
    hexToBytes: () => hexToBytes$3,
    insertEventIntoAscendingList: () => insertEventIntoAscendingList,
    insertEventIntoDescendingList: () => insertEventIntoDescendingList,
    mergeReverseSortedLists: () => mergeReverseSortedLists,
    normalizeURL: () => normalizeURL$1,
    utf8Decoder: () => utf8Decoder,
    utf8Encoder: () => utf8Encoder$2
  });
  var utf8Decoder = new TextDecoder("utf-8");
  var utf8Encoder$2 = new TextEncoder();
  function normalizeURL$1(url) {
    try {
      if (url.indexOf("://") === -1)
        url = "wss://" + url;
      let p = new URL(url);
      if (p.protocol === "http:")
        p.protocol = "ws:";
      else if (p.protocol === "https:")
        p.protocol = "wss:";
      p.pathname = p.pathname.replace(/\/+/g, "/");
      if (p.pathname.endsWith("/"))
        p.pathname = p.pathname.slice(0, -1);
      if (p.port === "80" && p.protocol === "ws:" || p.port === "443" && p.protocol === "wss:")
        p.port = "";
      p.searchParams.sort();
      p.hash = "";
      return p.toString();
    } catch (e) {
      throw new Error(`Invalid URL: ${url}`);
    }
  }
  function insertEventIntoDescendingList(sortedArray, event) {
    const [idx, found] = binarySearch(sortedArray, (b) => {
      if (event.id === b.id)
        return 0;
      if (event.created_at === b.created_at)
        return -1;
      return b.created_at - event.created_at;
    });
    if (!found) {
      sortedArray.splice(idx, 0, event);
    }
    return sortedArray;
  }
  function insertEventIntoAscendingList(sortedArray, event) {
    const [idx, found] = binarySearch(sortedArray, (b) => {
      if (event.id === b.id)
        return 0;
      if (event.created_at === b.created_at)
        return -1;
      return event.created_at - b.created_at;
    });
    if (!found) {
      sortedArray.splice(idx, 0, event);
    }
    return sortedArray;
  }
  function binarySearch(arr, compare) {
    let start = 0;
    let end = arr.length - 1;
    while (start <= end) {
      const mid = Math.floor((start + end) / 2);
      const cmp = compare(arr[mid]);
      if (cmp === 0) {
        return [mid, true];
      }
      if (cmp < 0) {
        end = mid - 1;
      } else {
        start = mid + 1;
      }
    }
    return [start, false];
  }
  function mergeReverseSortedLists(list1, list2) {
    const result = new Array(list1.length + list2.length);
    result.length = 0;
    let i1 = 0;
    let i2 = 0;
    let sameTimestampIds = [];
    while (i1 < list1.length && i2 < list2.length) {
      let next;
      if (list1[i1]?.created_at > list2[i2]?.created_at) {
        next = list1[i1];
        i1++;
      } else {
        next = list2[i2];
        i2++;
      }
      if (result.length > 0 && result[result.length - 1].created_at === next.created_at) {
        if (sameTimestampIds.includes(next.id))
          continue;
      } else {
        sameTimestampIds.length = 0;
      }
      result.push(next);
      sameTimestampIds.push(next.id);
    }
    while (i1 < list1.length) {
      const next = list1[i1];
      i1++;
      if (result.length > 0 && result[result.length - 1].created_at === next.created_at) {
        if (sameTimestampIds.includes(next.id))
          continue;
      } else {
        sameTimestampIds.length = 0;
      }
      result.push(next);
      sameTimestampIds.push(next.id);
    }
    while (i2 < list2.length) {
      const next = list2[i2];
      i2++;
      if (result.length > 0 && result[result.length - 1].created_at === next.created_at) {
        if (sameTimestampIds.includes(next.id))
          continue;
      } else {
        sameTimestampIds.length = 0;
      }
      result.push(next);
      sameTimestampIds.push(next.id);
    }
    return result;
  }

  // pure.ts
  var JS$2 = class JS {
    generateSecretKey() {
      return schnorr$1.utils.randomSecretKey();
    }
    getPublicKey(secretKey) {
      return bytesToHex$5(schnorr$1.getPublicKey(secretKey));
    }
    finalizeEvent(t, secretKey) {
      const event = t;
      event.pubkey = bytesToHex$5(schnorr$1.getPublicKey(secretKey));
      event.id = getEventHash$2(event);
      event.sig = bytesToHex$5(schnorr$1.sign(hexToBytes$3(getEventHash$2(event)), secretKey));
      event[verifiedSymbol$2] = true;
      return event;
    }
    verifyEvent(event) {
      if (typeof event[verifiedSymbol$2] === "boolean")
        return event[verifiedSymbol$2];
      try {
        const hash = getEventHash$2(event);
        if (hash !== event.id) {
          event[verifiedSymbol$2] = false;
          return false;
        }
        const valid = schnorr$1.verify(hexToBytes$3(event.sig), hexToBytes$3(hash), hexToBytes$3(event.pubkey));
        event[verifiedSymbol$2] = valid;
        return valid;
      } catch (err) {
        event[verifiedSymbol$2] = false;
        return false;
      }
    }
  };
  function serializeEvent$2(evt) {
    if (!validateEvent$2(evt))
      throw new Error("can't serialize event with wrong or missing properties");
    return JSON.stringify([0, evt.pubkey, evt.created_at, evt.kind, evt.tags, evt.content]);
  }
  function getEventHash$2(event) {
    let eventHash = sha256$3(utf8Encoder$2.encode(serializeEvent$2(event)));
    return bytesToHex$5(eventHash);
  }
  var i$2 = new JS$2();
  var generateSecretKey = i$2.generateSecretKey;
  var getPublicKey = i$2.getPublicKey;
  var finalizeEvent = i$2.finalizeEvent;
  var verifyEvent$1 = i$2.verifyEvent;

  // kinds.ts
  var kinds_exports = {};
  __export(kinds_exports, {
    Application: () => Application,
    BadgeAward: () => BadgeAward,
    BadgeDefinition: () => BadgeDefinition,
    BlockedRelaysList: () => BlockedRelaysList,
    BlossomServerList: () => BlossomServerList,
    BookmarkList: () => BookmarkList,
    Bookmarksets: () => Bookmarksets,
    Calendar: () => Calendar,
    CalendarEventRSVP: () => CalendarEventRSVP,
    ChannelCreation: () => ChannelCreation,
    ChannelHideMessage: () => ChannelHideMessage,
    ChannelMessage: () => ChannelMessage,
    ChannelMetadata: () => ChannelMetadata,
    ChannelMuteUser: () => ChannelMuteUser,
    ChatMessage: () => ChatMessage,
    ClassifiedListing: () => ClassifiedListing,
    ClientAuth: () => ClientAuth$1,
    Comment: () => Comment,
    CommunitiesList: () => CommunitiesList,
    CommunityDefinition: () => CommunityDefinition,
    CommunityPostApproval: () => CommunityPostApproval,
    Contacts: () => Contacts,
    CreateOrUpdateProduct: () => CreateOrUpdateProduct,
    CreateOrUpdateStall: () => CreateOrUpdateStall,
    Curationsets: () => Curationsets,
    Date: () => Date2,
    DirectMessageRelaysList: () => DirectMessageRelaysList,
    DraftClassifiedListing: () => DraftClassifiedListing,
    DraftLong: () => DraftLong,
    Emojisets: () => Emojisets,
    EncryptedDirectMessage: () => EncryptedDirectMessage,
    EventDeletion: () => EventDeletion,
    FavoriteRelays: () => FavoriteRelays,
    FileMessage: () => FileMessage,
    FileMetadata: () => FileMetadata,
    FileServerPreference: () => FileServerPreference,
    Followsets: () => Followsets,
    ForumThread: () => ForumThread,
    GenericRepost: () => GenericRepost,
    Genericlists: () => Genericlists,
    GiftWrap: () => GiftWrap,
    GroupMetadata: () => GroupMetadata,
    HTTPAuth: () => HTTPAuth,
    Handlerinformation: () => Handlerinformation,
    Handlerrecommendation: () => Handlerrecommendation,
    Highlights: () => Highlights,
    InterestsList: () => InterestsList,
    Interestsets: () => Interestsets,
    JobFeedback: () => JobFeedback,
    JobRequest: () => JobRequest,
    JobResult: () => JobResult,
    Label: () => Label,
    LightningPubRPC: () => LightningPubRPC,
    LiveChatMessage: () => LiveChatMessage,
    LiveEvent: () => LiveEvent,
    LongFormArticle: () => LongFormArticle,
    Metadata: () => Metadata,
    Mutelist: () => Mutelist,
    NWCWalletInfo: () => NWCWalletInfo,
    NWCWalletRequest: () => NWCWalletRequest,
    NWCWalletResponse: () => NWCWalletResponse,
    NormalVideo: () => NormalVideo,
    NostrConnect: () => NostrConnect,
    OpenTimestamps: () => OpenTimestamps,
    Photo: () => Photo,
    Pinlist: () => Pinlist,
    Poll: () => Poll,
    PollResponse: () => PollResponse,
    PrivateDirectMessage: () => PrivateDirectMessage,
    ProblemTracker: () => ProblemTracker,
    ProfileBadges: () => ProfileBadges,
    PublicChatsList: () => PublicChatsList,
    Reaction: () => Reaction,
    RecommendRelay: () => RecommendRelay,
    RelayList: () => RelayList,
    RelayReview: () => RelayReview,
    Relaysets: () => Relaysets,
    Report: () => Report,
    Reporting: () => Reporting,
    Repost: () => Repost,
    Seal: () => Seal,
    SearchRelaysList: () => SearchRelaysList,
    ShortTextNote: () => ShortTextNote,
    ShortVideo: () => ShortVideo,
    Time: () => Time,
    UserEmojiList: () => UserEmojiList,
    UserStatuses: () => UserStatuses,
    Voice: () => Voice,
    VoiceComment: () => VoiceComment,
    Zap: () => Zap,
    ZapGoal: () => ZapGoal,
    ZapRequest: () => ZapRequest,
    classifyKind: () => classifyKind,
    isAddressableKind: () => isAddressableKind,
    isEphemeralKind: () => isEphemeralKind,
    isKind: () => isKind,
    isRegularKind: () => isRegularKind,
    isReplaceableKind: () => isReplaceableKind
  });
  function isRegularKind(kind) {
    return kind < 1e4 && kind !== 0 && kind !== 3;
  }
  function isReplaceableKind(kind) {
    return kind === 0 || kind === 3 || 1e4 <= kind && kind < 2e4;
  }
  function isEphemeralKind(kind) {
    return 2e4 <= kind && kind < 3e4;
  }
  function isAddressableKind(kind) {
    return 3e4 <= kind && kind < 4e4;
  }
  function classifyKind(kind) {
    if (isRegularKind(kind))
      return "regular";
    if (isReplaceableKind(kind))
      return "replaceable";
    if (isEphemeralKind(kind))
      return "ephemeral";
    if (isAddressableKind(kind))
      return "parameterized";
    return "unknown";
  }
  function isKind(event, kind) {
    const kindAsArray = kind instanceof Array ? kind : [kind];
    return validateEvent$2(event) && kindAsArray.includes(event.kind) || false;
  }
  var Metadata = 0;
  var ShortTextNote = 1;
  var RecommendRelay = 2;
  var Contacts = 3;
  var EncryptedDirectMessage = 4;
  var EventDeletion = 5;
  var Repost = 6;
  var Reaction = 7;
  var BadgeAward = 8;
  var ChatMessage = 9;
  var ForumThread = 11;
  var Seal = 13;
  var PrivateDirectMessage = 14;
  var FileMessage = 15;
  var GenericRepost = 16;
  var Photo = 20;
  var NormalVideo = 21;
  var ShortVideo = 22;
  var ChannelCreation = 40;
  var ChannelMetadata = 41;
  var ChannelMessage = 42;
  var ChannelHideMessage = 43;
  var ChannelMuteUser = 44;
  var OpenTimestamps = 1040;
  var GiftWrap = 1059;
  var Poll = 1068;
  var FileMetadata = 1063;
  var Comment = 1111;
  var LiveChatMessage = 1311;
  var Voice = 1222;
  var VoiceComment = 1244;
  var ProblemTracker = 1971;
  var Report = 1984;
  var Reporting = 1984;
  var Label = 1985;
  var CommunityPostApproval = 4550;
  var JobRequest = 5999;
  var JobResult = 6999;
  var JobFeedback = 7e3;
  var ZapGoal = 9041;
  var ZapRequest = 9734;
  var Zap = 9735;
  var Highlights = 9802;
  var PollResponse = 1018;
  var Mutelist = 1e4;
  var Pinlist = 10001;
  var RelayList = 10002;
  var BookmarkList = 10003;
  var CommunitiesList = 10004;
  var PublicChatsList = 10005;
  var BlockedRelaysList = 10006;
  var SearchRelaysList = 10007;
  var FavoriteRelays = 10012;
  var InterestsList = 10015;
  var UserEmojiList = 10030;
  var DirectMessageRelaysList = 10050;
  var FileServerPreference = 10096;
  var BlossomServerList = 10063;
  var NWCWalletInfo = 13194;
  var LightningPubRPC = 21e3;
  var ClientAuth$1 = 22242;
  var NWCWalletRequest = 23194;
  var NWCWalletResponse = 23195;
  var NostrConnect = 24133;
  var HTTPAuth = 27235;
  var Followsets = 3e4;
  var Genericlists = 30001;
  var Relaysets = 30002;
  var Bookmarksets = 30003;
  var Curationsets = 30004;
  var ProfileBadges = 30008;
  var BadgeDefinition = 30009;
  var Interestsets = 30015;
  var CreateOrUpdateStall = 30017;
  var CreateOrUpdateProduct = 30018;
  var LongFormArticle = 30023;
  var DraftLong = 30024;
  var Emojisets = 30030;
  var Application = 30078;
  var LiveEvent = 30311;
  var UserStatuses = 30315;
  var ClassifiedListing = 30402;
  var DraftClassifiedListing = 30403;
  var Date2 = 31922;
  var Time = 31923;
  var Calendar = 31924;
  var CalendarEventRSVP = 31925;
  var RelayReview = 31987;
  var Handlerrecommendation = 31989;
  var Handlerinformation = 31990;
  var CommunityDefinition = 34550;
  var GroupMetadata = 39e3;

  // fakejson.ts
  var fakejson_exports = {};
  __export(fakejson_exports, {
    getHex64: () => getHex64$1,
    getInt: () => getInt,
    getSubscriptionId: () => getSubscriptionId$1,
    matchEventId: () => matchEventId,
    matchEventKind: () => matchEventKind,
    matchEventPubkey: () => matchEventPubkey
  });
  function getHex64$1(json, field) {
    let len = field.length + 3;
    let idx = json.indexOf(`"${field}":`) + len;
    let s = json.slice(idx).indexOf(`"`) + idx + 1;
    return json.slice(s, s + 64);
  }
  function getInt(json, field) {
    let len = field.length;
    let idx = json.indexOf(`"${field}":`) + len + 3;
    let sliced = json.slice(idx);
    let end = Math.min(sliced.indexOf(","), sliced.indexOf("}"));
    return parseInt(sliced.slice(0, end), 10);
  }
  function getSubscriptionId$1(json) {
    let idx = json.slice(0, 22).indexOf(`"EVENT"`);
    if (idx === -1)
      return null;
    let pstart = json.slice(idx + 7 + 1).indexOf(`"`);
    if (pstart === -1)
      return null;
    let start = idx + 7 + 1 + pstart;
    let pend = json.slice(start + 1, 80).indexOf(`"`);
    if (pend === -1)
      return null;
    let end = start + 1 + pend;
    return json.slice(start + 1, end);
  }
  function matchEventId(json, id) {
    return id === getHex64$1(json, "id");
  }
  function matchEventPubkey(json, pubkey) {
    return pubkey === getHex64$1(json, "pubkey");
  }
  function matchEventKind(json, kind) {
    return kind === getInt(json, "kind");
  }

  // nip42.ts
  var nip42_exports = {};
  __export(nip42_exports, {
    makeAuthEvent: () => makeAuthEvent$1
  });
  function makeAuthEvent$1(relayURL, challenge) {
    return {
      kind: ClientAuth$1,
      created_at: Math.floor(Date.now() / 1e3),
      tags: [
        ["relay", relayURL],
        ["challenge", challenge]
      ],
      content: ""
    };
  }

  // relay.ts
  var _WebSocket$1;
  try {
    _WebSocket$1 = WebSocket;
  } catch {
  }

  // pool.ts
  var _WebSocket2;
  try {
    _WebSocket2 = WebSocket;
  } catch {
  }

  // nip19.ts
  var nip19_exports = {};
  __export(nip19_exports, {
    BECH32_REGEX: () => BECH32_REGEX,
    Bech32MaxSize: () => Bech32MaxSize$1,
    NostrTypeGuard: () => NostrTypeGuard,
    decode: () => decode,
    decodeNostrURI: () => decodeNostrURI,
    encodeBytes: () => encodeBytes$1,
    naddrEncode: () => naddrEncode,
    neventEncode: () => neventEncode,
    noteEncode: () => noteEncode,
    nprofileEncode: () => nprofileEncode,
    npubEncode: () => npubEncode,
    nsecEncode: () => nsecEncode
  });
  var NostrTypeGuard = {
    isNProfile: (value) => /^nprofile1[a-z\d]+$/.test(value || ""),
    isNEvent: (value) => /^nevent1[a-z\d]+$/.test(value || ""),
    isNAddr: (value) => /^naddr1[a-z\d]+$/.test(value || ""),
    isNSec: (value) => /^nsec1[a-z\d]{58}$/.test(value || ""),
    isNPub: (value) => /^npub1[a-z\d]{58}$/.test(value || ""),
    isNote: (value) => /^note1[a-z\d]+$/.test(value || ""),
    isNcryptsec: (value) => /^ncryptsec1[a-z\d]+$/.test(value || "")
  };
  var Bech32MaxSize$1 = 5e3;
  var BECH32_REGEX = /[\x21-\x7E]{1,83}1[023456789acdefghjklmnpqrstuvwxyz]{6,}/;
  function integerToUint8Array(number) {
    const uint8Array = new Uint8Array(4);
    uint8Array[0] = number >> 24 & 255;
    uint8Array[1] = number >> 16 & 255;
    uint8Array[2] = number >> 8 & 255;
    uint8Array[3] = number & 255;
    return uint8Array;
  }
  function decodeNostrURI(nip19code) {
    try {
      if (nip19code.startsWith("nostr:"))
        nip19code = nip19code.substring(6);
      return decode(nip19code);
    } catch (_err) {
      return { type: "invalid", data: null };
    }
  }
  function decode(code) {
    let { prefix, words } = bech32.decode(code, Bech32MaxSize$1);
    let data = new Uint8Array(bech32.fromWords(words));
    switch (prefix) {
      case "nprofile": {
        let tlv = parseTLV(data);
        if (!tlv[0]?.[0])
          throw new Error("missing TLV 0 for nprofile");
        if (tlv[0][0].length !== 32)
          throw new Error("TLV 0 should be 32 bytes");
        return {
          type: "nprofile",
          data: {
            pubkey: bytesToHex$5(tlv[0][0]),
            relays: tlv[1] ? tlv[1].map((d) => utf8Decoder.decode(d)) : []
          }
        };
      }
      case "nevent": {
        let tlv = parseTLV(data);
        if (!tlv[0]?.[0])
          throw new Error("missing TLV 0 for nevent");
        if (tlv[0][0].length !== 32)
          throw new Error("TLV 0 should be 32 bytes");
        if (tlv[2] && tlv[2][0].length !== 32)
          throw new Error("TLV 2 should be 32 bytes");
        if (tlv[3] && tlv[3][0].length !== 4)
          throw new Error("TLV 3 should be 4 bytes");
        return {
          type: "nevent",
          data: {
            id: bytesToHex$5(tlv[0][0]),
            relays: tlv[1] ? tlv[1].map((d) => utf8Decoder.decode(d)) : [],
            author: tlv[2]?.[0] ? bytesToHex$5(tlv[2][0]) : void 0,
            kind: tlv[3]?.[0] ? parseInt(bytesToHex$5(tlv[3][0]), 16) : void 0
          }
        };
      }
      case "naddr": {
        let tlv = parseTLV(data);
        if (!tlv[0]?.[0])
          throw new Error("missing TLV 0 for naddr");
        if (!tlv[2]?.[0])
          throw new Error("missing TLV 2 for naddr");
        if (tlv[2][0].length !== 32)
          throw new Error("TLV 2 should be 32 bytes");
        if (!tlv[3]?.[0])
          throw new Error("missing TLV 3 for naddr");
        if (tlv[3][0].length !== 4)
          throw new Error("TLV 3 should be 4 bytes");
        return {
          type: "naddr",
          data: {
            identifier: utf8Decoder.decode(tlv[0][0]),
            pubkey: bytesToHex$5(tlv[2][0]),
            kind: parseInt(bytesToHex$5(tlv[3][0]), 16),
            relays: tlv[1] ? tlv[1].map((d) => utf8Decoder.decode(d)) : []
          }
        };
      }
      case "nsec":
        return { type: prefix, data };
      case "npub":
      case "note":
        return { type: prefix, data: bytesToHex$5(data) };
      default:
        throw new Error(`unknown prefix ${prefix}`);
    }
  }
  function parseTLV(data) {
    let result = {};
    let rest = data;
    while (rest.length > 0) {
      let t = rest[0];
      let l = rest[1];
      let v = rest.slice(2, 2 + l);
      rest = rest.slice(2 + l);
      if (v.length < l)
        throw new Error(`not enough data to read on TLV ${t}`);
      result[t] = result[t] || [];
      result[t].push(v);
    }
    return result;
  }
  function nsecEncode(key) {
    return encodeBytes$1("nsec", key);
  }
  function npubEncode(hex) {
    return encodeBytes$1("npub", hexToBytes$3(hex));
  }
  function noteEncode(hex) {
    return encodeBytes$1("note", hexToBytes$3(hex));
  }
  function encodeBech32$1(prefix, data) {
    let words = bech32.toWords(data);
    return bech32.encode(prefix, words, Bech32MaxSize$1);
  }
  function encodeBytes$1(prefix, bytes) {
    return encodeBech32$1(prefix, bytes);
  }
  function nprofileEncode(profile) {
    let data = encodeTLV({
      0: [hexToBytes$3(profile.pubkey)],
      1: (profile.relays || []).map((url) => utf8Encoder$2.encode(url))
    });
    return encodeBech32$1("nprofile", data);
  }
  function neventEncode(event) {
    let kindArray;
    if (event.kind !== void 0) {
      kindArray = integerToUint8Array(event.kind);
    }
    let data = encodeTLV({
      0: [hexToBytes$3(event.id)],
      1: (event.relays || []).map((url) => utf8Encoder$2.encode(url)),
      2: event.author ? [hexToBytes$3(event.author)] : [],
      3: kindArray ? [new Uint8Array(kindArray)] : []
    });
    return encodeBech32$1("nevent", data);
  }
  function naddrEncode(addr) {
    let kind = new ArrayBuffer(4);
    new DataView(kind).setUint32(0, addr.kind, false);
    let data = encodeTLV({
      0: [utf8Encoder$2.encode(addr.identifier)],
      1: (addr.relays || []).map((url) => utf8Encoder$2.encode(url)),
      2: [hexToBytes$3(addr.pubkey)],
      3: [new Uint8Array(kind)]
    });
    return encodeBech32$1("naddr", data);
  }
  function encodeTLV(tlv) {
    let entries = [];
    Object.entries(tlv).reverse().forEach(([t, vs]) => {
      vs.forEach((v) => {
        let entry = new Uint8Array(v.length + 2);
        entry.set([parseInt(t)], 0);
        entry.set([v.length], 1);
        entry.set(v, 2);
        entries.push(entry);
      });
    });
    return concatBytes$3(...entries);
  }

  // nip04.ts
  var nip04_exports = {};
  __export(nip04_exports, {
    decrypt: () => decrypt,
    encrypt: () => encrypt$1
  });
  function encrypt$1(secretKey, pubkey, text) {
    const privkey = secretKey instanceof Uint8Array ? secretKey : hexToBytes$3(secretKey);
    const key = secp256k1$2.getSharedSecret(privkey, hexToBytes$3("02" + pubkey));
    const normalizedKey = getNormalizedX(key);
    let iv = Uint8Array.from(randomBytes$2(16));
    let plaintext = utf8Encoder$2.encode(text);
    let ciphertext = cbc(normalizedKey, iv).encrypt(plaintext);
    let ctb64 = base64$1.encode(new Uint8Array(ciphertext));
    let ivb64 = base64$1.encode(new Uint8Array(iv.buffer));
    return `${ctb64}?iv=${ivb64}`;
  }
  function decrypt(secretKey, pubkey, data) {
    const privkey = secretKey instanceof Uint8Array ? secretKey : hexToBytes$3(secretKey);
    let [ctb64, ivb64] = data.split("?iv=");
    let key = secp256k1$2.getSharedSecret(privkey, hexToBytes$3("02" + pubkey));
    let normalizedKey = getNormalizedX(key);
    let iv = base64$1.decode(ivb64);
    let ciphertext = base64$1.decode(ctb64);
    let plaintext = cbc(normalizedKey, iv).decrypt(ciphertext);
    return utf8Decoder.decode(plaintext);
  }
  function getNormalizedX(key) {
    return key.slice(1, 33);
  }

  // nip05.ts
  var nip05_exports = {};
  __export(nip05_exports, {
    NIP05_REGEX: () => NIP05_REGEX,
    isNip05: () => isNip05,
    isValid: () => isValid,
    queryProfile: () => queryProfile,
    searchDomain: () => searchDomain,
    useFetchImplementation: () => useFetchImplementation
  });
  var NIP05_REGEX = /^(?:([\w.+-]+)@)?([\w_-]+(\.[\w_-]+)+)$/;
  var isNip05 = (value) => NIP05_REGEX.test(value || "");
  var _fetch;
  try {
    _fetch = fetch;
  } catch (_) {
  }
  function useFetchImplementation(fetchImplementation) {
    _fetch = fetchImplementation;
  }
  async function searchDomain(domain, query = "") {
    try {
      const url = `https://${domain}/.well-known/nostr.json?name=${query}`;
      const res = await _fetch(url, { redirect: "manual" });
      if (res.status !== 200) {
        throw Error("Wrong response code");
      }
      const json = await res.json();
      return json.names;
    } catch (_) {
      return {};
    }
  }
  async function queryProfile(fullname) {
    const match = fullname.match(NIP05_REGEX);
    if (!match)
      return null;
    const [, name = "_", domain] = match;
    try {
      const url = `https://${domain}/.well-known/nostr.json?name=${name}`;
      const res = await _fetch(url, { redirect: "manual" });
      if (res.status !== 200) {
        throw Error("Wrong response code");
      }
      const json = await res.json();
      const pubkey = json.names[name];
      return pubkey ? { pubkey, relays: json.relays?.[pubkey] } : null;
    } catch (_e) {
      return null;
    }
  }
  async function isValid(pubkey, nip05) {
    const res = await queryProfile(nip05);
    return res ? res.pubkey === pubkey : false;
  }

  // nip10.ts
  var nip10_exports = {};
  __export(nip10_exports, {
    parse: () => parse
  });
  function parse(event) {
    const result = {
      reply: void 0,
      root: void 0,
      mentions: [],
      profiles: [],
      quotes: []
    };
    let maybeParent;
    let maybeRoot;
    for (let i2 = event.tags.length - 1; i2 >= 0; i2--) {
      const tag = event.tags[i2];
      if (tag[0] === "e" && tag[1]) {
        const [_, eTagEventId, eTagRelayUrl, eTagMarker, eTagAuthor] = tag;
        const eventPointer = {
          id: eTagEventId,
          relays: eTagRelayUrl ? [eTagRelayUrl] : [],
          author: eTagAuthor
        };
        if (eTagMarker === "root") {
          result.root = eventPointer;
          continue;
        }
        if (eTagMarker === "reply") {
          result.reply = eventPointer;
          continue;
        }
        if (eTagMarker === "mention") {
          result.mentions.push(eventPointer);
          continue;
        }
        if (!maybeParent) {
          maybeParent = eventPointer;
        } else {
          maybeRoot = eventPointer;
        }
        result.mentions.push(eventPointer);
        continue;
      }
      if (tag[0] === "q" && tag[1]) {
        const [_, eTagEventId, eTagRelayUrl] = tag;
        result.quotes.push({
          id: eTagEventId,
          relays: eTagRelayUrl ? [eTagRelayUrl] : []
        });
      }
      if (tag[0] === "p" && tag[1]) {
        result.profiles.push({
          pubkey: tag[1],
          relays: tag[2] ? [tag[2]] : []
        });
        continue;
      }
    }
    if (!result.root) {
      result.root = maybeRoot || maybeParent || result.reply;
    }
    if (!result.reply) {
      result.reply = maybeParent || result.root;
    }
    [result.reply, result.root].forEach((ref) => {
      if (!ref)
        return;
      let idx = result.mentions.indexOf(ref);
      if (idx !== -1) {
        result.mentions.splice(idx, 1);
      }
      if (ref.author) {
        let author = result.profiles.find((p) => p.pubkey === ref.author);
        if (author && author.relays) {
          if (!ref.relays) {
            ref.relays = [];
          }
          author.relays.forEach((url) => {
            if (ref.relays?.indexOf(url) === -1)
              ref.relays.push(url);
          });
          author.relays = ref.relays;
        }
      }
    });
    result.mentions.forEach((ref) => {
      if (ref.author) {
        let author = result.profiles.find((p) => p.pubkey === ref.author);
        if (author && author.relays) {
          if (!ref.relays) {
            ref.relays = [];
          }
          author.relays.forEach((url) => {
            if (ref.relays.indexOf(url) === -1)
              ref.relays.push(url);
          });
          author.relays = ref.relays;
        }
      }
    });
    return result;
  }

  // nip11.ts
  var nip11_exports = {};
  __export(nip11_exports, {
    fetchRelayInformation: () => fetchRelayInformation,
    useFetchImplementation: () => useFetchImplementation2
  });
  var _fetch2;
  try {
    _fetch2 = fetch;
  } catch {
  }
  function useFetchImplementation2(fetchImplementation) {
    _fetch2 = fetchImplementation;
  }
  async function fetchRelayInformation(url) {
    return await (await fetch(url.replace("ws://", "http://").replace("wss://", "https://"), {
      headers: { Accept: "application/nostr+json" }
    })).json();
  }

  // nip13.ts
  var nip13_exports = {};
  __export(nip13_exports, {
    getPow: () => getPow,
    minePow: () => minePow
  });
  function getPow(hex) {
    let count = 0;
    for (let i2 = 0; i2 < 64; i2 += 8) {
      const nibble = parseInt(hex.substring(i2, i2 + 8), 16);
      if (nibble === 0) {
        count += 32;
      } else {
        count += Math.clz32(nibble);
        break;
      }
    }
    return count;
  }
  function getPowFromBytes(hash) {
    let count = 0;
    for (let i2 = 0; i2 < hash.length; i2++) {
      const byte = hash[i2];
      if (byte === 0) {
        count += 8;
      } else {
        count += Math.clz32(byte) - 24;
        break;
      }
    }
    return count;
  }
  function minePow(unsigned, difficulty) {
    let count = 0;
    const event = unsigned;
    const tag = ["nonce", count.toString(), difficulty.toString()];
    event.tags.push(tag);
    while (true) {
      const now2 = Math.floor(new Date().getTime() / 1e3);
      if (now2 !== event.created_at) {
        count = 0;
        event.created_at = now2;
      }
      tag[1] = (++count).toString();
      const hash = sha256$3(
        utf8Encoder$2.encode(JSON.stringify([0, event.pubkey, event.created_at, event.kind, event.tags, event.content]))
      );
      if (getPowFromBytes(hash) >= difficulty) {
        event.id = bytesToHex$5(hash);
        break;
      }
    }
    return event;
  }

  // nip17.ts
  var nip17_exports = {};
  __export(nip17_exports, {
    unwrapEvent: () => unwrapEvent2,
    unwrapManyEvents: () => unwrapManyEvents2,
    wrapEvent: () => wrapEvent2,
    wrapManyEvents: () => wrapManyEvents2
  });

  // nip59.ts
  var nip59_exports = {};
  __export(nip59_exports, {
    createRumor: () => createRumor,
    createSeal: () => createSeal,
    createWrap: () => createWrap,
    unwrapEvent: () => unwrapEvent,
    unwrapManyEvents: () => unwrapManyEvents,
    wrapEvent: () => wrapEvent,
    wrapManyEvents: () => wrapManyEvents
  });

  // nip44.ts
  var nip44_exports = {};
  __export(nip44_exports, {
    decrypt: () => decrypt2,
    encrypt: () => encrypt2,
    getConversationKey: () => getConversationKey,
    v2: () => v2
  });
  var minPlaintextSize = 1;
  var maxPlaintextSize = 65535;
  function getConversationKey(privkeyA, pubkeyB) {
    const sharedX = secp256k1$2.getSharedSecret(privkeyA, hexToBytes$3("02" + pubkeyB)).subarray(1, 33);
    return extract(sha256$3, sharedX, utf8Encoder$2.encode("nip44-v2"));
  }
  function getMessageKeys(conversationKey, nonce) {
    const keys = expand(sha256$3, conversationKey, nonce, 76);
    return {
      chacha_key: keys.subarray(0, 32),
      chacha_nonce: keys.subarray(32, 44),
      hmac_key: keys.subarray(44, 76)
    };
  }
  function calcPaddedLen(len) {
    if (!Number.isSafeInteger(len) || len < 1)
      throw new Error("expected positive integer");
    if (len <= 32)
      return 32;
    const nextPower = 1 << Math.floor(Math.log2(len - 1)) + 1;
    const chunk = nextPower <= 256 ? 32 : nextPower / 8;
    return chunk * (Math.floor((len - 1) / chunk) + 1);
  }
  function writeU16BE(num) {
    if (!Number.isSafeInteger(num) || num < minPlaintextSize || num > maxPlaintextSize)
      throw new Error("invalid plaintext size: must be between 1 and 65535 bytes");
    const arr = new Uint8Array(2);
    new DataView(arr.buffer).setUint16(0, num, false);
    return arr;
  }
  function pad(plaintext) {
    const unpadded = utf8Encoder$2.encode(plaintext);
    const unpaddedLen = unpadded.length;
    const prefix = writeU16BE(unpaddedLen);
    const suffix = new Uint8Array(calcPaddedLen(unpaddedLen) - unpaddedLen);
    return concatBytes$3(prefix, unpadded, suffix);
  }
  function unpad(padded) {
    const unpaddedLen = new DataView(padded.buffer).getUint16(0);
    const unpadded = padded.subarray(2, 2 + unpaddedLen);
    if (unpaddedLen < minPlaintextSize || unpaddedLen > maxPlaintextSize || unpadded.length !== unpaddedLen || padded.length !== 2 + calcPaddedLen(unpaddedLen))
      throw new Error("invalid padding");
    return utf8Decoder.decode(unpadded);
  }
  function hmacAad(key, message, aad) {
    if (aad.length !== 32)
      throw new Error("AAD associated data must be 32 bytes");
    const combined = concatBytes$3(aad, message);
    return hmac$2(sha256$3, key, combined);
  }
  function decodePayload(payload) {
    if (typeof payload !== "string")
      throw new Error("payload must be a valid string");
    const plen = payload.length;
    if (plen < 132 || plen > 87472)
      throw new Error("invalid payload length: " + plen);
    if (payload[0] === "#")
      throw new Error("unknown encryption version");
    let data;
    try {
      data = base64$1.decode(payload);
    } catch (error) {
      throw new Error("invalid base64: " + error.message);
    }
    const dlen = data.length;
    if (dlen < 99 || dlen > 65603)
      throw new Error("invalid data length: " + dlen);
    const vers = data[0];
    if (vers !== 2)
      throw new Error("unknown encryption version " + vers);
    return {
      nonce: data.subarray(1, 33),
      ciphertext: data.subarray(33, -32),
      mac: data.subarray(-32)
    };
  }
  function encrypt2(plaintext, conversationKey, nonce = randomBytes$2(32)) {
    const { chacha_key, chacha_nonce, hmac_key } = getMessageKeys(conversationKey, nonce);
    const padded = pad(plaintext);
    const ciphertext = chacha20(chacha_key, chacha_nonce, padded);
    const mac = hmacAad(hmac_key, ciphertext, nonce);
    return base64$1.encode(concatBytes$3(new Uint8Array([2]), nonce, ciphertext, mac));
  }
  function decrypt2(payload, conversationKey) {
    const { nonce, ciphertext, mac } = decodePayload(payload);
    const { chacha_key, chacha_nonce, hmac_key } = getMessageKeys(conversationKey, nonce);
    const calculatedMac = hmacAad(hmac_key, ciphertext, nonce);
    if (!equalBytes(calculatedMac, mac))
      throw new Error("invalid MAC");
    const padded = chacha20(chacha_key, chacha_nonce, ciphertext);
    return unpad(padded);
  }
  var v2 = {
    utils: {
      getConversationKey,
      calcPaddedLen
    },
    encrypt: encrypt2,
    decrypt: decrypt2
  };

  // nip59.ts
  var TWO_DAYS = 2 * 24 * 60 * 60;
  var now = () => Math.round(Date.now() / 1e3);
  var randomNow = () => Math.round(now() - Math.random() * TWO_DAYS);
  var nip44ConversationKey = (privateKey, publicKey) => getConversationKey(privateKey, publicKey);
  var nip44Encrypt = (data, privateKey, publicKey) => encrypt2(JSON.stringify(data), nip44ConversationKey(privateKey, publicKey));
  var nip44Decrypt = (data, privateKey) => JSON.parse(decrypt2(data.content, nip44ConversationKey(privateKey, data.pubkey)));
  function createRumor(event, privateKey) {
    const rumor = {
      created_at: now(),
      content: "",
      tags: [],
      ...event,
      pubkey: getPublicKey(privateKey)
    };
    rumor.id = getEventHash$2(rumor);
    return rumor;
  }
  function createSeal(rumor, privateKey, recipientPublicKey) {
    return finalizeEvent(
      {
        kind: Seal,
        content: nip44Encrypt(rumor, privateKey, recipientPublicKey),
        created_at: randomNow(),
        tags: []
      },
      privateKey
    );
  }
  function createWrap(seal, recipientPublicKey) {
    const randomKey = generateSecretKey();
    return finalizeEvent(
      {
        kind: GiftWrap,
        content: nip44Encrypt(seal, randomKey, recipientPublicKey),
        created_at: randomNow(),
        tags: [["p", recipientPublicKey]]
      },
      randomKey
    );
  }
  function wrapEvent(event, senderPrivateKey, recipientPublicKey) {
    const rumor = createRumor(event, senderPrivateKey);
    const seal = createSeal(rumor, senderPrivateKey, recipientPublicKey);
    return createWrap(seal, recipientPublicKey);
  }
  function wrapManyEvents(event, senderPrivateKey, recipientsPublicKeys) {
    if (!recipientsPublicKeys || recipientsPublicKeys.length === 0) {
      throw new Error("At least one recipient is required.");
    }
    const senderPublicKey = getPublicKey(senderPrivateKey);
    const wrappeds = [wrapEvent(event, senderPrivateKey, senderPublicKey)];
    recipientsPublicKeys.forEach((recipientPublicKey) => {
      wrappeds.push(wrapEvent(event, senderPrivateKey, recipientPublicKey));
    });
    return wrappeds;
  }
  function unwrapEvent(wrap, recipientPrivateKey) {
    const unwrappedSeal = nip44Decrypt(wrap, recipientPrivateKey);
    return nip44Decrypt(unwrappedSeal, recipientPrivateKey);
  }
  function unwrapManyEvents(wrappedEvents, recipientPrivateKey) {
    let unwrappedEvents = [];
    wrappedEvents.forEach((e) => {
      unwrappedEvents.push(unwrapEvent(e, recipientPrivateKey));
    });
    unwrappedEvents.sort((a, b) => a.created_at - b.created_at);
    return unwrappedEvents;
  }

  // nip17.ts
  function createEvent(recipients, message, conversationTitle, replyTo) {
    const baseEvent = {
      created_at: Math.ceil(Date.now() / 1e3),
      kind: PrivateDirectMessage,
      tags: [],
      content: message
    };
    const recipientsArray = Array.isArray(recipients) ? recipients : [recipients];
    recipientsArray.forEach(({ publicKey, relayUrl }) => {
      baseEvent.tags.push(relayUrl ? ["p", publicKey, relayUrl] : ["p", publicKey]);
    });
    if (replyTo) {
      baseEvent.tags.push(["e", replyTo.eventId, replyTo.relayUrl || "", "reply"]);
    }
    if (conversationTitle) {
      baseEvent.tags.push(["subject", conversationTitle]);
    }
    return baseEvent;
  }
  function wrapEvent2(senderPrivateKey, recipient, message, conversationTitle, replyTo) {
    const event = createEvent(recipient, message, conversationTitle, replyTo);
    return wrapEvent(event, senderPrivateKey, recipient.publicKey);
  }
  function wrapManyEvents2(senderPrivateKey, recipients, message, conversationTitle, replyTo) {
    if (!recipients || recipients.length === 0) {
      throw new Error("At least one recipient is required.");
    }
    const senderPublicKey = getPublicKey(senderPrivateKey);
    return [{ publicKey: senderPublicKey }, ...recipients].map(
      (recipient) => wrapEvent2(senderPrivateKey, recipient, message, conversationTitle, replyTo)
    );
  }
  var unwrapEvent2 = unwrapEvent;
  var unwrapManyEvents2 = unwrapManyEvents;

  // nip18.ts
  var nip18_exports = {};
  __export(nip18_exports, {
    finishRepostEvent: () => finishRepostEvent,
    getRepostedEvent: () => getRepostedEvent,
    getRepostedEventPointer: () => getRepostedEventPointer
  });
  function finishRepostEvent(t, reposted, relayUrl, privateKey) {
    let kind;
    const tags = [...t.tags ?? [], ["e", reposted.id, relayUrl], ["p", reposted.pubkey]];
    if (reposted.kind === ShortTextNote) {
      kind = Repost;
    } else {
      kind = GenericRepost;
      tags.push(["k", String(reposted.kind)]);
    }
    return finalizeEvent(
      {
        kind,
        tags,
        content: t.content === "" || reposted.tags?.find((tag) => tag[0] === "-") ? "" : JSON.stringify(reposted),
        created_at: t.created_at
      },
      privateKey
    );
  }
  function getRepostedEventPointer(event) {
    if (![Repost, GenericRepost].includes(event.kind)) {
      return void 0;
    }
    let lastETag;
    let lastPTag;
    for (let i2 = event.tags.length - 1; i2 >= 0 && (lastETag === void 0 || lastPTag === void 0); i2--) {
      const tag = event.tags[i2];
      if (tag.length >= 2) {
        if (tag[0] === "e" && lastETag === void 0) {
          lastETag = tag;
        } else if (tag[0] === "p" && lastPTag === void 0) {
          lastPTag = tag;
        }
      }
    }
    if (lastETag === void 0) {
      return void 0;
    }
    return {
      id: lastETag[1],
      relays: [lastETag[2], lastPTag?.[2]].filter((x) => typeof x === "string"),
      author: lastPTag?.[1]
    };
  }
  function getRepostedEvent(event, { skipVerification } = {}) {
    const pointer = getRepostedEventPointer(event);
    if (pointer === void 0 || event.content === "") {
      return void 0;
    }
    let repostedEvent;
    try {
      repostedEvent = JSON.parse(event.content);
    } catch (error) {
      return void 0;
    }
    if (repostedEvent.id !== pointer.id) {
      return void 0;
    }
    if (!skipVerification && !verifyEvent$1(repostedEvent)) {
      return void 0;
    }
    return repostedEvent;
  }

  // nip21.ts
  var nip21_exports = {};
  __export(nip21_exports, {
    NOSTR_URI_REGEX: () => NOSTR_URI_REGEX,
    parse: () => parse2,
    test: () => test
  });
  var NOSTR_URI_REGEX = new RegExp(`nostr:(${BECH32_REGEX.source})`);
  function test(value) {
    return typeof value === "string" && new RegExp(`^${NOSTR_URI_REGEX.source}$`).test(value);
  }
  function parse2(uri) {
    const match = uri.match(new RegExp(`^${NOSTR_URI_REGEX.source}$`));
    if (!match)
      throw new Error(`Invalid Nostr URI: ${uri}`);
    return {
      uri: match[0],
      value: match[1],
      decoded: decode(match[1])
    };
  }

  // nip25.ts
  var nip25_exports = {};
  __export(nip25_exports, {
    finishReactionEvent: () => finishReactionEvent,
    getReactedEventPointer: () => getReactedEventPointer
  });
  function finishReactionEvent(t, reacted, privateKey) {
    const inheritedTags = reacted.tags.filter((tag) => tag.length >= 2 && (tag[0] === "e" || tag[0] === "p"));
    return finalizeEvent(
      {
        ...t,
        kind: Reaction,
        tags: [...t.tags ?? [], ...inheritedTags, ["e", reacted.id], ["p", reacted.pubkey]],
        content: t.content ?? "+"
      },
      privateKey
    );
  }
  function getReactedEventPointer(event) {
    if (event.kind !== Reaction) {
      return void 0;
    }
    let lastETag;
    let lastPTag;
    for (let i2 = event.tags.length - 1; i2 >= 0 && (lastETag === void 0 || lastPTag === void 0); i2--) {
      const tag = event.tags[i2];
      if (tag.length >= 2) {
        if (tag[0] === "e" && lastETag === void 0) {
          lastETag = tag;
        } else if (tag[0] === "p" && lastPTag === void 0) {
          lastPTag = tag;
        }
      }
    }
    if (lastETag === void 0 || lastPTag === void 0) {
      return void 0;
    }
    return {
      id: lastETag[1],
      relays: [lastETag[2], lastPTag[2]].filter((x) => x !== void 0),
      author: lastPTag[1]
    };
  }

  // nip27.ts
  var nip27_exports = {};
  __export(nip27_exports, {
    parse: () => parse3
  });
  var noCharacter = /\W/m;
  var noURLCharacter = /[^\w\/] |[^\w\/]$|$|,| /m;
  var MAX_HASHTAG_LENGTH = 42;
  function* parse3(content) {
    let emojis = [];
    if (typeof content !== "string") {
      for (let i2 = 0; i2 < content.tags.length; i2++) {
        const tag = content.tags[i2];
        if (tag[0] === "emoji" && tag.length >= 3) {
          emojis.push({ type: "emoji", shortcode: tag[1], url: tag[2] });
        }
      }
      content = content.content;
    }
    const max = content.length;
    let prevIndex = 0;
    let index = 0;
    mainloop:
      while (index < max) {
        const u = content.indexOf(":", index);
        const h = content.indexOf("#", index);
        if (u === -1 && h === -1) {
          break mainloop;
        }
        if (u === -1 || h >= 0 && h < u) {
          if (h === 0 || content[h - 1].match(noCharacter)) {
            const m = content.slice(h + 1, h + MAX_HASHTAG_LENGTH).match(noCharacter);
            const end = m ? h + 1 + m.index : max;
            yield { type: "text", text: content.slice(prevIndex, h) };
            yield { type: "hashtag", value: content.slice(h + 1, end) };
            index = end;
            prevIndex = index;
            continue mainloop;
          }
          index = h + 1;
          continue mainloop;
        }
        if (content.slice(u - 5, u) === "nostr") {
          const m = content.slice(u + 60).match(noCharacter);
          const end = m ? u + 60 + m.index : max;
          try {
            let pointer;
            let { data, type } = decode(content.slice(u + 1, end));
            switch (type) {
              case "npub":
                pointer = { pubkey: data };
                break;
              case "note":
                pointer = { id: data };
                break;
              case "nsec":
                index = end + 1;
                continue;
              default:
                pointer = data;
            }
            if (prevIndex !== u - 5) {
              yield { type: "text", text: content.slice(prevIndex, u - 5) };
            }
            yield { type: "reference", pointer };
            index = end;
            prevIndex = index;
            continue mainloop;
          } catch (_err) {
            index = u + 1;
            continue mainloop;
          }
        } else if (content.slice(u - 5, u) === "https" || content.slice(u - 4, u) === "http") {
          const m = content.slice(u + 4).match(noURLCharacter);
          const end = m ? u + 4 + m.index : max;
          const prefixLen = content[u - 1] === "s" ? 5 : 4;
          try {
            let url = new URL(content.slice(u - prefixLen, end));
            if (url.hostname.indexOf(".") === -1) {
              throw new Error("invalid url");
            }
            if (prevIndex !== u - prefixLen) {
              yield { type: "text", text: content.slice(prevIndex, u - prefixLen) };
            }
            if (/\.(png|jpe?g|gif|webp|heic|svg)$/i.test(url.pathname)) {
              yield { type: "image", url: url.toString() };
              index = end;
              prevIndex = index;
              continue mainloop;
            }
            if (/\.(mp4|avi|webm|mkv|mov)$/i.test(url.pathname)) {
              yield { type: "video", url: url.toString() };
              index = end;
              prevIndex = index;
              continue mainloop;
            }
            if (/\.(mp3|aac|ogg|opus|wav|flac)$/i.test(url.pathname)) {
              yield { type: "audio", url: url.toString() };
              index = end;
              prevIndex = index;
              continue mainloop;
            }
            yield { type: "url", url: url.toString() };
            index = end;
            prevIndex = index;
            continue mainloop;
          } catch (_err) {
            index = end + 1;
            continue mainloop;
          }
        } else if (content.slice(u - 3, u) === "wss" || content.slice(u - 2, u) === "ws") {
          const m = content.slice(u + 4).match(noURLCharacter);
          const end = m ? u + 4 + m.index : max;
          const prefixLen = content[u - 1] === "s" ? 3 : 2;
          try {
            let url = new URL(content.slice(u - prefixLen, end));
            if (url.hostname.indexOf(".") === -1) {
              throw new Error("invalid ws url");
            }
            if (prevIndex !== u - prefixLen) {
              yield { type: "text", text: content.slice(prevIndex, u - prefixLen) };
            }
            yield { type: "relay", url: url.toString() };
            index = end;
            prevIndex = index;
            continue mainloop;
          } catch (_err) {
            index = end + 1;
            continue mainloop;
          }
        } else {
          for (let e = 0; e < emojis.length; e++) {
            const emoji = emojis[e];
            if (content[u + emoji.shortcode.length + 1] === ":" && content.slice(u + 1, u + emoji.shortcode.length + 1) === emoji.shortcode) {
              if (prevIndex !== u) {
                yield { type: "text", text: content.slice(prevIndex, u) };
              }
              yield emoji;
              index = u + emoji.shortcode.length + 2;
              prevIndex = index;
              continue mainloop;
            }
          }
          index = u + 1;
          continue mainloop;
        }
      }
    if (prevIndex !== max) {
      yield { type: "text", text: content.slice(prevIndex) };
    }
  }

  // nip28.ts
  var nip28_exports = {};
  __export(nip28_exports, {
    channelCreateEvent: () => channelCreateEvent,
    channelHideMessageEvent: () => channelHideMessageEvent,
    channelMessageEvent: () => channelMessageEvent,
    channelMetadataEvent: () => channelMetadataEvent,
    channelMuteUserEvent: () => channelMuteUserEvent
  });
  var channelCreateEvent = (t, privateKey) => {
    let content;
    if (typeof t.content === "object") {
      content = JSON.stringify(t.content);
    } else if (typeof t.content === "string") {
      content = t.content;
    } else {
      return void 0;
    }
    return finalizeEvent(
      {
        kind: ChannelCreation,
        tags: [...t.tags ?? []],
        content,
        created_at: t.created_at
      },
      privateKey
    );
  };
  var channelMetadataEvent = (t, privateKey) => {
    let content;
    if (typeof t.content === "object") {
      content = JSON.stringify(t.content);
    } else if (typeof t.content === "string") {
      content = t.content;
    } else {
      return void 0;
    }
    return finalizeEvent(
      {
        kind: ChannelMetadata,
        tags: [["e", t.channel_create_event_id], ...t.tags ?? []],
        content,
        created_at: t.created_at
      },
      privateKey
    );
  };
  var channelMessageEvent = (t, privateKey) => {
    const tags = [["e", t.channel_create_event_id, t.relay_url, "root"]];
    if (t.reply_to_channel_message_event_id) {
      tags.push(["e", t.reply_to_channel_message_event_id, t.relay_url, "reply"]);
    }
    return finalizeEvent(
      {
        kind: ChannelMessage,
        tags: [...tags, ...t.tags ?? []],
        content: t.content,
        created_at: t.created_at
      },
      privateKey
    );
  };
  var channelHideMessageEvent = (t, privateKey) => {
    let content;
    if (typeof t.content === "object") {
      content = JSON.stringify(t.content);
    } else if (typeof t.content === "string") {
      content = t.content;
    } else {
      return void 0;
    }
    return finalizeEvent(
      {
        kind: ChannelHideMessage,
        tags: [["e", t.channel_message_event_id], ...t.tags ?? []],
        content,
        created_at: t.created_at
      },
      privateKey
    );
  };
  var channelMuteUserEvent = (t, privateKey) => {
    let content;
    if (typeof t.content === "object") {
      content = JSON.stringify(t.content);
    } else if (typeof t.content === "string") {
      content = t.content;
    } else {
      return void 0;
    }
    return finalizeEvent(
      {
        kind: ChannelMuteUser,
        tags: [["p", t.pubkey_to_mute], ...t.tags ?? []],
        content,
        created_at: t.created_at
      },
      privateKey
    );
  };

  // nip30.ts
  var nip30_exports = {};
  __export(nip30_exports, {
    EMOJI_SHORTCODE_REGEX: () => EMOJI_SHORTCODE_REGEX,
    matchAll: () => matchAll,
    regex: () => regex,
    replaceAll: () => replaceAll
  });
  var EMOJI_SHORTCODE_REGEX = /:(\w+):/;
  var regex = () => new RegExp(`\\B${EMOJI_SHORTCODE_REGEX.source}\\B`, "g");
  function* matchAll(content) {
    const matches = content.matchAll(regex());
    for (const match of matches) {
      try {
        const [shortcode, name] = match;
        yield {
          shortcode,
          name,
          start: match.index,
          end: match.index + shortcode.length
        };
      } catch (_e) {
      }
    }
  }
  function replaceAll(content, replacer) {
    return content.replaceAll(regex(), (shortcode, name) => {
      return replacer({
        shortcode,
        name
      });
    });
  }

  // nip39.ts
  var nip39_exports = {};
  __export(nip39_exports, {
    useFetchImplementation: () => useFetchImplementation3,
    validateGithub: () => validateGithub
  });
  var _fetch3;
  try {
    _fetch3 = fetch;
  } catch {
  }
  function useFetchImplementation3(fetchImplementation) {
    _fetch3 = fetchImplementation;
  }
  async function validateGithub(pubkey, username, proof) {
    try {
      let res = await (await _fetch3(`https://gist.github.com/${username}/${proof}/raw`)).text();
      return res === `Verifying that I control the following Nostr public key: ${pubkey}`;
    } catch (_) {
      return false;
    }
  }

  // nip47.ts
  var nip47_exports = {};
  __export(nip47_exports, {
    makeNwcRequestEvent: () => makeNwcRequestEvent,
    parseConnectionString: () => parseConnectionString
  });
  function parseConnectionString(connectionString) {
    const { host, pathname, searchParams } = new URL(connectionString);
    const pubkey = pathname || host;
    const relay = searchParams.get("relay");
    const secret = searchParams.get("secret");
    if (!pubkey || !relay || !secret) {
      throw new Error("invalid connection string");
    }
    return { pubkey, relay, secret };
  }
  async function makeNwcRequestEvent(pubkey, secretKey, invoice) {
    const content = {
      method: "pay_invoice",
      params: {
        invoice
      }
    };
    const encryptedContent = encrypt$1(secretKey, pubkey, JSON.stringify(content));
    const eventTemplate = {
      kind: NWCWalletRequest,
      created_at: Math.round(Date.now() / 1e3),
      content: encryptedContent,
      tags: [["p", pubkey]]
    };
    return finalizeEvent(eventTemplate, secretKey);
  }

  // nip54.ts
  var nip54_exports = {};
  __export(nip54_exports, {
    normalizeIdentifier: () => normalizeIdentifier
  });
  function normalizeIdentifier(name) {
    name = name.trim().toLowerCase();
    name = name.normalize("NFKC");
    return Array.from(name).map((char) => {
      if (/\p{Letter}/u.test(char) || /\p{Number}/u.test(char)) {
        return char;
      }
      return "-";
    }).join("");
  }

  // nip57.ts
  var nip57_exports = {};
  __export(nip57_exports, {
    getSatoshisAmountFromBolt11: () => getSatoshisAmountFromBolt11,
    getZapEndpoint: () => getZapEndpoint,
    makeZapReceipt: () => makeZapReceipt,
    makeZapRequest: () => makeZapRequest,
    useFetchImplementation: () => useFetchImplementation4,
    validateZapRequest: () => validateZapRequest
  });
  var _fetch4;
  try {
    _fetch4 = fetch;
  } catch {
  }
  function useFetchImplementation4(fetchImplementation) {
    _fetch4 = fetchImplementation;
  }
  async function getZapEndpoint(metadata) {
    try {
      let lnurl = "";
      let { lud06, lud16 } = JSON.parse(metadata.content);
      if (lud16) {
        let [name, domain] = lud16.split("@");
        lnurl = new URL(`/.well-known/lnurlp/${name}`, `https://${domain}`).toString();
      } else if (lud06) {
        let { words } = bech32.decode(lud06, 1e3);
        let data = bech32.fromWords(words);
        lnurl = utf8Decoder.decode(data);
      } else {
        return null;
      }
      let res = await _fetch4(lnurl);
      let body = await res.json();
      if (body.allowsNostr && body.nostrPubkey) {
        return body.callback;
      }
    } catch (err) {
    }
    return null;
  }
  function makeZapRequest(params) {
    let zr = {
      kind: 9734,
      created_at: Math.round(Date.now() / 1e3),
      content: params.comment || "",
      tags: [
        ["p", "pubkey" in params ? params.pubkey : params.event.pubkey],
        ["amount", params.amount.toString()],
        ["relays", ...params.relays]
      ]
    };
    if ("event" in params) {
      zr.tags.push(["e", params.event.id]);
      if (isReplaceableKind(params.event.kind)) {
        const a = ["a", `${params.event.kind}:${params.event.pubkey}:`];
        zr.tags.push(a);
      } else if (isAddressableKind(params.event.kind)) {
        let d = params.event.tags.find(([t, v]) => t === "d" && v);
        if (!d)
          throw new Error("d tag not found or is empty");
        const a = ["a", `${params.event.kind}:${params.event.pubkey}:${d[1]}`];
        zr.tags.push(a);
      }
      zr.tags.push(["k", params.event.kind.toString()]);
    }
    return zr;
  }
  function validateZapRequest(zapRequestString) {
    let zapRequest;
    try {
      zapRequest = JSON.parse(zapRequestString);
    } catch (err) {
      return "Invalid zap request JSON.";
    }
    if (!validateEvent$2(zapRequest))
      return "Zap request is not a valid Nostr event.";
    if (!verifyEvent$1(zapRequest))
      return "Invalid signature on zap request.";
    let p = zapRequest.tags.find(([t, v]) => t === "p" && v);
    if (!p)
      return "Zap request doesn't have a 'p' tag.";
    if (!p[1].match(/^[a-f0-9]{64}$/))
      return "Zap request 'p' tag is not valid hex.";
    let e = zapRequest.tags.find(([t, v]) => t === "e" && v);
    if (e && !e[1].match(/^[a-f0-9]{64}$/))
      return "Zap request 'e' tag is not valid hex.";
    let relays = zapRequest.tags.find(([t, v]) => t === "relays" && v);
    if (!relays)
      return "Zap request doesn't have a 'relays' tag.";
    return null;
  }
  function makeZapReceipt({
    zapRequest,
    preimage,
    bolt11,
    paidAt
  }) {
    let zr = JSON.parse(zapRequest);
    let tagsFromZapRequest = zr.tags.filter(([t]) => t === "e" || t === "p" || t === "a");
    let zap = {
      kind: 9735,
      created_at: Math.round(paidAt.getTime() / 1e3),
      content: "",
      tags: [...tagsFromZapRequest, ["P", zr.pubkey], ["bolt11", bolt11], ["description", zapRequest]]
    };
    if (preimage) {
      zap.tags.push(["preimage", preimage]);
    }
    return zap;
  }
  function getSatoshisAmountFromBolt11(bolt11) {
    if (bolt11.length < 50) {
      return 0;
    }
    bolt11 = bolt11.substring(0, 50);
    const idx = bolt11.lastIndexOf("1");
    if (idx === -1) {
      return 0;
    }
    const hrp = bolt11.substring(0, idx);
    if (!hrp.startsWith("lnbc")) {
      return 0;
    }
    const amount = hrp.substring(4);
    if (amount.length < 1) {
      return 0;
    }
    const char = amount[amount.length - 1];
    const digit = char.charCodeAt(0) - "0".charCodeAt(0);
    const isDigit = digit >= 0 && digit <= 9;
    let cutPoint = amount.length - 1;
    if (isDigit) {
      cutPoint++;
    }
    if (cutPoint < 1) {
      return 0;
    }
    const num = parseInt(amount.substring(0, cutPoint));
    switch (char) {
      case "m":
        return num * 1e5;
      case "u":
        return num * 100;
      case "n":
        return num / 10;
      case "p":
        return num / 1e4;
      default:
        return num * 1e8;
    }
  }

  // nip77.ts
  var nip77_exports = {};
  __export(nip77_exports, {
    Negentropy: () => Negentropy,
    NegentropyStorageVector: () => NegentropyStorageVector,
    NegentropySync: () => NegentropySync
  });
  var PROTOCOL_VERSION = 97;
  var ID_SIZE = 32;
  var FINGERPRINT_SIZE = 16;
  var Mode = {
    Skip: 0,
    Fingerprint: 1,
    IdList: 2
  };
  var WrappedBuffer = class {
    _raw;
    length;
    constructor(buffer) {
      if (typeof buffer === "number") {
        this._raw = new Uint8Array(buffer);
        this.length = 0;
      } else if (buffer instanceof Uint8Array) {
        this._raw = new Uint8Array(buffer);
        this.length = buffer.length;
      } else {
        this._raw = new Uint8Array(512);
        this.length = 0;
      }
    }
    unwrap() {
      return this._raw.subarray(0, this.length);
    }
    get capacity() {
      return this._raw.byteLength;
    }
    extend(buf) {
      if (buf instanceof WrappedBuffer)
        buf = buf.unwrap();
      if (typeof buf.length !== "number")
        throw Error("bad length");
      const targetSize = buf.length + this.length;
      if (this.capacity < targetSize) {
        const oldRaw = this._raw;
        const newCapacity = Math.max(this.capacity * 2, targetSize);
        this._raw = new Uint8Array(newCapacity);
        this._raw.set(oldRaw);
      }
      this._raw.set(buf, this.length);
      this.length += buf.length;
    }
    shift() {
      const first = this._raw[0];
      this._raw = this._raw.subarray(1);
      this.length--;
      return first;
    }
    shiftN(n = 1) {
      const firstSubarray = this._raw.subarray(0, n);
      this._raw = this._raw.subarray(n);
      this.length -= n;
      return firstSubarray;
    }
  };
  function decodeVarInt(buf) {
    let res = 0;
    while (1) {
      if (buf.length === 0)
        throw Error("parse ends prematurely");
      let byte = buf.shift();
      res = res << 7 | byte & 127;
      if ((byte & 128) === 0)
        break;
    }
    return res;
  }
  function encodeVarInt(n) {
    if (n === 0)
      return new WrappedBuffer(new Uint8Array([0]));
    let o = [];
    while (n !== 0) {
      o.push(n & 127);
      n >>>= 7;
    }
    o.reverse();
    for (let i2 = 0; i2 < o.length - 1; i2++)
      o[i2] |= 128;
    return new WrappedBuffer(new Uint8Array(o));
  }
  function getByte(buf) {
    return getBytes(buf, 1)[0];
  }
  function getBytes(buf, n) {
    if (buf.length < n)
      throw Error("parse ends prematurely");
    return buf.shiftN(n);
  }
  var Accumulator = class {
    buf;
    constructor() {
      this.setToZero();
    }
    setToZero() {
      this.buf = new Uint8Array(ID_SIZE);
    }
    add(otherBuf) {
      let currCarry = 0, nextCarry = 0;
      let p = new DataView(this.buf.buffer);
      let po = new DataView(otherBuf.buffer);
      for (let i2 = 0; i2 < 8; i2++) {
        let offset = i2 * 4;
        let orig = p.getUint32(offset, true);
        let otherV = po.getUint32(offset, true);
        let next = orig;
        next += currCarry;
        next += otherV;
        if (next > 4294967295)
          nextCarry = 1;
        p.setUint32(offset, next & 4294967295, true);
        currCarry = nextCarry;
        nextCarry = 0;
      }
    }
    negate() {
      let p = new DataView(this.buf.buffer);
      for (let i2 = 0; i2 < 8; i2++) {
        let offset = i2 * 4;
        p.setUint32(offset, ~p.getUint32(offset, true));
      }
      let one = new Uint8Array(ID_SIZE);
      one[0] = 1;
      this.add(one);
    }
    getFingerprint(n) {
      let input = new WrappedBuffer();
      input.extend(this.buf);
      input.extend(encodeVarInt(n));
      let hash = sha256$3(input.unwrap());
      return hash.subarray(0, FINGERPRINT_SIZE);
    }
  };
  var NegentropyStorageVector = class {
    items;
    sealed;
    constructor() {
      this.items = [];
      this.sealed = false;
    }
    insert(timestamp, id) {
      if (this.sealed)
        throw Error("already sealed");
      const idb = hexToBytes$3(id);
      if (idb.byteLength !== ID_SIZE)
        throw Error("bad id size for added item");
      this.items.push({ timestamp, id: idb });
    }
    seal() {
      if (this.sealed)
        throw Error("already sealed");
      this.sealed = true;
      this.items.sort(itemCompare);
      for (let i2 = 1; i2 < this.items.length; i2++) {
        if (itemCompare(this.items[i2 - 1], this.items[i2]) === 0)
          throw Error("duplicate item inserted");
      }
    }
    unseal() {
      this.sealed = false;
    }
    size() {
      this._checkSealed();
      return this.items.length;
    }
    getItem(i2) {
      this._checkSealed();
      if (i2 >= this.items.length)
        throw Error("out of range");
      return this.items[i2];
    }
    iterate(begin, end, cb) {
      this._checkSealed();
      this._checkBounds(begin, end);
      for (let i2 = begin; i2 < end; ++i2) {
        if (!cb(this.items[i2], i2))
          break;
      }
    }
    findLowerBound(begin, end, bound) {
      this._checkSealed();
      this._checkBounds(begin, end);
      return this._binarySearch(this.items, begin, end, (a) => itemCompare(a, bound) < 0);
    }
    fingerprint(begin, end) {
      let out = new Accumulator();
      out.setToZero();
      this.iterate(begin, end, (item) => {
        out.add(item.id);
        return true;
      });
      return out.getFingerprint(end - begin);
    }
    _checkSealed() {
      if (!this.sealed)
        throw Error("not sealed");
    }
    _checkBounds(begin, end) {
      if (begin > end || end > this.items.length)
        throw Error("bad range");
    }
    _binarySearch(arr, first, last, cmp) {
      let count = last - first;
      while (count > 0) {
        let it = first;
        let step = Math.floor(count / 2);
        it += step;
        if (cmp(arr[it])) {
          first = ++it;
          count -= step + 1;
        } else {
          count = step;
        }
      }
      return first;
    }
  };
  var Negentropy = class {
    storage;
    frameSizeLimit;
    lastTimestampIn;
    lastTimestampOut;
    constructor(storage, frameSizeLimit = 6e4) {
      if (frameSizeLimit < 4096)
        throw Error("frameSizeLimit too small");
      this.storage = storage;
      this.frameSizeLimit = frameSizeLimit;
      this.lastTimestampIn = 0;
      this.lastTimestampOut = 0;
    }
    _bound(timestamp, id) {
      return { timestamp, id: id || new Uint8Array(0) };
    }
    initiate() {
      let output = new WrappedBuffer();
      output.extend(new Uint8Array([PROTOCOL_VERSION]));
      this.splitRange(0, this.storage.size(), this._bound(Number.MAX_VALUE), output);
      return bytesToHex$5(output.unwrap());
    }
    reconcile(queryMsg, onhave, onneed) {
      const query = new WrappedBuffer(hexToBytes$3(queryMsg));
      this.lastTimestampIn = this.lastTimestampOut = 0;
      let fullOutput = new WrappedBuffer();
      fullOutput.extend(new Uint8Array([PROTOCOL_VERSION]));
      let protocolVersion = getByte(query);
      if (protocolVersion < 96 || protocolVersion > 111)
        throw Error("invalid negentropy protocol version byte");
      if (protocolVersion !== PROTOCOL_VERSION) {
        throw Error("unsupported negentropy protocol version requested: " + (protocolVersion - 96));
      }
      let storageSize = this.storage.size();
      let prevBound = this._bound(0);
      let prevIndex = 0;
      let skip = false;
      while (query.length !== 0) {
        let o = new WrappedBuffer();
        let doSkip = () => {
          if (skip) {
            skip = false;
            o.extend(this.encodeBound(prevBound));
            o.extend(encodeVarInt(Mode.Skip));
          }
        };
        let currBound = this.decodeBound(query);
        let mode = decodeVarInt(query);
        let lower = prevIndex;
        let upper = this.storage.findLowerBound(prevIndex, storageSize, currBound);
        if (mode === Mode.Skip) {
          skip = true;
        } else if (mode === Mode.Fingerprint) {
          let theirFingerprint = getBytes(query, FINGERPRINT_SIZE);
          let ourFingerprint = this.storage.fingerprint(lower, upper);
          if (compareUint8Array(theirFingerprint, ourFingerprint) !== 0) {
            doSkip();
            this.splitRange(lower, upper, currBound, o);
          } else {
            skip = true;
          }
        } else if (mode === Mode.IdList) {
          let numIds = decodeVarInt(query);
          let theirElems = {};
          for (let i2 = 0; i2 < numIds; i2++) {
            let e = getBytes(query, ID_SIZE);
            theirElems[bytesToHex$5(e)] = e;
          }
          skip = true;
          this.storage.iterate(lower, upper, (item) => {
            let k = item.id;
            const id = bytesToHex$5(k);
            if (!theirElems[id]) {
              onhave?.(id);
            } else {
              delete theirElems[bytesToHex$5(k)];
            }
            return true;
          });
          if (onneed) {
            for (let v of Object.values(theirElems)) {
              onneed(bytesToHex$5(v));
            }
          }
        } else {
          throw Error("unexpected mode");
        }
        if (this.exceededFrameSizeLimit(fullOutput.length + o.length)) {
          let remainingFingerprint = this.storage.fingerprint(upper, storageSize);
          fullOutput.extend(this.encodeBound(this._bound(Number.MAX_VALUE)));
          fullOutput.extend(encodeVarInt(Mode.Fingerprint));
          fullOutput.extend(remainingFingerprint);
          break;
        } else {
          fullOutput.extend(o);
        }
        prevIndex = upper;
        prevBound = currBound;
      }
      return fullOutput.length === 1 ? null : bytesToHex$5(fullOutput.unwrap());
    }
    splitRange(lower, upper, upperBound, o) {
      let numElems = upper - lower;
      let buckets = 16;
      if (numElems < buckets * 2) {
        o.extend(this.encodeBound(upperBound));
        o.extend(encodeVarInt(Mode.IdList));
        o.extend(encodeVarInt(numElems));
        this.storage.iterate(lower, upper, (item) => {
          o.extend(item.id);
          return true;
        });
      } else {
        let itemsPerBucket = Math.floor(numElems / buckets);
        let bucketsWithExtra = numElems % buckets;
        let curr = lower;
        for (let i2 = 0; i2 < buckets; i2++) {
          let bucketSize = itemsPerBucket + (i2 < bucketsWithExtra ? 1 : 0);
          let ourFingerprint = this.storage.fingerprint(curr, curr + bucketSize);
          curr += bucketSize;
          let nextBound;
          if (curr === upper) {
            nextBound = upperBound;
          } else {
            let prevItem;
            let currItem;
            this.storage.iterate(curr - 1, curr + 1, (item, index) => {
              if (index === curr - 1)
                prevItem = item;
              else
                currItem = item;
              return true;
            });
            nextBound = this.getMinimalBound(prevItem, currItem);
          }
          o.extend(this.encodeBound(nextBound));
          o.extend(encodeVarInt(Mode.Fingerprint));
          o.extend(ourFingerprint);
        }
      }
    }
    exceededFrameSizeLimit(n) {
      return n > this.frameSizeLimit - 200;
    }
    decodeTimestampIn(encoded) {
      let timestamp = decodeVarInt(encoded);
      timestamp = timestamp === 0 ? Number.MAX_VALUE : timestamp - 1;
      if (this.lastTimestampIn === Number.MAX_VALUE || timestamp === Number.MAX_VALUE) {
        this.lastTimestampIn = Number.MAX_VALUE;
        return Number.MAX_VALUE;
      }
      timestamp += this.lastTimestampIn;
      this.lastTimestampIn = timestamp;
      return timestamp;
    }
    decodeBound(encoded) {
      let timestamp = this.decodeTimestampIn(encoded);
      let len = decodeVarInt(encoded);
      if (len > ID_SIZE)
        throw Error("bound key too long");
      let id = getBytes(encoded, len);
      return { timestamp, id };
    }
    encodeTimestampOut(timestamp) {
      if (timestamp === Number.MAX_VALUE) {
        this.lastTimestampOut = Number.MAX_VALUE;
        return encodeVarInt(0);
      }
      let temp = timestamp;
      timestamp -= this.lastTimestampOut;
      this.lastTimestampOut = temp;
      return encodeVarInt(timestamp + 1);
    }
    encodeBound(key) {
      let output = new WrappedBuffer();
      output.extend(this.encodeTimestampOut(key.timestamp));
      output.extend(encodeVarInt(key.id.length));
      output.extend(key.id);
      return output;
    }
    getMinimalBound(prev, curr) {
      if (curr.timestamp !== prev.timestamp) {
        return this._bound(curr.timestamp);
      } else {
        let sharedPrefixBytes = 0;
        let currKey = curr.id;
        let prevKey = prev.id;
        for (let i2 = 0; i2 < ID_SIZE; i2++) {
          if (currKey[i2] !== prevKey[i2])
            break;
          sharedPrefixBytes++;
        }
        return this._bound(curr.timestamp, curr.id.subarray(0, sharedPrefixBytes + 1));
      }
    }
  };
  function compareUint8Array(a, b) {
    for (let i2 = 0; i2 < a.byteLength; i2++) {
      if (a[i2] < b[i2])
        return -1;
      if (a[i2] > b[i2])
        return 1;
    }
    if (a.byteLength > b.byteLength)
      return 1;
    if (a.byteLength < b.byteLength)
      return -1;
    return 0;
  }
  function itemCompare(a, b) {
    if (a.timestamp === b.timestamp) {
      return compareUint8Array(a.id, b.id);
    }
    return a.timestamp - b.timestamp;
  }
  var NegentropySync = class {
    relay;
    storage;
    neg;
    filter;
    subscription;
    onhave;
    onneed;
    constructor(relay, storage, filter, params = {}) {
      this.relay = relay;
      this.storage = storage;
      this.neg = new Negentropy(storage);
      this.onhave = params.onhave;
      this.onneed = params.onneed;
      this.filter = filter;
      this.subscription = this.relay.prepareSubscription([{}], { label: params.label || "negentropy" });
      this.subscription.oncustom = (data) => {
        switch (data[0]) {
          case "NEG-MSG": {
            if (data.length < 3) {
              console.warn(`got invalid NEG-MSG from ${this.relay.url}: ${data}`);
            }
            try {
              const response = this.neg.reconcile(data[2], this.onhave, this.onneed);
              if (response) {
                this.relay.send(`["NEG-MSG", "${this.subscription.id}", "${response}"]`);
              } else {
                this.close();
                params.onclose?.();
              }
            } catch (error) {
              console.error("negentropy reconcile error:", error);
              params?.onclose?.(`reconcile error: ${error}`);
            }
            break;
          }
          case "NEG-CLOSE": {
            const reason = data[2];
            console.warn("negentropy error:", reason);
            params.onclose?.(reason);
            break;
          }
          case "NEG-ERR": {
            params.onclose?.();
          }
        }
      };
    }
    async start() {
      const initMsg = this.neg.initiate();
      this.relay.send(`["NEG-OPEN","${this.subscription.id}",${JSON.stringify(this.filter)},"${initMsg}"]`);
    }
    close() {
      this.relay.send(`["NEG-CLOSE","${this.subscription.id}"]`);
      this.subscription.close();
    }
  };

  // nip98.ts
  var nip98_exports = {};
  __export(nip98_exports, {
    getToken: () => getToken,
    hashPayload: () => hashPayload,
    unpackEventFromToken: () => unpackEventFromToken,
    validateEvent: () => validateEvent2,
    validateEventKind: () => validateEventKind,
    validateEventMethodTag: () => validateEventMethodTag,
    validateEventPayloadTag: () => validateEventPayloadTag,
    validateEventTimestamp: () => validateEventTimestamp,
    validateEventUrlTag: () => validateEventUrlTag,
    validateToken: () => validateToken
  });
  var _authorizationScheme = "Nostr ";
  async function getToken(loginUrl, httpMethod, sign, includeAuthorizationScheme = false, payload) {
    const event = {
      kind: HTTPAuth,
      tags: [
        ["u", loginUrl],
        ["method", httpMethod]
      ],
      created_at: Math.round(new Date().getTime() / 1e3),
      content: ""
    };
    if (payload) {
      event.tags.push(["payload", hashPayload(payload)]);
    }
    const signedEvent = await sign(event);
    const authorizationScheme = includeAuthorizationScheme ? _authorizationScheme : "";
    return authorizationScheme + base64$1.encode(utf8Encoder$2.encode(JSON.stringify(signedEvent)));
  }
  async function validateToken(token, url, method) {
    const event = await unpackEventFromToken(token).catch((error) => {
      throw error;
    });
    const valid = await validateEvent2(event, url, method).catch((error) => {
      throw error;
    });
    return valid;
  }
  async function unpackEventFromToken(token) {
    if (!token) {
      throw new Error("Missing token");
    }
    token = token.replace(_authorizationScheme, "");
    const eventB64 = utf8Decoder.decode(base64$1.decode(token));
    if (!eventB64 || eventB64.length === 0 || !eventB64.startsWith("{")) {
      throw new Error("Invalid token");
    }
    const event = JSON.parse(eventB64);
    return event;
  }
  function validateEventTimestamp(event) {
    if (!event.created_at) {
      return false;
    }
    return Math.round(new Date().getTime() / 1e3) - event.created_at < 60;
  }
  function validateEventKind(event) {
    return event.kind === HTTPAuth;
  }
  function validateEventUrlTag(event, url) {
    const urlTag = event.tags.find((t) => t[0] === "u");
    if (!urlTag) {
      return false;
    }
    return urlTag.length > 0 && urlTag[1] === url;
  }
  function validateEventMethodTag(event, method) {
    const methodTag = event.tags.find((t) => t[0] === "method");
    if (!methodTag) {
      return false;
    }
    return methodTag.length > 0 && methodTag[1].toLowerCase() === method.toLowerCase();
  }
  function hashPayload(payload) {
    const hash = sha256$3(utf8Encoder$2.encode(JSON.stringify(payload)));
    return bytesToHex$5(hash);
  }
  function validateEventPayloadTag(event, payload) {
    const payloadTag = event.tags.find((t) => t[0] === "payload");
    if (!payloadTag) {
      return false;
    }
    const payloadHash = hashPayload(payload);
    return payloadTag.length > 0 && payloadTag[1] === payloadHash;
  }
  async function validateEvent2(event, url, method, body) {
    if (!verifyEvent$1(event)) {
      throw new Error("Invalid nostr event, signature invalid");
    }
    if (!validateEventKind(event)) {
      throw new Error("Invalid nostr event, kind invalid");
    }
    if (!validateEventTimestamp(event)) {
      throw new Error("Invalid nostr event, created_at timestamp invalid");
    }
    if (!validateEventUrlTag(event, url)) {
      throw new Error("Invalid nostr event, url tag invalid");
    }
    if (!validateEventMethodTag(event, method)) {
      throw new Error("Invalid nostr event, method tag invalid");
    }
    if (Boolean(body) && typeof body === "object" && Object.keys(body).length > 0) {
      if (!validateEventPayloadTag(event, body)) {
        throw new Error("Invalid nostr event, payload tag does not match request body hash");
      }
    }
    return true;
  }

  // pure.ts

  // core.ts
  var verifiedSymbol$1 = Symbol("verified");
  var isRecord$1 = (obj) => obj instanceof Object;
  function validateEvent$1(event) {
    if (!isRecord$1(event))
      return false;
    if (typeof event.kind !== "number")
      return false;
    if (typeof event.content !== "string")
      return false;
    if (typeof event.created_at !== "number")
      return false;
    if (typeof event.pubkey !== "string")
      return false;
    if (!event.pubkey.match(/^[a-f0-9]{64}$/))
      return false;
    if (!Array.isArray(event.tags))
      return false;
    for (let i2 = 0; i2 < event.tags.length; i2++) {
      let tag = event.tags[i2];
      if (!Array.isArray(tag))
        return false;
      for (let j = 0; j < tag.length; j++) {
        if (typeof tag[j] !== "string")
          return false;
      }
    }
    return true;
  }
  new TextDecoder("utf-8");
  var utf8Encoder$1 = new TextEncoder();
  function normalizeURL(url) {
    try {
      if (url.indexOf("://") === -1)
        url = "wss://" + url;
      let p = new URL(url);
      if (p.protocol === "http:")
        p.protocol = "ws:";
      else if (p.protocol === "https:")
        p.protocol = "wss:";
      p.pathname = p.pathname.replace(/\/+/g, "/");
      if (p.pathname.endsWith("/"))
        p.pathname = p.pathname.slice(0, -1);
      if (p.port === "80" && p.protocol === "ws:" || p.port === "443" && p.protocol === "wss:")
        p.port = "";
      p.searchParams.sort();
      p.hash = "";
      return p.toString();
    } catch (e) {
      throw new Error(`Invalid URL: ${url}`);
    }
  }

  // pure.ts
  var JS$1 = class JS {
    generateSecretKey() {
      return schnorr$1.utils.randomSecretKey();
    }
    getPublicKey(secretKey) {
      return bytesToHex$5(schnorr$1.getPublicKey(secretKey));
    }
    finalizeEvent(t, secretKey) {
      const event = t;
      event.pubkey = bytesToHex$5(schnorr$1.getPublicKey(secretKey));
      event.id = getEventHash$1(event);
      event.sig = bytesToHex$5(schnorr$1.sign(hexToBytes$3(getEventHash$1(event)), secretKey));
      event[verifiedSymbol$1] = true;
      return event;
    }
    verifyEvent(event) {
      if (typeof event[verifiedSymbol$1] === "boolean")
        return event[verifiedSymbol$1];
      try {
        const hash = getEventHash$1(event);
        if (hash !== event.id) {
          event[verifiedSymbol$1] = false;
          return false;
        }
        const valid = schnorr$1.verify(hexToBytes$3(event.sig), hexToBytes$3(hash), hexToBytes$3(event.pubkey));
        event[verifiedSymbol$1] = valid;
        return valid;
      } catch (err) {
        event[verifiedSymbol$1] = false;
        return false;
      }
    }
  };
  function serializeEvent$1(evt) {
    if (!validateEvent$1(evt))
      throw new Error("can't serialize event with wrong or missing properties");
    return JSON.stringify([0, evt.pubkey, evt.created_at, evt.kind, evt.tags, evt.content]);
  }
  function getEventHash$1(event) {
    let eventHash = sha256$3(utf8Encoder$1.encode(serializeEvent$1(event)));
    return bytesToHex$5(eventHash);
  }
  var i$1 = new JS$1();
  i$1.generateSecretKey;
  i$1.getPublicKey;
  i$1.finalizeEvent;
  var verifyEvent = i$1.verifyEvent;

  // kinds.ts
  var ClientAuth = 22242;

  // filter.ts
  function matchFilter(filter, event) {
    if (filter.ids && filter.ids.indexOf(event.id) === -1) {
      return false;
    }
    if (filter.kinds && filter.kinds.indexOf(event.kind) === -1) {
      return false;
    }
    if (filter.authors && filter.authors.indexOf(event.pubkey) === -1) {
      return false;
    }
    for (let f in filter) {
      if (f[0] === "#") {
        let tagName = f.slice(1);
        let values = filter[`#${tagName}`];
        if (values && !event.tags.find(([t, v]) => t === f.slice(1) && values.indexOf(v) !== -1))
          return false;
      }
    }
    if (filter.since && event.created_at < filter.since)
      return false;
    if (filter.until && event.created_at > filter.until)
      return false;
    return true;
  }
  function matchFilters(filters, event) {
    for (let i2 = 0; i2 < filters.length; i2++) {
      if (matchFilter(filters[i2], event)) {
        return true;
      }
    }
    return false;
  }

  // fakejson.ts
  function getHex64(json, field) {
    let len = field.length + 3;
    let idx = json.indexOf(`"${field}":`) + len;
    let s = json.slice(idx).indexOf(`"`) + idx + 1;
    return json.slice(s, s + 64);
  }
  function getSubscriptionId(json) {
    let idx = json.slice(0, 22).indexOf(`"EVENT"`);
    if (idx === -1)
      return null;
    let pstart = json.slice(idx + 7 + 1).indexOf(`"`);
    if (pstart === -1)
      return null;
    let start = idx + 7 + 1 + pstart;
    let pend = json.slice(start + 1, 80).indexOf(`"`);
    if (pend === -1)
      return null;
    let end = start + 1 + pend;
    return json.slice(start + 1, end);
  }

  // nip42.ts
  function makeAuthEvent(relayURL, challenge) {
    return {
      kind: ClientAuth,
      created_at: Math.floor(Date.now() / 1e3),
      tags: [
        ["relay", relayURL],
        ["challenge", challenge]
      ],
      content: ""
    };
  }

  // abstract-relay.ts
  var SendingOnClosedConnection = class extends Error {
    constructor(message, relay) {
      super(`Tried to send message '${message} on a closed connection to ${relay}.`);
      this.name = "SendingOnClosedConnection";
    }
  };
  var AbstractRelay = class {
    url;
    _connected = false;
    onclose = null;
    onnotice = (msg) => console.debug(`NOTICE from ${this.url}: ${msg}`);
    onauth;
    baseEoseTimeout = 4400;
    publishTimeout = 4400;
    pingFrequency = 29e3;
    pingTimeout = 2e4;
    resubscribeBackoff = [1e4, 1e4, 1e4, 2e4, 2e4, 3e4, 6e4];
    openSubs = /* @__PURE__ */ new Map();
    enablePing;
    enableReconnect;
    idleSince = Date.now();
    ongoingOperations = 0;
    reconnectTimeoutHandle;
    pingIntervalHandle;
    reconnectAttempts = 0;
    skipReconnection = false;
    connectionPromise;
    openCountRequests = /* @__PURE__ */ new Map();
    openEventPublishes = /* @__PURE__ */ new Map();
    ws;
    challenge;
    authPromise;
    serial = 0;
    verifyEvent;
    _WebSocket;
    constructor(url, opts) {
      this.url = normalizeURL(url);
      this.verifyEvent = opts.verifyEvent;
      this._WebSocket = opts.websocketImplementation || WebSocket;
      this.enablePing = opts.enablePing;
      this.enableReconnect = opts.enableReconnect || false;
    }
    static async connect(url, opts) {
      const relay = new AbstractRelay(url, opts);
      await relay.connect(opts);
      return relay;
    }
    closeAllSubscriptions(reason) {
      for (let [_, sub] of this.openSubs) {
        sub.close(reason);
      }
      this.openSubs.clear();
      for (let [_, ep] of this.openEventPublishes) {
        ep.reject(new Error(reason));
      }
      this.openEventPublishes.clear();
      for (let [_, cr] of this.openCountRequests) {
        cr.reject(new Error(reason));
      }
      this.openCountRequests.clear();
    }
    get connected() {
      return this._connected;
    }
    async reconnect() {
      const backoff = this.resubscribeBackoff[Math.min(this.reconnectAttempts, this.resubscribeBackoff.length - 1)];
      this.reconnectAttempts++;
      this.reconnectTimeoutHandle = setTimeout(async () => {
        try {
          await this.connect();
        } catch (err) {
        }
      }, backoff);
    }
    handleHardClose(reason) {
      if (this.pingIntervalHandle) {
        clearInterval(this.pingIntervalHandle);
        this.pingIntervalHandle = void 0;
      }
      this._connected = false;
      this.connectionPromise = void 0;
      this.idleSince = void 0;
      if (this.enableReconnect && !this.skipReconnection) {
        this.reconnect();
      } else {
        this.onclose?.();
        this.closeAllSubscriptions(reason);
      }
    }
    async connect(opts) {
      let connectionTimeoutHandle;
      if (this.connectionPromise)
        return this.connectionPromise;
      this.challenge = void 0;
      this.authPromise = void 0;
      this.skipReconnection = false;
      this.connectionPromise = new Promise((resolve, reject) => {
        if (opts?.timeout) {
          connectionTimeoutHandle = setTimeout(() => {
            reject("connection timed out");
            this.connectionPromise = void 0;
            this.skipReconnection = true;
            this.onclose?.();
            this.handleHardClose("relay connection timed out");
          }, opts.timeout);
        }
        if (opts?.abort) {
          opts.abort.onabort = reject;
        }
        try {
          this.ws = new this._WebSocket(this.url);
        } catch (err) {
          clearTimeout(connectionTimeoutHandle);
          reject(err);
          return;
        }
        this.ws.onopen = () => {
          if (this.reconnectTimeoutHandle) {
            clearTimeout(this.reconnectTimeoutHandle);
            this.reconnectTimeoutHandle = void 0;
          }
          clearTimeout(connectionTimeoutHandle);
          this._connected = true;
          const isReconnection = this.reconnectAttempts > 0;
          this.reconnectAttempts = 0;
          for (const sub of this.openSubs.values()) {
            sub.eosed = false;
            if (isReconnection) {
              for (let f = 0; f < sub.filters.length; f++) {
                if (sub.lastEmitted) {
                  sub.filters[f].since = sub.lastEmitted + 1;
                }
              }
            }
            sub.fire();
          }
          if (this.enablePing) {
            this.pingIntervalHandle = setInterval(() => this.pingpong(), this.pingFrequency);
          }
          resolve();
        };
        this.ws.onerror = () => {
          clearTimeout(connectionTimeoutHandle);
          reject("connection failed");
          this.connectionPromise = void 0;
          this.skipReconnection = true;
          this.onclose?.();
          this.handleHardClose("relay connection failed");
        };
        this.ws.onclose = (ev) => {
          clearTimeout(connectionTimeoutHandle);
          reject(ev.message || "websocket closed");
          this.handleHardClose("relay connection closed");
        };
        this.ws.onmessage = this._onmessage.bind(this);
      });
      return this.connectionPromise;
    }
    waitForPingPong() {
      return new Promise((resolve) => {
        this.ws.once("pong", () => resolve(true));
        this.ws.ping();
      });
    }
    waitForDummyReq() {
      return new Promise((resolve, reject) => {
        if (!this.connectionPromise)
          return reject(new Error(`no connection to ${this.url}, can't ping`));
        try {
          const sub = this.subscribe(
            [{ ids: ["aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"], limit: 0 }],
            {
              label: "<forced-ping>",
              oneose: () => {
                resolve(true);
                sub.close();
              },
              onclose() {
                resolve(true);
              },
              eoseTimeout: this.pingTimeout + 1e3
            }
          );
        } catch (err) {
          reject(err);
        }
      });
    }
    async pingpong() {
      if (this.ws?.readyState === 1) {
        const result = await Promise.any([
          this.ws && this.ws.ping && this.ws.once ? this.waitForPingPong() : this.waitForDummyReq(),
          new Promise((res) => setTimeout(() => res(false), this.pingTimeout))
        ]);
        if (!result) {
          if (this.ws?.readyState === this._WebSocket.OPEN) {
            this.ws?.close();
          }
        }
      }
    }
    async send(message) {
      if (!this.connectionPromise)
        throw new SendingOnClosedConnection(message, this.url);
      this.connectionPromise.then(() => {
        this.ws?.send(message);
      });
    }
    async auth(signAuthEvent) {
      const challenge = this.challenge;
      if (!challenge)
        throw new Error("can't perform auth, no challenge was received");
      if (this.authPromise)
        return this.authPromise;
      this.authPromise = new Promise(async (resolve, reject) => {
        try {
          let evt = await signAuthEvent(makeAuthEvent(this.url, challenge));
          let timeout = setTimeout(() => {
            let ep = this.openEventPublishes.get(evt.id);
            if (ep) {
              ep.reject(new Error("auth timed out"));
              this.openEventPublishes.delete(evt.id);
            }
          }, this.publishTimeout);
          this.openEventPublishes.set(evt.id, { resolve, reject, timeout });
          this.send('["AUTH",' + JSON.stringify(evt) + "]");
        } catch (err) {
          console.warn("subscribe auth function failed:", err);
        }
      });
      return this.authPromise;
    }
    async publish(event) {
      this.idleSince = void 0;
      this.ongoingOperations++;
      const ret = new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
          const ep = this.openEventPublishes.get(event.id);
          if (ep) {
            ep.reject(new Error("publish timed out"));
            this.openEventPublishes.delete(event.id);
          }
        }, this.publishTimeout);
        this.openEventPublishes.set(event.id, { resolve, reject, timeout });
      });
      this.send('["EVENT",' + JSON.stringify(event) + "]");
      this.ongoingOperations--;
      if (this.ongoingOperations === 0)
        this.idleSince = Date.now();
      return ret;
    }
    async count(filters, params) {
      this.serial++;
      const id = params?.id || "count:" + this.serial;
      const ret = new Promise((resolve, reject) => {
        this.openCountRequests.set(id, { resolve, reject });
      });
      this.send('["COUNT","' + id + '",' + JSON.stringify(filters).substring(1));
      return ret;
    }
    subscribe(filters, params) {
      if (params.label !== "<forced-ping>") {
        this.idleSince = void 0;
        this.ongoingOperations++;
      }
      const sub = this.prepareSubscription(filters, params);
      sub.fire();
      if (params.abort) {
        params.abort.onabort = () => sub.close(String(params.abort.reason || "<aborted>"));
      }
      return sub;
    }
    prepareSubscription(filters, params) {
      this.serial++;
      const id = params.id || (params.label ? params.label + ":" : "sub:") + this.serial;
      const sub = new Subscription(this, id, filters, params);
      this.openSubs.set(id, sub);
      return sub;
    }
    close() {
      this.skipReconnection = true;
      if (this.reconnectTimeoutHandle) {
        clearTimeout(this.reconnectTimeoutHandle);
        this.reconnectTimeoutHandle = void 0;
      }
      if (this.pingIntervalHandle) {
        clearInterval(this.pingIntervalHandle);
        this.pingIntervalHandle = void 0;
      }
      this.closeAllSubscriptions("relay connection closed by us");
      this._connected = false;
      this.idleSince = void 0;
      this.onclose?.();
      if (this.ws?.readyState === this._WebSocket.OPEN) {
        this.ws?.close();
      }
    }
    _onmessage(ev) {
      const json = ev.data;
      if (!json) {
        return;
      }
      const subid = getSubscriptionId(json);
      if (subid) {
        const so = this.openSubs.get(subid);
        if (!so) {
          return;
        }
        const id = getHex64(json, "id");
        const alreadyHave = so.alreadyHaveEvent?.(id);
        so.receivedEvent?.(this, id);
        if (alreadyHave) {
          return;
        }
      }
      try {
        let data = JSON.parse(json);
        switch (data[0]) {
          case "EVENT": {
            const so = this.openSubs.get(data[1]);
            const event = data[2];
            if (this.verifyEvent(event) && matchFilters(so.filters, event)) {
              so.onevent(event);
            } else {
              so.oninvalidevent?.(event);
            }
            if (!so.lastEmitted || so.lastEmitted < event.created_at)
              so.lastEmitted = event.created_at;
            return;
          }
          case "COUNT": {
            const id = data[1];
            const payload = data[2];
            const cr = this.openCountRequests.get(id);
            if (cr) {
              cr.resolve(payload.count);
              this.openCountRequests.delete(id);
            }
            return;
          }
          case "EOSE": {
            const so = this.openSubs.get(data[1]);
            if (!so)
              return;
            so.receivedEose();
            return;
          }
          case "OK": {
            const id = data[1];
            const ok = data[2];
            const reason = data[3];
            const ep = this.openEventPublishes.get(id);
            if (ep) {
              clearTimeout(ep.timeout);
              if (ok)
                ep.resolve(reason);
              else
                ep.reject(new Error(reason));
              this.openEventPublishes.delete(id);
            }
            return;
          }
          case "CLOSED": {
            const id = data[1];
            const so = this.openSubs.get(id);
            if (!so)
              return;
            so.closed = true;
            so.close(data[2]);
            return;
          }
          case "NOTICE": {
            this.onnotice(data[1]);
            return;
          }
          case "AUTH": {
            this.challenge = data[1];
            if (this.onauth) {
              this.auth(this.onauth);
            }
            return;
          }
          default: {
            const so = this.openSubs.get(data[1]);
            so?.oncustom?.(data);
            return;
          }
        }
      } catch (err) {
        try {
          const [_, __, event] = JSON.parse(json);
          console.warn(`[nostr] relay ${this.url} error processing message:`, err, event);
        } catch (_) {
          console.warn(`[nostr] relay ${this.url} error processing message:`, err);
        }
        return;
      }
    }
  };
  var Subscription = class {
    relay;
    id;
    lastEmitted;
    closed = false;
    eosed = false;
    filters;
    alreadyHaveEvent;
    receivedEvent;
    onevent;
    oninvalidevent;
    oneose;
    onclose;
    oncustom;
    eoseTimeout;
    eoseTimeoutHandle;
    constructor(relay, id, filters, params) {
      if (filters.length === 0)
        throw new Error("subscription can't be created with zero filters");
      this.relay = relay;
      this.filters = filters;
      this.id = id;
      this.alreadyHaveEvent = params.alreadyHaveEvent;
      this.receivedEvent = params.receivedEvent;
      this.eoseTimeout = params.eoseTimeout || relay.baseEoseTimeout;
      this.oneose = params.oneose;
      this.onclose = params.onclose;
      this.oninvalidevent = params.oninvalidevent;
      this.onevent = params.onevent || ((event) => {
        console.warn(
          `onevent() callback not defined for subscription '${this.id}' in relay ${this.relay.url}. event received:`,
          event
        );
      });
    }
    fire() {
      this.relay.send('["REQ","' + this.id + '",' + JSON.stringify(this.filters).substring(1));
      this.eoseTimeoutHandle = setTimeout(this.receivedEose.bind(this), this.eoseTimeout);
    }
    receivedEose() {
      if (this.eosed)
        return;
      clearTimeout(this.eoseTimeoutHandle);
      this.eosed = true;
      this.oneose?.();
    }
    close(reason = "closed by caller") {
      if (!this.closed && this.relay.connected) {
        try {
          this.relay.send('["CLOSE",' + JSON.stringify(this.id) + "]");
        } catch (err) {
          if (err instanceof SendingOnClosedConnection) ; else {
            throw err;
          }
        }
        this.closed = true;
      }
      this.relay.openSubs.delete(this.id);
      this.relay.ongoingOperations--;
      if (this.relay.ongoingOperations === 0)
        this.relay.idleSince = Date.now();
      this.onclose?.(reason);
    }
  };

  // helpers.ts
  var alwaysTrue = (t) => {
    t[verifiedSymbol$1] = true;
    return true;
  };

  // abstract-pool.ts
  var AbstractSimplePool = class {
    relays = /* @__PURE__ */ new Map();
    seenOn = /* @__PURE__ */ new Map();
    trackRelays = false;
    verifyEvent;
    enablePing;
    enableReconnect;
    automaticallyAuth;
    trustedRelayURLs = /* @__PURE__ */ new Set();
    onRelayConnectionFailure;
    onRelayConnectionSuccess;
    allowConnectingToRelay;
    maxWaitForConnection;
    _WebSocket;
    constructor(opts) {
      this.verifyEvent = opts.verifyEvent;
      this._WebSocket = opts.websocketImplementation;
      this.enablePing = opts.enablePing;
      this.enableReconnect = opts.enableReconnect || false;
      this.automaticallyAuth = opts.automaticallyAuth;
      this.onRelayConnectionFailure = opts.onRelayConnectionFailure;
      this.onRelayConnectionSuccess = opts.onRelayConnectionSuccess;
      this.allowConnectingToRelay = opts.allowConnectingToRelay;
      this.maxWaitForConnection = opts.maxWaitForConnection || 3e3;
    }
    async ensureRelay(url, params) {
      url = normalizeURL(url);
      let relay = this.relays.get(url);
      if (!relay) {
        relay = new AbstractRelay(url, {
          verifyEvent: this.trustedRelayURLs.has(url) ? alwaysTrue : this.verifyEvent,
          websocketImplementation: this._WebSocket,
          enablePing: this.enablePing,
          enableReconnect: this.enableReconnect
        });
        relay.onclose = () => {
          this.relays.delete(url);
        };
        this.relays.set(url, relay);
      }
      if (this.automaticallyAuth) {
        const authSignerFn = this.automaticallyAuth(url);
        if (authSignerFn) {
          relay.onauth = authSignerFn;
        }
      }
      try {
        await relay.connect({
          timeout: params?.connectionTimeout,
          abort: params?.abort
        });
      } catch (err) {
        this.relays.delete(url);
        throw err;
      }
      return relay;
    }
    close(relays) {
      relays.map(normalizeURL).forEach((url) => {
        this.relays.get(url)?.close();
        this.relays.delete(url);
      });
    }
    subscribe(relays, filter, params) {
      const request = [];
      const uniqUrls = [];
      for (let i2 = 0; i2 < relays.length; i2++) {
        const url = normalizeURL(relays[i2]);
        if (!request.find((r) => r.url === url)) {
          if (uniqUrls.indexOf(url) === -1) {
            uniqUrls.push(url);
            request.push({ url, filter });
          }
        }
      }
      return this.subscribeMap(request, params);
    }
    subscribeMany(relays, filter, params) {
      return this.subscribe(relays, filter, params);
    }
    subscribeMap(requests, params) {
      const grouped = /* @__PURE__ */ new Map();
      for (const req of requests) {
        const { url, filter } = req;
        if (!grouped.has(url))
          grouped.set(url, []);
        grouped.get(url).push(filter);
      }
      const groupedRequests = Array.from(grouped.entries()).map(([url, filters]) => ({ url, filters }));
      if (this.trackRelays) {
        params.receivedEvent = (relay, id) => {
          let set = this.seenOn.get(id);
          if (!set) {
            set = /* @__PURE__ */ new Set();
            this.seenOn.set(id, set);
          }
          set.add(relay);
        };
      }
      const _knownIds = /* @__PURE__ */ new Set();
      const subs = [];
      const eosesReceived = [];
      let handleEose = (i2) => {
        if (eosesReceived[i2])
          return;
        eosesReceived[i2] = true;
        if (eosesReceived.filter((a) => a).length === groupedRequests.length) {
          params.oneose?.();
          handleEose = () => {
          };
        }
      };
      const closesReceived = [];
      let handleClose = (i2, reason) => {
        if (closesReceived[i2])
          return;
        handleEose(i2);
        closesReceived[i2] = reason;
        if (closesReceived.filter((a) => a).length === groupedRequests.length) {
          params.onclose?.(closesReceived);
          handleClose = () => {
          };
        }
      };
      const localAlreadyHaveEventHandler = (id) => {
        if (params.alreadyHaveEvent?.(id)) {
          return true;
        }
        const have = _knownIds.has(id);
        _knownIds.add(id);
        return have;
      };
      const allOpened = Promise.all(
        groupedRequests.map(async ({ url, filters }, i2) => {
          if (this.allowConnectingToRelay?.(url, ["read", filters]) === false) {
            handleClose(i2, "connection skipped by allowConnectingToRelay");
            return;
          }
          let relay;
          try {
            relay = await this.ensureRelay(url, {
              connectionTimeout: this.maxWaitForConnection < (params.maxWait || 0) ? Math.max(params.maxWait * 0.8, params.maxWait - 1e3) : this.maxWaitForConnection,
              abort: params.abort
            });
          } catch (err) {
            this.onRelayConnectionFailure?.(url);
            handleClose(i2, err?.message || String(err));
            return;
          }
          this.onRelayConnectionSuccess?.(url);
          let subscription = relay.subscribe(filters, {
            ...params,
            oneose: () => handleEose(i2),
            onclose: (reason) => {
              if (reason.startsWith("auth-required: ") && params.onauth) {
                relay.auth(params.onauth).then(() => {
                  relay.subscribe(filters, {
                    ...params,
                    oneose: () => handleEose(i2),
                    onclose: (reason2) => {
                      handleClose(i2, reason2);
                    },
                    alreadyHaveEvent: localAlreadyHaveEventHandler,
                    eoseTimeout: params.maxWait,
                    abort: params.abort
                  });
                }).catch((err) => {
                  handleClose(i2, `auth was required and attempted, but failed with: ${err}`);
                });
              } else {
                handleClose(i2, reason);
              }
            },
            alreadyHaveEvent: localAlreadyHaveEventHandler,
            eoseTimeout: params.maxWait,
            abort: params.abort
          });
          subs.push(subscription);
        })
      );
      return {
        async close(reason) {
          await allOpened;
          subs.forEach((sub) => {
            sub.close(reason);
          });
        }
      };
    }
    subscribeEose(relays, filter, params) {
      let subcloser;
      subcloser = this.subscribe(relays, filter, {
        ...params,
        oneose() {
          const reason = "closed automatically on eose";
          if (subcloser)
            subcloser.close(reason);
          else
            params.onclose?.(relays.map((_) => reason));
        }
      });
      return subcloser;
    }
    subscribeManyEose(relays, filter, params) {
      return this.subscribeEose(relays, filter, params);
    }
    async querySync(relays, filter, params) {
      return new Promise(async (resolve) => {
        const events = [];
        this.subscribeEose(relays, filter, {
          ...params,
          onevent(event) {
            events.push(event);
          },
          onclose(_) {
            resolve(events);
          }
        });
      });
    }
    async get(relays, filter, params) {
      filter.limit = 1;
      const events = await this.querySync(relays, filter, params);
      events.sort((a, b) => b.created_at - a.created_at);
      return events[0] || null;
    }
    publish(relays, event, params) {
      return relays.map(normalizeURL).map(async (url, i2, arr) => {
        if (arr.indexOf(url) !== i2) {
          return Promise.reject("duplicate url");
        }
        if (this.allowConnectingToRelay?.(url, ["write", event]) === false) {
          return Promise.reject("connection skipped by allowConnectingToRelay");
        }
        let r;
        try {
          r = await this.ensureRelay(url, {
            connectionTimeout: this.maxWaitForConnection < (params?.maxWait || 0) ? Math.max(params.maxWait * 0.8, params.maxWait - 1e3) : this.maxWaitForConnection,
            abort: params?.abort
          });
        } catch (err) {
          this.onRelayConnectionFailure?.(url);
          return String("connection failure: " + String(err));
        }
        return r.publish(event).catch(async (err) => {
          if (err instanceof Error && err.message.startsWith("auth-required: ") && params?.onauth) {
            await r.auth(params.onauth);
            return r.publish(event);
          }
          throw err;
        }).then((reason) => {
          if (this.trackRelays) {
            let set = this.seenOn.get(event.id);
            if (!set) {
              set = /* @__PURE__ */ new Set();
              this.seenOn.set(event.id, set);
            }
            set.add(r);
          }
          return reason;
        });
      });
    }
    listConnectionStatus() {
      const map = /* @__PURE__ */ new Map();
      this.relays.forEach((relay, url) => map.set(url, relay.connected));
      return map;
    }
    destroy() {
      this.relays.forEach((conn) => conn.close());
      this.relays = /* @__PURE__ */ new Map();
    }
    pruneIdleRelays(idleThresholdMs = 1e4) {
      const prunedUrls = [];
      for (const [url, relay] of this.relays) {
        if (relay.idleSince && Date.now() - relay.idleSince >= idleThresholdMs) {
          this.relays.delete(url);
          prunedUrls.push(url);
          relay.close();
        }
      }
      return prunedUrls;
    }
  };

  // pool.ts
  var _WebSocket;
  try {
    _WebSocket = WebSocket;
  } catch {
  }
  var SimplePool = class extends AbstractSimplePool {
    constructor(options) {
      super({ verifyEvent, websocketImplementation: _WebSocket, maxWaitForConnection: 3e3, ...options });
    }
  };

  /**
   * MILL — nip46.js
   * NIP-46 Nostr Connect client. Framework-agnostic: no DOM, no globals.
   *
   * Supports both:
   *   - bunker://<remote-pk>?relay=…&secret=…  (user pastes their bunker URI)
   *   - nostrconnect://<client-pk>?relay=…&secret=…  (we generate, user scans/pastes into bunker)
   *
   * Exposes: NIP46Client class with connect(), getPublicKey(), signEvent(),
   * nip04 / nip44 encrypt/decrypt, disconnect().
   */


  // Default relays for NIP-46. Picked for: ephemeral-event support, broad reach,
  // and uptime. relay.nostr.band was removed because it's primarily a search
  // index and frequently rejects/drops kind 24133 messages.
  const DEFAULT_RELAYS = [
    'wss://relay.nsec.app',          // purpose-built for NIP-46 traffic
    'wss://relay.damus.io',
    'wss://nos.lol',
    'wss://relay.primal.net',
  ];

  // Curated list users can pick from in the UI. The first entry is recommended
  // as the most reliable for NIP-46 ephemeral events.
  const SUGGESTED_RELAYS = [
    'wss://relay.nsec.app',
    'wss://wheat.happytavern.co',
    'wss://relay.damus.io',
    'wss://nos.lol',
    'wss://relay.primal.net',
    'wss://nostr.wine',
  ];

  function parseBunkerURI(uri) {
    const m = uri.match(/^bunker:\/\/([0-9a-f]{64})\?(.+)$/i);
    if (!m) throw new Error('Invalid bunker:// URI');
    const params = new URLSearchParams(m[2]);
    return {
      remotePubkey: m[1].toLowerCase(),
      relays: params.getAll('relay'),
      secret: params.get('secret') || '',
    };
  }

  function buildNostrConnectURI({ clientPubkey, relays, secret, metadata = {}, perms }) {
    // Spec-compliant params (NIP-46): relay(s), secret, then optional name/url/
    // image and perms as discrete query params. Older mill emitted a single
    // `metadata=<json>` blob, which some signers (e.g. Amber) don't parse.
    const parts = relays.map(r => `relay=${encodeURIComponent(r)}`);
    parts.push(`secret=${secret}`);
    parts.push(`perms=${encodeURIComponent(perms)}`);
    if (metadata.name) parts.push(`name=${encodeURIComponent(metadata.name)}`);
    if (metadata.url) parts.push(`url=${encodeURIComponent(metadata.url)}`);
    if (metadata.image) parts.push(`image=${encodeURIComponent(metadata.image)}`);
    return `nostrconnect://${clientPubkey}?${parts.join('&')}`;
  }

  function randomHex(bytes = 16) {
    return bytesToHex$4(crypto.getRandomValues(new Uint8Array(bytes)));
  }

  class NIP46Client {
    constructor({ relays = DEFAULT_RELAYS, metadata = {}, debug = false, onLog = null, onAuthChallenge = null, clientSecretKey = null } = {}) {
      this.relays = relays;
      this.metadata = metadata;
      this.debug = debug;
      this.onLog = onLog;                   // optional callback for surfacing logs in UI
      this.onAuthChallenge = onAuthChallenge; // optional: fired with the auth_url the signer asks the user to approve
      // A provided key (Uint8Array) restores a prior client identity so the
      // bunker recognizes us after a reload; otherwise generate a fresh one.
      this.clientSecretKey = clientSecretKey || generateSecretKey$1();
      this.clientPubkey = getPublicKey$1(this.clientSecretKey);
      this.remotePubkey = null;
      this.userPubkey = null;
      this.pool = null;
      this.sub = null;
      this.pending = new Map();
      this.connected = false;
      this._closed = false;
      this._eventCount = 0;
      this._publishedCount = 0;
      this._decryptFailures = 0;
    }

    _log(level, ...args) {
      const msg = args.map(a => typeof a === 'string' ? a : JSON.stringify(a)).join(' ');
      if (this.debug) console[level === 'err' ? 'warn' : 'log']('[NIP-46]', ...args);
      this.onLog?.({ level, msg, ts: Date.now() });
    }

    /**
     * Outbound (mill is initiator): user paste a bunker:// URI we connect to.
     * Returns a promise that resolves with userPubkey.
     */
    async connectViaBunker(bunkerUri, { timeoutMs = 60_000 } = {}) {
      const parsed = parseBunkerURI(bunkerUri);
      this.remotePubkey = parsed.remotePubkey;
      if (parsed.relays.length) this.relays = parsed.relays;

      this._openPool();

      // Send connect request, then resolve the user pubkey.
      const connectArgs = parsed.secret ? [this.remotePubkey, parsed.secret] : [this.remotePubkey];
      await this._request('connect', connectArgs, { timeoutMs });
      this.userPubkey = await this._resolveUserPubkey();
      this.connected = true;
      return this.userPubkey;
    }

    /**
     * Resolve the user's identity pubkey after a connection is established.
     * Prefers get_public_key (works for signers whose user key differs from the
     * remote-signer key). When that goes unanswered — Amber answers signing but
     * not get_public_key in both the bunker and nostrconnect flows — derive the
     * pubkey from a signed probe event, whose author is the user's real key.
     */
    async _resolveUserPubkey({ timeoutMs = 15_000 } = {}) {
      try {
        return await this._request('get_public_key', [], { timeoutMs });
      } catch (e) {
        this._log('info', `get_public_key unanswered (${e.message}); deriving user pubkey from a signed probe`);
        const probe = await this.signEvent({
          kind: 27235,
          created_at: Math.floor(Date.now() / 1000),
          tags: [['challenge', 'mill-connect']],
          content: '',
        });
        if (!probe || !probe.pubkey) throw new Error('Signer did not return a usable public key');
        this._log('info', `Derived user pubkey ${probe.pubkey.slice(0, 8)}… from signed probe`);
        return probe.pubkey;
      }
    }

    /**
     * Inbound (bunker is initiator): we display nostrconnect:// URI for user
     * to scan/paste into their bunker. Returns a promise that resolves when
     * the bunker contacts us back, with userPubkey.
     *
     * onURI callback fires once with the URI (so caller can render QR).
     */
    async connectAsListener({ timeoutMs = 120_000, onURI } = {}) {
      const secret = randomHex(16);
      const uri = buildNostrConnectURI({
        clientPubkey: this.clientPubkey,
        relays: this.relays,
        secret,
        metadata: this.metadata,
        // Pre-request the perms we actually use so the signer can authorize them
        // up front instead of challenging on the first sign.
        perms: 'sign_event,nip44_encrypt,nip44_decrypt,nip04_encrypt,nip04_decrypt',
      });
      onURI?.(uri);

      this._openPool();

      // Wait for first inbound 24133 event from any pubkey, then validate secret.
      const remotePubkey = await new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('NIP-46 connection timed out')), timeoutMs);
        this._connectListener = { resolve: pk => { clearTimeout(timer); resolve(pk); }, reject: e => { clearTimeout(timer); reject(e); }, secret };
      });

      this.remotePubkey = remotePubkey;
      // The connect author is only the per-connection transport key — resolve
      // the real user identity (get_public_key, or a signed-probe fallback).
      this.userPubkey = await this._resolveUserPubkey();
      this.connected = true;
      return this.userPubkey;
    }

    /**
     * Restore a previously-established session after a page reload. The bunker
     * already authorized our client pubkey during the original pairing, so we
     * only need to re-open the relay subscription — no new connect handshake.
     * Construct the client with the persisted `clientSecretKey` first, then call
     * this with the saved remote pubkey / relays / user pubkey.
     *
     * If the bunker has since forgotten the client, the first signEvent will
     * time out and the caller should fall back to a fresh pairing.
     */
    async restore({ remotePubkey, relays, userPubkey } = {}) {
      if (!remotePubkey) throw new Error('restore requires remotePubkey');
      this.remotePubkey = remotePubkey;
      if (Array.isArray(relays) && relays.length) this.relays = relays;
      this.userPubkey = userPubkey || null;
      this._closed = false;
      this._openPool();
      this.connected = true;
      this._log('info', `Restored NIP-46 session for ${this.clientPubkey.slice(0, 8)}… → ${remotePubkey.slice(0, 8)}…`);
      return this.userPubkey;
    }

    async getPublicKey() {
      if (this.userPubkey) return this.userPubkey;
      return this._request('get_public_key', []);
    }

    async signEvent(event) {
      const result = await this._request('sign_event', [JSON.stringify(event)]);
      return typeof result === 'string' ? JSON.parse(result) : result;
    }

    async nip04Encrypt(thirdPartyPubkey, plaintext) {
      return this._request('nip04_encrypt', [thirdPartyPubkey, plaintext]);
    }
    async nip04Decrypt(thirdPartyPubkey, ciphertext) {
      return this._request('nip04_decrypt', [thirdPartyPubkey, ciphertext]);
    }
    async nip44Encrypt(thirdPartyPubkey, plaintext) {
      return this._request('nip44_encrypt', [thirdPartyPubkey, plaintext]);
    }
    async nip44Decrypt(thirdPartyPubkey, ciphertext) {
      return this._request('nip44_decrypt', [thirdPartyPubkey, ciphertext]);
    }

    disconnect() {
      if (this._closed) return;
      this._closed = true;
      this.connected = false;
      for (const [, p] of this.pending) { clearTimeout(p.timer); p.reject(new Error('NIP-46 disconnected')); }
      this.pending.clear();
      try { this.sub?.close?.(); } catch {}
      try { this.pool?.close?.(this.relays); } catch {}
      this.pool = null;
      this.sub = null;
    }

    // ── internals ──────────────────────────────────────────────────────────────
    _openPool() {
      if (this.pool) return;
      this.pool = new SimplePool();
      this._log('info', `Subscribing to ${this.relays.length} relays:`, this.relays.join(', '));
      this._log('info', `Client pubkey: ${this.clientPubkey}`);
      // CRITICAL: SimplePool.subscribeMany takes a SINGLE filter object, not an array.
      // Passing an array silently sends a malformed REQ that all relays reject with
      // "bad req: provided filter is not an object" — mill never receives any events.
      this.sub = this.pool.subscribeMany(
        this.relays,
        { kinds: [24133], '#p': [this.clientPubkey], since: Math.floor(Date.now() / 1000) - 60 },
        {
          onevent: e => { this._eventCount++; this._log('info', `← Event ${this._eventCount} from ${e.pubkey.slice(0,8)}…`); this._onEvent(e); },
          oneose:  () => { this._log('info', 'Subscription EOSE — relays ready'); },
          onclose: (reasons) => { this._log('err', 'Subscription closed:', reasons); },
        }
      );
    }

    // Try NIP-44 v2 first, fall back to NIP-04 — older bunkers and some Amber
    // versions still use NIP-04 for kind 24133. Remember which one worked so
    // we encrypt the response with the same scheme.
    async _decrypt(senderPk, ciphertext) {
      let nip44Err = null, nip04Err = null;
      try {
        const convKey = nip44_exports.v2.utils.getConversationKey(this.clientSecretKey, senderPk);
        const pt = nip44_exports.v2.decrypt(ciphertext, convKey);
        this._lastEncScheme = 'nip44';
        this._log('info', '✓ Decrypted with NIP-44');
        return pt;
      } catch (e) { nip44Err = e?.message || String(e); }
      try {
        const pt = await nip04_exports.decrypt(this.clientSecretKey, senderPk, ciphertext);
        this._lastEncScheme = 'nip04';
        this._log('info', '✓ Decrypted with NIP-04');
        return pt;
      } catch (e) { nip04Err = e?.message || String(e); }
      this._decryptFailures++;
      this._log('err', `✗ Decrypt failed (NIP-44: ${nip44Err}; NIP-04: ${nip04Err})`);
      return null;
    }

    async _encrypt(remotePk, plaintext, scheme) {
      const s = scheme || this._lastEncScheme || 'nip44';
      if (s === 'nip04') {
        return nip04_exports.encrypt(this.clientSecretKey, remotePk, plaintext);
      }
      const convKey = nip44_exports.v2.utils.getConversationKey(this.clientSecretKey, remotePk);
      return nip44_exports.v2.encrypt(plaintext, convKey);
    }

    async _onEvent(event) {
      const senderPk = event.pubkey;
      const decrypted = await this._decrypt(senderPk, event.content);
      if (!decrypted) return;            // can't decrypt — ignore
      let msg;
      try { msg = JSON.parse(decrypted); } catch { return; }

      // Inbound nostrconnect:// from bunker. Different bunkers/Amber versions use
      // slightly different shapes — be permissive: accept the secret in any of
      // params[0], params[1], or result. Some bunkers also send `result: 'ack'`
      // and rely on the relay-tag pubkey for identity (we use senderPk for that).
      if (this._connectListener) {
        const sec = this._connectListener.secret;
        const secretMatches = msg.params?.[1] === sec || msg.params?.[0] === sec || msg.result === sec;
        const ackOnly = msg.result === 'ack' && !msg.method;
        this._log('info', `Candidate connect: method=${msg.method} result=${msg.result} params=${JSON.stringify(msg.params || [])} secretMatch=${secretMatches}`);
        // Accept if the secret matches anywhere — Amber sends `result: <secret>` with no method.
        // Some bunkers send `result: 'ack'` with no method. Both are valid handshake completions.
        if (secretMatches || ackOnly) {
          // Send ack back so bunker knows we accepted (only if they sent a connect request with id)
          if (msg.id) {
            try {
              const ack = JSON.stringify({ id: msg.id, result: 'ack' });
              const ackEnc = await this._encrypt(senderPk, ack);
              const ackEvent = finalizeEvent$1(
                { kind: 24133, created_at: Math.floor(Date.now() / 1000), tags: [['p', senderPk]], content: ackEnc },
                this.clientSecretKey
              );
              this.pool.publish(this.relays, ackEvent);
              this._log('info', `→ Sent ack to ${senderPk.slice(0,8)}…`);
            } catch (e) {
              this._log('err', `Ack publish failed: ${e?.message || e}`);
            }
          }
          const cb = this._connectListener;
          this._connectListener = null;
          cb.resolve(senderPk);
          return;
        }
      }

      // Response to one of our pending requests
      if (msg.id && this.pending.has(msg.id)) {
        const p = this.pending.get(msg.id);

        // Auth challenge (NIP-46): the signer needs the user to approve. The URL
        // lives in `error`, and the real response arrives LATER reusing the same
        // id. Surface the URL and keep waiting — do NOT reject or drop the
        // pending request. (Checked before the generic error branch because an
        // auth_url response also carries a truthy `error`.)
        if (msg.result === 'auth_url') {
          const url = msg.error || '';
          this._log('info', `Auth challenge — awaiting user approval: ${url}`);
          clearTimeout(p.timer);
          p.timer = setTimeout(() => {
            this.pending.delete(msg.id);
            p.reject(new Error('NIP-46 authorization timed out'));
          }, 120_000);
          try { this.onAuthChallenge?.(url); } catch (_) {}
          return;
        }

        this.pending.delete(msg.id);
        clearTimeout(p.timer);
        if (msg.error) p.reject(new Error(msg.error));
        else p.resolve(msg.result);
      }
    }

    async _request(method, params, { timeoutMs = 30_000 } = {}) {
      if (!this.remotePubkey) throw new Error('NIP-46 not connected');
      if (this._closed) throw new Error('NIP-46 client closed');

      const id = randomHex(8);
      const payload = JSON.stringify({ id, method, params });
      const encrypted = await this._encrypt(this.remotePubkey, payload);

      const event = finalizeEvent$1(
        { kind: 24133, created_at: Math.floor(Date.now() / 1000), tags: [['p', this.remotePubkey]], content: encrypted },
        this.clientSecretKey
      );

      const promise = new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
          this.pending.delete(id);
          reject(new Error(`NIP-46 ${method} timed out`));
        }, timeoutMs);
        this.pending.set(id, { resolve, reject, timer });
      });

      // Publish to all relays, log per-relay outcome, succeed if any accept.
      const publishResults = this.pool.publish(this.relays, event);
      publishResults.forEach((p, i) => {
        Promise.resolve(p).then(
          () => { this._publishedCount++; this._log('info', `→ Published to ${this.relays[i]}`); },
          (err) => this._log('err', `✗ Publish to ${this.relays[i]} failed: ${err?.message || err}`),
        );
      });
      try {
        await Promise.any(publishResults);
        this._log('info', `→ Sent ${method} (id=${id.slice(0,4)}…) to ${this.remotePubkey.slice(0,8)}…`);
      } catch (e) {
        this.pending.delete(id);
        throw new Error('Failed to publish NIP-46 request to any relay');
      }
      return promise;
    }
  }

  /**
   * MILL — nip55.js
   * NIP-55 Android signer (Amber) intent helpers. Framework-agnostic.
   *
   * Amber returns results either as URL query params (?event=…) on the
   * configured callbackUrl, OR as a localStorage entry written by a callback
   * page. Mill exposes both: a one-shot openAmberIntent() + setupCallbackListener(),
   * and a long-lived AmberSigner that fires a fresh intent for every signEvent.
   */

  const STORAGE_KEY = 'mill:amber:result';

  // On script load (browser only), capture any `?event=` from the URL into
  // localStorage. Amber's callback may trigger a full page reload, destroying
  // the awaitAmberResult listener — this snapshot lets a freshly-loaded page
  // (or a fresh awaitAmberResult call) recover the result.
  // Read an Amber result from either the `#event=` fragment (what mill now asks
  // for) or a legacy `?event=` query param.
  function readCallbackResult(href) {
    try {
      const url = new URL(href);
      const hash = url.hash.replace(/^#/, '');
      if (/^event=/.test(hash)) {
        return { event: decodeURIComponent(hash.slice(6)), error: null, from: 'hash' };
      }
      const sp = url.searchParams;
      const event = sp.get('event');
      const error = sp.get('error');
      if (event || error) return { event, error, from: 'query' };
    } catch {}
    return null;
  }

  if (typeof window !== 'undefined' && typeof localStorage !== 'undefined') {
    try {
      const hit = readCallbackResult(window.location.href);
      const event = hit?.event || null;
      const error = hit?.error || null;
      if (event || error) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ event, error, ts: Date.now() }));
        // Notify any opener (cross-window callback flow)
        if (window.opener) {
          try { window.opener.postMessage({ amberEvent: event, amberError: error }, '*'); } catch {}
        }
        // Clean URL so a refresh doesn't keep re-firing. Must clear the fragment
        // too, otherwise the hash form replays on every reload.
        try {
          const url = new URL(window.location.href);
          url.searchParams.delete('event');
          url.searchParams.delete('error');
          const hash = hit?.from === 'hash' ? '' : url.hash;
          history.replaceState(null, '', url.pathname + (url.search || '') + hash);
        } catch {}
      }
    } catch {}
  }

  /**
   * Normalise a host-supplied callback URL for Amber's concatenation behaviour.
   *
   * Amber does NOT append a param name — it literally does
   * `callbackUrl + Uri.encode(result)`. So the URL must already end in the
   * separator + param name, or the result is glued onto the path and lost.
   *
   * We use a `#event=` fragment rather than `?event=` because Amber ≥ 6.0.0
   * fully URL-decodes the intent URI and *then* splits on `?`, which shreds any
   * query string inside the callback URL (regression in commit 18db8c3d). A
   * fragment survives both old and new parsers — and, as a bonus, never reaches
   * the host's server, so the signature stays out of access logs.
   */
  function normalizeCallbackUrl(url) {
    if (!url) return url;
    if (/[?#]event=$/.test(url)) return url;          // already correct
    const bare = url.replace(/[?#]$/, '');
    return `${bare}#event=`;
  }

  function buildAmberURL({
    type = 'get_public_key',
    callbackUrl,
    appName = 'Nostr App',
    pubkey,
    eventJson,
    permissions,
    // get_public_key returns a bare hex pubkey; sign_event needs the full event
    // JSON back, which only returnType=event provides.
    returnType = type === 'sign_event' ? 'event' : 'signature',
  }) {
    const params = new URLSearchParams();
    params.set('compressionType', 'none');
    params.set('returnType', returnType);
    params.set('type', type);
    // Omitting callbackUrl is deliberate and supported: Amber then copies the
    // result to the clipboard, which is how mill reads it back with no host-side
    // callback route at all. See awaitAmberClipboard().
    if (callbackUrl) params.set('callbackUrl', normalizeCallbackUrl(callbackUrl));
    if (appName)     params.set('appName', appName);
    if (pubkey)      params.set('pubKey', pubkey);
    if (permissions) params.set('permissions', JSON.stringify(permissions));

    const base = `nostrsigner:${eventJson ? encodeURIComponent(eventJson) : ''}?${params.toString()}`;
    return base;
  }

  function isLocalhost() {
    if (typeof window === 'undefined') return false;
    return /^(localhost|127\.0\.0\.1|0\.0\.0\.0)$/.test(window.location.hostname);
  }

  /**
   * Fire-and-forget: open Amber via best available method.
   * Returns true if at least one method seemed to fire.
   */
  function openAmberIntent(url) {
    try {
      const a = document.createElement('a');
      a.href = url; a.target = '_blank'; a.rel = 'noopener';
      a.style.display = 'none';
      document.body.appendChild(a);
      a.click();
      setTimeout(() => a.remove(), 250);
      return true;
    } catch {
      try { window.location.href = url; return true; } catch {}
      try { const w = window.open(url, '_blank'); if (w) { w.close(); return true; } } catch {}
    }
    return false;
  }

  /**
   * Listen for an Amber callback. Resolves with the raw `event` query param
   * (which is either a pubkey hex for get_public_key, or a signed event JSON).
   *
   * The host app must have a callback route that captures `?event=…` and either:
   *   - redirects back to the page that opened Amber (then we read URL or storage)
   *   - writes localStorage[mill:amber:result] = JSON({ event, error? })
   *
   * Or the same page is the callback (single-page setup).
   */
  function awaitAmberResult({ timeoutMs = 60_000 } = {}) {
    return new Promise((resolve, reject) => {
      let done = false;
      const finish = (fn, val) => { if (done) return; done = true; cleanup(); fn(val); };

      const checkURL = () => {
        try {
          const hit = readCallbackResult(window.location.href);
          if (!hit) return;
          if (hit.error) finish(reject, new Error(hit.error));
          else if (hit.event) finish(resolve, hit.event);
        } catch {}
      };

      const checkStorage = () => {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        try {
          const data = JSON.parse(raw);
          // Discard entries older than 10 minutes — stale callbacks shouldn't trigger fresh flows
          if (data.ts && Date.now() - data.ts > 10 * 60 * 1000) {
            localStorage.removeItem(STORAGE_KEY);
            return;
          }
          localStorage.removeItem(STORAGE_KEY);
          if (data.error) finish(reject, new Error(data.error));
          else if (data.event) finish(resolve, data.event);
        } catch (e) {
          localStorage.removeItem(STORAGE_KEY);
        }
      };

      const onVis    = () => { if (!document.hidden) setTimeout(() => { checkURL(); checkStorage(); }, 400); };
      const onFocus  = () => { setTimeout(() => { checkURL(); checkStorage(); }, 400); };
      const onMsg    = e => { if (e.data?.amberEvent) finish(resolve, e.data.amberEvent); };
      const onStorage = e => { if (e.key === STORAGE_KEY) checkStorage(); };
      // A `#event=` callback that lands on the page already open is a
      // same-document navigation — no reload, so only hashchange fires.
      const onHash   = () => checkURL();

      document.addEventListener('visibilitychange', onVis);
      window.addEventListener('focus', onFocus);
      window.addEventListener('message', onMsg);
      window.addEventListener('storage', onStorage);
      window.addEventListener('hashchange', onHash);
      const poll = setInterval(() => { checkURL(); checkStorage(); }, 1500);
      const timer = setTimeout(() => finish(reject, new Error('Amber callback timed out')), timeoutMs);

      function cleanup() {
        document.removeEventListener('visibilitychange', onVis);
        window.removeEventListener('focus', onFocus);
        window.removeEventListener('message', onMsg);
        window.removeEventListener('storage', onStorage);
        window.removeEventListener('hashchange', onHash);
        clearInterval(poll);
        clearTimeout(timer);
      }

      // Initial check in case we're already on the callback URL
      setTimeout(() => { checkURL(); checkStorage(); }, 100);
    });
  }

  const HEX64  = /^[0-9a-f]{64}$/i;
  const HEX128 = /^[0-9a-f]{128}$/i;

  /** Does this clipboard string look like an Amber result rather than junk? */
  function looksLikeAmberResult(s) {
    if (!s) return false;
    const t = s.trim();
    if (HEX64.test(t) || HEX128.test(t)) return true;      // pubkey / signature
    if (/^npub1[023-9ac-hj-np-z]+$/.test(t)) return true;
    if (t.startsWith('{')) {                                // signed event JSON
      try { const o = JSON.parse(t); return !!(o.sig || o.pubkey); } catch { return false; }
    }
    return false;
  }

  /**
   * Read an Amber result back off the clipboard.
   *
   * When no callbackUrl is supplied, Amber copies the result to the clipboard and
   * shows a toast — this is the documented no-callback behaviour and the path
   * used by applesauce and Nostria. It needs no callback route, no server, and no
   * host-app code, so it is mill's default.
   *
   * Caveats: navigator.clipboard.readText() needs a secure context and, on
   * Chromium, a clipboard-read permission grant (prompted once per origin). We
   * snapshot the clipboard before opening Amber so pre-existing content is never
   * mistaken for a result.
   */
  function awaitAmberClipboard({ timeoutMs = 60_000, before = '' } = {}) {
    return new Promise((resolve, reject) => {
      if (!navigator?.clipboard?.readText) {
        reject(new Error('Clipboard read unavailable — needs HTTPS and a supporting browser.'));
        return;
      }
      let done = false;
      const finish = (fn, val) => { if (done) return; done = true; cleanup(); fn(val); };

      const tryRead = async () => {
        if (done) return;
        try {
          const txt = (await navigator.clipboard.readText())?.trim();
          if (!txt || txt === before?.trim()) return;      // unchanged — not our result
          if (looksLikeAmberResult(txt)) finish(resolve, txt);
        } catch {
          // Permission denied or not focused yet — keep polling; the visibility
          // handler retries once the tab is actually foregrounded.
        }
      };

      // Amber returns by switching back to the browser, so foregrounding is the
      // signal. The delay lets the clipboard settle before the first read.
      const onVis   = () => { if (!document.hidden) setTimeout(tryRead, 300); };
      const onFocus = () => setTimeout(tryRead, 300);

      document.addEventListener('visibilitychange', onVis);
      window.addEventListener('focus', onFocus);
      const poll  = setInterval(tryRead, 700);
      const timer = setTimeout(
        () => finish(reject, new Error('Timed out waiting for Amber. If you approved the request, allow clipboard access and try again.')),
        timeoutMs,
      );

      function cleanup() {
        document.removeEventListener('visibilitychange', onVis);
        window.removeEventListener('focus', onFocus);
        clearInterval(poll);
        clearTimeout(timer);
      }
    });
  }

  /** Best-effort snapshot of current clipboard text, to ignore stale content. */
  async function snapshotClipboard() {
    try { return (await navigator.clipboard.readText()) || ''; } catch { return ''; }
  }

  /**
   * Helper for the host app's callback page:
   * call this once at the top of the callback route to forward the result.
   * Pass autoClose: true to close the popup after writing.
   */
  function deliverAmberCallback({ autoClose = false } = {}) {
    try {
      const hit = readCallbackResult(window.location.href);
      const event = hit?.event || null;
      const error = hit?.error || null;
      if (event || error) {
        // ts included so awaitAmberResult's staleness check works on this path too
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ event, error, ts: Date.now() }));
        if (window.opener) {
          try { window.opener.postMessage({ amberEvent: event, amberError: error }, '*'); } catch {}
        }
        if (autoClose) setTimeout(() => window.close(), 200);
      }
    } catch {}
  }

  var nip55 = /*#__PURE__*/Object.freeze({
    __proto__: null,
    awaitAmberClipboard: awaitAmberClipboard,
    awaitAmberResult: awaitAmberResult,
    buildAmberURL: buildAmberURL,
    deliverAmberCallback: deliverAmberCallback,
    isLocalhost: isLocalhost,
    normalizeCallbackUrl: normalizeCallbackUrl,
    openAmberIntent: openAmberIntent,
    snapshotClipboard: snapshotClipboard
  });

  /**
   * MILL — kinds.js
   * Human-readable names for Nostr event kinds, for the signing consent card.
   *
   * Table adapted from grain's client/nostr_kinds.go, which sources the Event
   * Kinds section of github.com/nostr-protocol/nips README. Labels here are
   * rephrased for a consent sentence — the card reads "wants you to sign a
   * {label}", so labels are noun phrases the user might actually recognise
   * ("Reaction", not "User Metadata (NIP-01)"). The NIP is kept separately for
   * the details view rather than baked into the label.
   *
   * Intentionally not exhaustive — only labels we can stand behind. Unknown
   * kinds fall back to the event's `alt` tag, then to "Event kind N". Update by
   * reading the upstream README, not by inventing labels.
   */

  // kind → [label, nip]
  const KINDS = {
    0:     ['Profile Update',            'NIP-01'],
    1:     ['Note',                      'NIP-01'],
    2:     ['Relay Recommendation',      'deprecated'],
    3:     ['Follow List',               'NIP-02'],
    4:     ['Encrypted Message',         'NIP-04, deprecated'],
    5:     ['Deletion Request',          'NIP-09'],
    6:     ['Repost',                    'NIP-18'],
    7:     ['Reaction',                  'NIP-25'],
    8:     ['Badge Award',               'NIP-58'],
    9:     ['Chat Message',              'NIP-C7'],
    13:    ['Seal',                      'NIP-59'],
    14:    ['Direct Message',            'NIP-17'],
    15:    ['File Message',              'NIP-17'],
    16:    ['Repost',                    'NIP-18'],
    17:    ['Website Reaction',          'NIP-25'],
    20:    ['Picture Post',              'NIP-68'],
    21:    ['Video Post',                'NIP-71'],
    22:    ['Short Video',               'NIP-71'],
    40:    ['Channel Creation',          'NIP-28'],
    41:    ['Channel Metadata',          'NIP-28'],
    42:    ['Channel Message',           'NIP-28'],
    43:    ['Channel Message Hide',      'NIP-28, deprecated'],
    44:    ['Channel User Mute',         'NIP-28, deprecated'],
    1059:  ['Gift Wrap',                 'NIP-59'],
    1063:  ['File Metadata',             'NIP-94'],
    1311:  ['Live Chat Message',         'NIP-53'],
    1984:  ['Report',                    'NIP-56'],
    1985:  ['Label',                     'NIP-32'],
    9734:  ['Zap Request',               'NIP-57'],
    9735:  ['Zap Receipt',               'NIP-57'],
    10000: ['Mute List',                 'NIP-51'],
    10001: ['Pin List',                  'NIP-51'],
    10002: ['Relay List',                'NIP-65'],
    10003: ['Bookmark List',             'NIP-51'],
    10004: ['Communities List',          'NIP-51'],
    10005: ['Public Chats List',         'NIP-51'],
    10006: ['Blocked Relays List',       'NIP-51'],
    10007: ['Search Relays List',        'NIP-51'],
    10015: ['Interests List',            'NIP-51'],
    10030: ['Emoji List',                'NIP-51'],
    22242: ['Relay Authentication',      'NIP-42'],
    27235: ['HTTP Authentication',       'NIP-98'],
    30000: ['Follow Set',                'NIP-51'],
    30002: ['Relay Set',                 'NIP-51'],
    30003: ['Bookmark Set',              'NIP-51'],
    30004: ['Curation Set',              'NIP-51'],
    30008: ['Profile Badges',            'NIP-58'],
    30009: ['Badge Definition',          'NIP-58'],
    30015: ['Interest Set',              'NIP-51'],
    30017: ['Stall',                     'NIP-15'],
    30018: ['Product',                   'NIP-15'],
    30023: ['Article',                   'NIP-23'],
    30024: ['Article Draft',             'NIP-23'],
    30030: ['Emoji Set',                 'NIP-51'],
    30078: ['App Data',                  'NIP-78'],
    30311: ['Live Event',                'NIP-53'],
    30315: ['Status Update',             'NIP-38'],
    30402: ['Classified Listing',        'NIP-99'],
    30403: ['Classified Listing Draft',  'NIP-99'],
    31922: ['Calendar Event',            'NIP-52'],
    31923: ['Calendar Event',            'NIP-52'],
    31924: ['Calendar',                  'NIP-52'],
    31925: ['Calendar RSVP',             'NIP-52'],
    31989: ['App Recommendation',        'NIP-89'],
    31990: ['App Handler',               'NIP-89'],
    34550: ['Community Definition',      'NIP-72'],
  };

  /** The NIP a kind is defined by, or '' if unknown. */
  function kindNip(kind) { return KINDS[kind]?.[1] || ''; }

  /**
   * Human-readable name for what's being signed.
   *
   * Fallback chain mirrors Amber's: known kind → the event's own `alt` tag
   * (NIP-31, which exists precisely so unknown kinds can describe themselves)
   * → a bare "Event kind N". Never returns an empty string.
   */
  function kindLabel(event) {
    const kind = typeof event === 'number' ? event : event?.kind;
    const hit = KINDS[kind];
    if (hit) return hit[0];

    const group = kindGroupLabel(kind);
    if (group) return group;

    if (event && Array.isArray(event.tags)) {
      const alt = event.tags.find(t => t?.[0] === 'alt')?.[1];
      if (alt && alt.trim()) return alt.trim();
    }
    return kind === undefined || kind === null ? 'Unknown Event' : `Event kind ${kind}`;
  }

  /**
   * Indefinite article for a label, so the consent sentence reads "an Article"
   * rather than "a Article". Vowel-letter test is wrong for a few words
   * ("a User Status"), but every label in the table above is regular.
   */
  function kindArticle(label) {
    return /^[aeiou]/i.test(String(label || '')) ? 'an' : 'a';
  }

  /**
   * Ranges get one generic label rather than 2000 individual entries.
   * NIP-90 data-vending-machine kinds are allocated in blocks, so the specific
   * number carries less meaning than the block does.
   */
  function kindGroupLabel(kind) {
    if (kind >= 5000 && kind <= 5999) return 'Job Request';
    if (kind >= 6000 && kind <= 6999) return 'Job Result';
    if (kind >= 7000 && kind <= 7999) return 'Job Feedback';
    return null;
  }

  // ── Permission categories ─────────────────────────────────────────────────────
  // The coarse buckets shown on the setup screen. Runtime grants are per-kind
  // (matching Amber), but the pre-approval policy is chosen per category — six
  // choices is a screen a person will actually read; 60 is not.
  function categoryForKind(kind) {
    if (kind === 1 || kind === 6 || kind === 7 || kind === 16) return 'notes';
    if (kind === 0) return 'profile';
    if (kind === 3) return 'contacts';
    if (kind === 4 || kind === 14 || kind === 1059 || kind === 13) return 'dms';
    if (kind === 9734 || kind === 9735) return 'zaps';
    return 'other';
  }

  function categoryFor(event) {
    return categoryForKind(typeof event === 'number' ? event : event?.kind);
  }

  /**
   * MILL — grants.js
   * Per-kind signing grants for the private-key signer.
   *
   * Model follows Amber's, which splits the decision into two orthogonal axes:
   *   what     — allow | deny | ask
   *   how long — this time only | 5m | 1h | this session | always
   *
   * A grant is keyed by event kind, not by category. Amber keys on
   * (app, type, kind) with an explicit note that this prevents "kind-A rejects
   * leaking to kind-B requests"; mill lives inside a single app, so the app axis
   * collapses and the key is just the kind.
   *
   * Deny carries its own expiry so "reject this for an hour" is expressible —
   * that's why Amber's schema has both acceptUntil and rejectUntil.
   *
   * Storage split matters:
   *   - 'always' grants → localStorage, outliving the tab.
   *   - everything else → sessionStorage, dying with the tab AND with the
   *     encrypted key blob, which also lives in sessionStorage. A grant that
   *     outlived the key it authorises would be meaningless.
   */

  const SESSION_KEY = 'mill:grants:session';
  const ALWAYS_KEY  = 'mill:grants:always';

  const FOREVER = 8640000000000000;   // max safe Date value

  // Duration options for the consent card. `ms: null` means "don't remember" —
  // deliberately first and default, so the safe choice is the no-op and
  // persistence is always an active opt-in (Amber defaults its picker to Never).
  const DURATIONS = [
    { id: 'once',    label: 'Just this time', ms: null },
    { id: '5m',      label: '5 minutes',      ms: 5 * 60 * 1000 },
    { id: '1h',      label: '1 hour',         ms: 60 * 60 * 1000 },
    { id: 'session', label: 'This session',   ms: FOREVER },   // sessionStorage bounds it
    { id: 'always',  label: 'Always',         ms: FOREVER },   // localStorage: survives reload
  ];

  function durationById(id) { return DURATIONS.find(d => d.id === id) || DURATIONS[0]; }

  function read(store, key) {
    try {
      const raw = store.getItem(key);
      const val = raw ? JSON.parse(raw) : null;
      return val && typeof val === 'object' ? val : {};
    } catch { return {}; }
  }

  function write(store, key, table) {
    try { store.setItem(key, JSON.stringify(table)); } catch {}
  }

  /**
   * Resolve the effective grant for a kind.
   * Returns 'allow' | 'deny' | null, where null means "ask the user".
   *
   * Expiry is checked at read time rather than trusted to a sweep — a stale row
   * must never authorise a signature just because cleanup hasn't run yet.
   */
  function grantFor(kind, now = Date.now()) {
    const k = String(kind);
    // Persistent grants are checked first: an explicit "always" outranks a
    // leftover time-boxed row for the same kind.
    for (const [store, key] of [[safeLocal(), ALWAYS_KEY], [safeSession(), SESSION_KEY]]) {
      if (!store) continue;
      const row = read(store, key)[k];
      if (!row) continue;
      if (typeof row.until === 'number' && row.until > now) {
        return row.action === 'deny' ? 'deny' : 'allow';
      }
    }
    return null;
  }

  /**
   * Record a decision. `durationId` of 'once' stores nothing — the decision
   * applies to the in-flight request only.
   */
  function saveGrant(kind, action, durationId, now = Date.now()) {
    const dur = durationById(durationId);
    if (dur.ms === null) return;                       // "just this time" — don't persist

    const persistent = dur.id === 'always';
    const store = persistent ? safeLocal() : safeSession();
    if (!store) return;
    const key   = persistent ? ALWAYS_KEY : SESSION_KEY;
    const table = read(store, key);
    table[String(kind)] = {
      action: action === 'deny' ? 'deny' : 'allow',
      until:  dur.ms === FOREVER ? FOREVER : now + dur.ms,
      dur:    dur.id,
    };
    write(store, key, table);
  }

  /**
   * Every live grant, for the management screen.
   *
   * Deduped by kind: a kind can hold a row in both stores, and grantFor()
   * resolves that by letting the persistent one win. This must agree, or the
   * screen would list a kind twice and offer to revoke a row that isn't the one
   * actually in force.
   */
  function listGrants(now = Date.now()) {
    const seen = new Map();
    for (const [store, key, scope] of [[safeLocal(), ALWAYS_KEY, 'always'], [safeSession(), SESSION_KEY, 'session']]) {
      if (!store) continue;
      const table = read(store, key);
      for (const [kind, row] of Object.entries(table)) {
        if (typeof row?.until !== 'number' || row.until <= now) continue;
        if (seen.has(kind)) continue;                  // persistent store wins
        seen.set(kind, { kind: Number(kind), action: row.action, until: row.until, dur: row.dur, scope });
      }
    }
    return [...seen.values()].sort((a, b) => a.kind - b.kind);
  }

  /** Drop a single kind's grant from both stores. Returns it to "ask". */
  function revokeGrant(kind) {
    const k = String(kind);
    for (const [store, key] of [[safeLocal(), ALWAYS_KEY], [safeSession(), SESSION_KEY]]) {
      if (!store) continue;
      const table = read(store, key);
      if (k in table) { delete table[k]; write(store, key, table); }
    }
  }

  function revokeAllGrants() {
    try { safeLocal()?.removeItem(ALWAYS_KEY); } catch {}
    try { safeSession()?.removeItem(SESSION_KEY); } catch {}
  }

  /**
   * Drop expired rows. Purely housekeeping so the stores don't grow without
   * bound — grantFor() already refuses expired rows, so correctness does not
   * depend on this running.
   */
  function sweepExpiredGrants(now = Date.now()) {
    for (const [store, key] of [[safeLocal(), ALWAYS_KEY], [safeSession(), SESSION_KEY]]) {
      if (!store) continue;
      const table = read(store, key);
      let dirty = false;
      for (const [kind, row] of Object.entries(table)) {
        if (typeof row?.until !== 'number' || row.until <= now) { delete table[kind]; dirty = true; }
      }
      if (dirty) write(store, key, table);
    }
  }

  // Storage can throw outright in some privacy modes / sandboxed iframes.
  function safeSession() { try { return window.sessionStorage; } catch { return null; } }
  function safeLocal()   { try { return window.localStorage;   } catch { return null; } }

  /**
   * MILL — signers.js
   * Uniform signer-object factory for all 6 methods.
   * Every signer exposes:
   *   { method, pubkey, npub, canSign, getPublicKey, signEvent,
   *     nip04?: { encrypt, decrypt }, nip44?: { encrypt, decrypt },
   *     disconnect() }
   */


  // ── NIP-07 (browser extension) ────────────────────────────────────────────────
  function createNIP07Signer(pubkey) {
    if (!window.nostr) throw new Error('NIP-07 extension not available');
    return {
      method: 'nip07',
      pubkey,
      npub: hexToNpub(pubkey),
      canSign: true,
      getPublicKey: () => window.nostr.getPublicKey(),
      signEvent:    (e) => window.nostr.signEvent(e),
      nip04: window.nostr.nip04 ? {
        encrypt: (pk, pt) => window.nostr.nip04.encrypt(pk, pt),
        decrypt: (pk, ct) => window.nostr.nip04.decrypt(pk, ct),
      } : undefined,
      nip44: window.nostr.nip44 ? {
        encrypt: (pk, pt) => window.nostr.nip44.encrypt(pk, pt),
        decrypt: (pk, ct) => window.nostr.nip44.decrypt(pk, ct),
      } : undefined,
      disconnect() {},
    };
  }

  // ── NIP-46 (remote bunker) ────────────────────────────────────────────────────
  function createNIP46Signer(client, userPubkey) {
    return {
      method: 'nip46',
      pubkey: userPubkey,
      npub: hexToNpub(userPubkey),
      canSign: true,
      getPublicKey: () => client.getPublicKey(),
      signEvent:    (e) => client.signEvent(e),
      nip04: {
        encrypt: (pk, pt) => client.nip04Encrypt(pk, pt),
        decrypt: (pk, ct) => client.nip04Decrypt(pk, ct),
      },
      nip44: {
        encrypt: (pk, pt) => client.nip44Encrypt(pk, pt),
        decrypt: (pk, ct) => client.nip44Decrypt(pk, ct),
      },
      disconnect() { client.disconnect(); },
    };
  }

  // ── NIP-55 (Amber) ────────────────────────────────────────────────────────────
  // Each signEvent fires a fresh nostrsigner: intent and awaits the callback.
  function createNIP55Signer({ pubkey, callbackUrl, appName }) {
    async function intentRoundtrip(type, eventJson, extra = {}) {
      // No callbackUrl → Amber returns via the clipboard, which needs no host
      // route. Snapshot first so stale clipboard content isn't misread.
      const before = callbackUrl ? '' : await snapshotClipboard();
      const url = buildAmberURL({ type, callbackUrl, appName, pubkey, eventJson, ...extra });
      openAmberIntent(url);
      return callbackUrl
        ? await awaitAmberResult({ timeoutMs: 60_000 })
        : await awaitAmberClipboard({ timeoutMs: 60_000, before });
    }

    return {
      method: 'nip55',
      pubkey,
      npub: hexToNpub(pubkey),
      canSign: true,
      getPublicKey: async () => pubkey,
      signEvent: async (event) => {
        const signedJson = await intentRoundtrip('sign_event', JSON.stringify(event));
        return JSON.parse(signedJson);
      },
      nip04: {
        encrypt: (pk, pt) => intentRoundtrip('nip04_encrypt', pt, { /* Amber reads pubkey + plaintext via params; consult Amber docs */ }),
        decrypt: (pk, ct) => intentRoundtrip('nip04_decrypt', ct),
      },
      nip44: {
        encrypt: (pk, pt) => intentRoundtrip('nip44_encrypt', pt),
        decrypt: (pk, ct) => intentRoundtrip('nip44_decrypt', ct),
      },
      disconnect() {},
    };
  }

  // ── Private key (encrypted nsec, prompts for password per perms) ──────────────
  //
  // `perms` is { categoryId: 'session'|'prompt' } — the coarse pre-approval
  // chosen at setup. 'session' auto-approves the category; 'prompt' shows the
  // consent card, where the user can then remember an answer per kind.
  // `promptPassword` is a function the host provides to ask the user for the
  // session password (returns Promise<string>). MILL's modal supplies one.
  // Two independent gates, deliberately not fused:
  //
  //   unlock  — "do we have the key?"      → password, once per session
  //   consent — "do you approve THIS event?" → per-kind grant, its own lifetime
  //
  // Amber works this way: its biometric gate wraps the app and is time-boxed,
  // and a remembered permission signs in a ContentProvider without launching an
  // Activity at all — so the biometric is skipped too. Fusing the two means
  // either a password per signature (which users disable immediately) or no
  // review at all.
  function createPrivateKeySigner({ pubkey, perms, promptPassword, requestConsent }) {
    let cachedKey = null;        // Uint8Array(32) once unlocked for this session

    // Gate 1: unlock. The key is encrypted at rest, so the first signature after
    // a page load always costs a password — that's the cipher, not policy.
    async function unlock() {
      if (cachedKey) return cachedKey;
      const enc = loadEncryptedNsec();
      if (!enc) throw new Error('No stored nsec — login again');
      const password = await promptPassword({});
      if (!password) throw new Error('Password required');
      const hex = await decryptNsec(enc, password);
      cachedKey = hexToBytes$2(hex);
      return cachedKey;
    }

    // Gate 2: consent. Resolution order — an explicit per-kind grant beats the
    // coarse category policy chosen at setup, which beats asking.
    async function authorize(event, type = 'sign_event') {
      const kind  = event?.kind;
      const grant = grantFor(kind);
      if (grant === 'deny')  throw new Error(`Blocked by your permissions: ${kindLabel(event)}`);
      if (grant === 'allow') return;

      const category = categoryFor(event);
      if ((perms?.[category] ?? 'prompt') === 'session') return;   // pre-approved at setup

      // No consent handler (someone building a signer by hand) — fall back to
      // the password gate rather than silently allowing.
      if (!requestConsent) {
        const password = await promptPassword({ category, policy: 'prompt' });
        if (!password) throw new Error('Password required');
        return;
      }

      const decision = await requestConsent({ event, kind, category, type, label: kindLabel(event) });
      if (!decision?.approved) {
        if (decision?.duration) saveGrant(kind, 'deny', decision.duration);
        throw new Error(`Signing rejected: ${kindLabel(event)}`);
      }
      saveGrant(kind, 'allow', decision.duration || 'once');
    }

    // nip04/nip44 encrypt+decrypt are not sign_event; they map to the 'dms'
    // category and carry no event to review.
    async function dmKey(type) {
      await authorize({ kind: 4, tags: [] }, type);
      return unlock();
    }

    return {
      method: 'privatekey',
      pubkey,
      npub: hexToNpub(pubkey),
      canSign: true,
      getPublicKey: async () => pubkey,
      signEvent: async (event) => {
        await authorize(event);
        return finalizeEvent$1(event, await unlock());
      },
      nip04: {
        encrypt: async (pk, pt) => nip04_exports.encrypt(await dmKey('nip04_encrypt'), pk, pt),
        decrypt: async (pk, ct) => nip04_exports.decrypt(await dmKey('nip04_decrypt'), pk, ct),
      },
      nip44: {
        encrypt: async (pk, pt) => nip44_exports.v2.encrypt(pt, nip44_exports.v2.utils.getConversationKey(await dmKey('nip44_encrypt'), pk)),
        decrypt: async (pk, ct) => nip44_exports.v2.decrypt(ct, nip44_exports.v2.utils.getConversationKey(await dmKey('nip44_decrypt'), pk)),
      },
      disconnect() {
        if (cachedKey) cachedKey.fill(0);
        cachedKey = null;
        clearStoredNsec();
      },
    };
  }

  // ── Read-only ─────────────────────────────────────────────────────────────────
  function createReadOnlySigner(pubkey) {
    return {
      method: 'readonly',
      pubkey,
      npub: hexToNpub(pubkey),
      canSign: false,
      getPublicKey: async () => pubkey,
      signEvent: async () => { throw new Error('Read-only signer cannot sign events'); },
      disconnect() {},
    };
  }
  function installAsWindowNostr(signer) {
    if (typeof window === 'undefined') return;
    window.nostr = {
      getPublicKey: () => signer.getPublicKey(),
      signEvent:    (e) => signer.signEvent(e),
      nip04: signer.nip04,
      nip44: signer.nip44,
    };
  }

  /**
   * MILL — oauth.js
   * Client half of the cloud-login popup handshake. The other half is the static
   * page in shim/mill-oauth.html, which the host deploys at a stable origin.
   *
   * Why a popup and not an in-page flow: Google validates the OAuth flow against
   * the origin running it, and drive.appdata is scoped per OAuth client. A
   * drop-in library on arbitrary host origins therefore cannot run the flow
   * itself. The shim is one fixed, registered origin that every host shares, so
   * "log in with Google" resolves to the same identity everywhere. See the shim
   * file header for the full rationale.
   *
   * This module is provider-agnostic on purpose: it knows how to open a shim and
   * receive a token by postMessage. Google specifics live in drive.js; a future
   * OneDrive/Dropbox shim would reuse this untouched.
   */

  function randomNonce() {
    const b = crypto.getRandomValues(new Uint8Array(16));
    return Array.from(b).map(x => x.toString(16).padStart(2, '0')).join('');
  }

  /**
   * Open the OAuth shim in a popup and resolve with the access token.
   *
   * @param {string} shimUrl  Absolute URL of the deployed shim page.
   * @returns {Promise<{ accessToken: string, expiresIn?: number, scope?: string }>}
   */
  function requestCloudToken(shimUrl, { timeoutMs = 120_000 } = {}) {
    return new Promise((resolve, reject) => {
      let shimOrigin;
      try { shimOrigin = new URL(shimUrl, location.href).origin; }
      catch { reject(new Error('Invalid OAuth shim URL')); return; }

      const nonce = randomNonce();
      // Our origin and the nonce travel in the fragment, never the query — a
      // fragment is not sent to the server, so neither value lands in an access
      // log or a Referer header.
      const url = `${shimUrl}#origin=${encodeURIComponent(location.origin)}&nonce=${nonce}`;

      // A centered, modest popup reads as "sign-in window" rather than a new tab.
      const w = 460, h = 640;
      const left = Math.max(0, (screen.width  - w) / 2);
      const top  = Math.max(0, (screen.height - h) / 2);
      const popup = window.open(url, 'mill-oauth', `width=${w},height=${h},left=${left},top=${top},noopener=no`);
      if (!popup) { reject(new Error('Popup blocked. Allow popups for this site and try again.')); return; }

      let settled = false;
      const finish = (fn, val) => {
        if (settled) return;
        settled = true;
        window.removeEventListener('message', onMsg);
        clearInterval(closedTimer);
        clearTimeout(timer);
        fn(val);
      };

      const onMsg = (e) => {
        // Three independent checks: the message must come from the shim's exact
        // origin, from our popup, and carry our one-time nonce. Any token that
        // fails these is not ours.
        if (e.origin !== shimOrigin) return;
        if (e.source !== popup) return;
        const d = e.data;
        if (!d || d.source !== 'mill-oauth' || d.nonce !== nonce) return;

        if (d.ok && d.accessToken) {
          finish(resolve, { accessToken: d.accessToken, expiresIn: d.expiresIn, scope: d.scope, sub: d.sub || null });
        } else {
          finish(reject, new Error(oauthErrorMessage(d.error)));
        }
      };
      window.addEventListener('message', onMsg);

      // If the user closes the popup without finishing, don't hang forever.
      const closedTimer = setInterval(() => {
        if (popup.closed) finish(reject, new Error('Sign-in was cancelled.'));
      }, 500);

      const timer = setTimeout(() => {
        try { popup.close(); } catch {}
        finish(reject, new Error('Sign-in timed out.'));
      }, timeoutMs);
    });
  }

  function oauthErrorMessage(code) {
    switch (code) {
      case 'popup_failed':
      case 'popup_closed':      return 'The Google window closed before sign-in finished.';
      case 'access_denied':     return 'You declined the Google permission. Sign-in needs access to its own hidden app folder to store your key.';
      case 'no_token':          return 'Google did not return access. Please try again.';
      default:                  return code ? `Sign-in failed (${code}).` : 'Sign-in failed.';
    }
  }

  /**
   * PBKDF (RFC 2898). Can be used to create a key from password and salt.
   * @module
   */
  // Common start and end for sync/async functions
  function pbkdf2Init(hash, _password, _salt, _opts) {
      ahash$1(hash);
      const opts = checkOpts$1({ dkLen: 32, asyncTick: 10 }, _opts);
      const { c, dkLen, asyncTick } = opts;
      anumber$3(c, 'c');
      anumber$3(dkLen, 'dkLen');
      anumber$3(asyncTick, 'asyncTick');
      if (c < 1)
          throw new Error('iterations (c) must be >= 1');
      const password = kdfInputToBytes(_password, 'password');
      const salt = kdfInputToBytes(_salt, 'salt');
      // DK = PBKDF2(PRF, Password, Salt, c, dkLen);
      const DK = new Uint8Array(dkLen);
      // U1 = PRF(Password, Salt + INT_32_BE(i))
      const PRF = hmac$2.create(hash, password);
      const PRFSalt = PRF._cloneInto().update(salt);
      return { c, dkLen, asyncTick, DK, PRF, PRFSalt };
  }
  function pbkdf2Output(PRF, PRFSalt, DK, prfW, u) {
      PRF.destroy();
      PRFSalt.destroy();
      if (prfW)
          prfW.destroy();
      clean$2(u);
      return DK;
  }
  /**
   * PBKDF2-HMAC: RFC 2898 key derivation function
   * @param hash - hash function that would be used e.g. sha256
   * @param password - password from which a derived key is generated
   * @param salt - cryptographic salt
   * @param opts - {c, dkLen} where c is work factor and dkLen is output message size
   * @example
   * const key = pbkdf2(sha256, 'password', 'salt', { dkLen: 32, c: Math.pow(2, 18) });
   */
  function pbkdf2(hash, password, salt, opts) {
      const { c, dkLen, DK, PRF, PRFSalt } = pbkdf2Init(hash, password, salt, opts);
      let prfW; // Working copy
      const arr = new Uint8Array(4);
      const view = createView$4(arr);
      const u = new Uint8Array(PRF.outputLen);
      // DK = T1 + T2 + ⋯ + Tdklen/hlen
      for (let ti = 1, pos = 0; pos < dkLen; ti++, pos += PRF.outputLen) {
          // Ti = F(Password, Salt, c, i)
          const Ti = DK.subarray(pos, pos + PRF.outputLen);
          view.setInt32(0, ti, false);
          // F(Password, Salt, c, i) = U1 ^ U2 ^ ⋯ ^ Uc
          // U1 = PRF(Password, Salt + INT_32_BE(i))
          (prfW = PRFSalt._cloneInto(prfW)).update(arr).digestInto(u);
          Ti.set(u.subarray(0, Ti.length));
          for (let ui = 1; ui < c; ui++) {
              // Uc = PRF(Password, Uc−1)
              PRF._cloneInto(prfW).update(u).digestInto(u);
              for (let i = 0; i < Ti.length; i++)
                  Ti[i] ^= u[i];
          }
      }
      return pbkdf2Output(PRF, PRFSalt, DK, prfW, u);
  }

  /**
   * RFC 7914 Scrypt KDF. Can be used to create a key from password and salt.
   * @module
   */
  // The main Scrypt loop: uses Salsa extensively.
  // Six versions of the function were tried, this is the fastest one.
  // prettier-ignore
  function XorAndSalsa(prev, pi, input, ii, out, oi) {
      // Based on https://cr.yp.to/salsa20.html
      // Xor blocks
      let y00 = prev[pi++] ^ input[ii++], y01 = prev[pi++] ^ input[ii++];
      let y02 = prev[pi++] ^ input[ii++], y03 = prev[pi++] ^ input[ii++];
      let y04 = prev[pi++] ^ input[ii++], y05 = prev[pi++] ^ input[ii++];
      let y06 = prev[pi++] ^ input[ii++], y07 = prev[pi++] ^ input[ii++];
      let y08 = prev[pi++] ^ input[ii++], y09 = prev[pi++] ^ input[ii++];
      let y10 = prev[pi++] ^ input[ii++], y11 = prev[pi++] ^ input[ii++];
      let y12 = prev[pi++] ^ input[ii++], y13 = prev[pi++] ^ input[ii++];
      let y14 = prev[pi++] ^ input[ii++], y15 = prev[pi++] ^ input[ii++];
      // Save state to temporary variables (salsa)
      let x00 = y00, x01 = y01, x02 = y02, x03 = y03, x04 = y04, x05 = y05, x06 = y06, x07 = y07, x08 = y08, x09 = y09, x10 = y10, x11 = y11, x12 = y12, x13 = y13, x14 = y14, x15 = y15;
      // Main loop (salsa)
      for (let i = 0; i < 8; i += 2) {
          x04 ^= rotl$1(x00 + x12 | 0, 7);
          x08 ^= rotl$1(x04 + x00 | 0, 9);
          x12 ^= rotl$1(x08 + x04 | 0, 13);
          x00 ^= rotl$1(x12 + x08 | 0, 18);
          x09 ^= rotl$1(x05 + x01 | 0, 7);
          x13 ^= rotl$1(x09 + x05 | 0, 9);
          x01 ^= rotl$1(x13 + x09 | 0, 13);
          x05 ^= rotl$1(x01 + x13 | 0, 18);
          x14 ^= rotl$1(x10 + x06 | 0, 7);
          x02 ^= rotl$1(x14 + x10 | 0, 9);
          x06 ^= rotl$1(x02 + x14 | 0, 13);
          x10 ^= rotl$1(x06 + x02 | 0, 18);
          x03 ^= rotl$1(x15 + x11 | 0, 7);
          x07 ^= rotl$1(x03 + x15 | 0, 9);
          x11 ^= rotl$1(x07 + x03 | 0, 13);
          x15 ^= rotl$1(x11 + x07 | 0, 18);
          x01 ^= rotl$1(x00 + x03 | 0, 7);
          x02 ^= rotl$1(x01 + x00 | 0, 9);
          x03 ^= rotl$1(x02 + x01 | 0, 13);
          x00 ^= rotl$1(x03 + x02 | 0, 18);
          x06 ^= rotl$1(x05 + x04 | 0, 7);
          x07 ^= rotl$1(x06 + x05 | 0, 9);
          x04 ^= rotl$1(x07 + x06 | 0, 13);
          x05 ^= rotl$1(x04 + x07 | 0, 18);
          x11 ^= rotl$1(x10 + x09 | 0, 7);
          x08 ^= rotl$1(x11 + x10 | 0, 9);
          x09 ^= rotl$1(x08 + x11 | 0, 13);
          x10 ^= rotl$1(x09 + x08 | 0, 18);
          x12 ^= rotl$1(x15 + x14 | 0, 7);
          x13 ^= rotl$1(x12 + x15 | 0, 9);
          x14 ^= rotl$1(x13 + x12 | 0, 13);
          x15 ^= rotl$1(x14 + x13 | 0, 18);
      }
      // Write output (salsa)
      out[oi++] = (y00 + x00) | 0;
      out[oi++] = (y01 + x01) | 0;
      out[oi++] = (y02 + x02) | 0;
      out[oi++] = (y03 + x03) | 0;
      out[oi++] = (y04 + x04) | 0;
      out[oi++] = (y05 + x05) | 0;
      out[oi++] = (y06 + x06) | 0;
      out[oi++] = (y07 + x07) | 0;
      out[oi++] = (y08 + x08) | 0;
      out[oi++] = (y09 + x09) | 0;
      out[oi++] = (y10 + x10) | 0;
      out[oi++] = (y11 + x11) | 0;
      out[oi++] = (y12 + x12) | 0;
      out[oi++] = (y13 + x13) | 0;
      out[oi++] = (y14 + x14) | 0;
      out[oi++] = (y15 + x15) | 0;
  }
  function BlockMix(input, ii, out, oi, r) {
      // The block B is r 128-byte chunks (which is equivalent of 2r 64-byte chunks)
      let head = oi + 0;
      let tail = oi + 16 * r;
      for (let i = 0; i < 16; i++)
          out[tail + i] = input[ii + (2 * r - 1) * 16 + i]; // X ← B[2r−1]
      for (let i = 0; i < r; i++, head += 16, ii += 16) {
          // We write odd & even Yi at same time. Even: 0bXXXXX0 Odd:  0bXXXXX1
          XorAndSalsa(out, tail, input, ii, out, head); // head[i] = Salsa(blockIn[2*i] ^ tail[i-1])
          if (i > 0)
              tail += 16; // First iteration overwrites tmp value in tail
          XorAndSalsa(out, head, input, (ii += 16), out, tail); // tail[i] = Salsa(blockIn[2*i+1] ^ head[i])
      }
  }
  // Common prologue and epilogue for sync/async functions
  function scryptInit(password, salt, _opts) {
      // Maxmem - 1GB+1KB by default
      const opts = checkOpts$1({
          dkLen: 32,
          asyncTick: 10,
          maxmem: 1024 ** 3 + 1024,
      }, _opts);
      const { N, r, p, dkLen, asyncTick, maxmem, onProgress } = opts;
      anumber$3(N, 'N');
      anumber$3(r, 'r');
      anumber$3(p, 'p');
      anumber$3(dkLen, 'dkLen');
      anumber$3(asyncTick, 'asyncTick');
      anumber$3(maxmem, 'maxmem');
      if (onProgress !== undefined && typeof onProgress !== 'function')
          throw new Error('progressCb must be a function');
      const blockSize = 128 * r;
      const blockSize32 = blockSize / 4;
      // Max N is 2^32 (Integrify is 32-bit).
      // Real limit can be 2^22: some JS engines limit Uint8Array to 4GB.
      // Spec check `N >= 2^(blockSize / 8)` is not done for compat with popular libs,
      // which used incorrect r: 1, p: 8. Also, the check seems to be a spec error:
      // https://www.rfc-editor.org/errata_search.php?rfc=7914
      const pow32 = Math.pow(2, 32);
      if (N <= 1 || (N & (N - 1)) !== 0 || N > pow32)
          throw new Error('"N" expected a power of 2, and 2^1 <= N <= 2^32');
      if (p < 1 || p > ((pow32 - 1) * 32) / blockSize)
          throw new Error('"p" expected integer 1..((2^32 - 1) * 32) / (128 * r)');
      if (dkLen < 1 || dkLen > (pow32 - 1) * 32)
          throw new Error('"dkLen" expected integer 1..(2^32 - 1) * 32');
      const memUsed = blockSize * (N + p);
      if (memUsed > maxmem)
          throw new Error('"maxmem" limit was hit, expected 128*r*(N+p) <= "maxmem"=' + maxmem);
      // [B0...Bp−1] ← PBKDF2HMAC-SHA256(Passphrase, Salt, 1, blockSize*ParallelizationFactor)
      // Since it has only one iteration there is no reason to use async variant
      const B = pbkdf2(sha256$3, password, salt, { c: 1, dkLen: blockSize * p });
      const B32 = u32$1(B);
      // Re-used between parallel iterations. Array(iterations) of B
      const V = u32$1(new Uint8Array(blockSize * N));
      const tmp = u32$1(new Uint8Array(blockSize));
      let blockMixCb = () => { };
      if (onProgress) {
          const totalBlockMix = 2 * N * p;
          // Invoke callback if progress changes from 10.01 to 10.02
          // Allows to draw smooth progress bar on up to 8K screen
          const callbackPer = Math.max(Math.floor(totalBlockMix / 10000), 1);
          let blockMixCnt = 0;
          blockMixCb = () => {
              blockMixCnt++;
              if (onProgress && (!(blockMixCnt % callbackPer) || blockMixCnt === totalBlockMix))
                  onProgress(blockMixCnt / totalBlockMix);
          };
      }
      return { N, r, p, dkLen, blockSize32, V, B32, B, tmp, blockMixCb, asyncTick };
  }
  function scryptOutput(password, dkLen, B, V, tmp) {
      const res = pbkdf2(sha256$3, password, B, { c: 1, dkLen });
      clean$2(B, V, tmp);
      return res;
  }
  /**
   * Scrypt KDF from RFC 7914. See {@link ScryptOpts}.
   * @example
   * scrypt('password', 'salt', { N: 2**18, r: 8, p: 1, dkLen: 32 });
   */
  function scrypt(password, salt, opts) {
      const { N, r, p, dkLen, blockSize32, V, B32, B, tmp, blockMixCb } = scryptInit(password, salt, opts);
      swap32IfBE(B32);
      for (let pi = 0; pi < p; pi++) {
          const Pi = blockSize32 * pi;
          for (let i = 0; i < blockSize32; i++)
              V[i] = B32[Pi + i]; // V[0] = B[i]
          for (let i = 0, pos = 0; i < N - 1; i++) {
              BlockMix(V, pos, V, (pos += blockSize32), r); // V[i] = BlockMix(V[i-1]);
              blockMixCb();
          }
          BlockMix(V, (N - 1) * blockSize32, B32, Pi, r); // Process last element
          blockMixCb();
          for (let i = 0; i < N; i++) {
              // First u32 of the last 64-byte block (u32 is LE)
              // & (N - 1) is % N as N is a power of 2, N & (N - 1) = 0 is checked above; >>> 0 for unsigned, input fits in u32
              const j = (B32[Pi + blockSize32 - 16] & (N - 1)) >>> 0; // j = Integrify(X) % iterations
              for (let k = 0; k < blockSize32; k++)
                  tmp[k] = B32[Pi + k] ^ V[j * blockSize32 + k]; // tmp = B ^ V[j]
              BlockMix(tmp, 0, B32, Pi, r); // B = BlockMix(B ^ V[j])
              blockMixCb();
          }
      }
      swap32IfBE(B32);
      return scryptOutput(password, dkLen, B, V, tmp);
  }

  // nip49.ts
  var Bech32MaxSize = 5e3;
  function encodeBech32(prefix, data) {
    let words = bech32.toWords(data);
    return bech32.encode(prefix, words, Bech32MaxSize);
  }
  function encodeBytes(prefix, bytes) {
    return encodeBech32(prefix, bytes);
  }

  // nip49.ts
  function encrypt(sec, password, logn = 16, ksb = 2) {
    let salt = randomBytes$2(16);
    let n = 2 ** logn;
    let key = scrypt(password.normalize("NFKC"), salt, { N: n, r: 8, p: 1, dkLen: 32 });
    let nonce = randomBytes$2(24);
    let aad = Uint8Array.from([ksb]);
    let xc2p1 = xchacha20poly1305(key, nonce, aad);
    let ciphertext = xc2p1.encrypt(sec);
    let b = concatBytes$3(Uint8Array.from([2]), Uint8Array.from([logn]), salt, nonce, aad, ciphertext);
    return encodeBytes("ncryptsec", b);
  }

  /**
   * MILL — cloudkey.js
   * Encryption for cloud-backed key storage ("log in with Google" and friends).
   *
   * Design follows wisp (github.com/barrydeen/wisp), which does this natively on
   * Android. Two factors gate the backup:
   *
   *   1. Access to the cloud account (enforced by the provider, not by us)
   *   2. A short PIN the user sets
   *
   * All the entropy comes from the PIN, which is why the KDF is deliberately
   * slow. The salt is random per blob and stored alongside the ciphertext — see
   * deriveCloudKey for why that differs from wisp.
   *
   * Be honest about the strength, because wisp's own docstring is optimistic.
   * It claims a compromised account still costs an attacker "~weeks of compute".
   * Measured against this implementation (600k PBKDF2-SHA256, ~61ms/attempt on a
   * 2026 laptop core), exhaustive search of the whole PIN space is:
   *
   *   digits   1 core      1000x parallel (PBKDF2-SHA256 is GPU-friendly)
   *   ------   ---------   ------------------------------------------------
   *   4        ~10 min     ~1 second
   *   6        ~17 hours   ~1 minute
   *   8        ~71 days    ~2 hours
   *
   * So a numeric PIN does NOT meaningfully protect the ciphertext. Raising the
   * iteration count or switching to scrypt does not rescue it either — the
   * problem is ~13-27 bits of entropy, not the KDF. The PIN's real job is to stop
   * a casual "their laptop is unlocked / their Drive is open" grab; actual
   * security rests on the cloud account and its 2FA.
   *
   * Consequences, both deliberate:
   *   - `secret` is any string, not just digits, so the UI can offer a passphrase.
   *   - The portable export path uses NIP-49 with an enforced real passphrase,
   *     because an exported file has no cloud account protecting it.
   */


  const KDF_ITERATIONS = 600_000;      // matches wisp


  const BLOB_VERSION = 'mill1';
  const SALT_BYTES   = 16;

  const b64 = {
    enc: bytes => btoa(String.fromCharCode(...bytes)),
    dec: str   => Uint8Array.from(atob(str), c => c.charCodeAt(0)),
  };

  /**
   * Derive the 32-byte wrapping key from the user's PIN and a salt. Returns a
   * Uint8Array(32) suitable for use as a NIP-44 conversation key.
   *
   * DELIBERATE DEVIATION FROM WISP: wisp derives its salt from the Google
   * account id, HMAC-SHA256("wisp-google-backup", sub). That forces the app to
   * obtain a stable account identifier before it can decrypt anything, which on
   * the web means running an ID-token flow purely to read a `sub` claim.
   *
   * A salt is not a secret — its only job is to stop precomputation being shared
   * across users. A random per-blob salt does that just as well, and NIP-49 does
   * exactly this (16 random bytes inside its own payload). Storing the salt in
   * the blob makes it self-describing, removes the ID-token dependency and the
   * extra scope, and gives each backup its own salt rather than one per account.
   */
  async function deriveCloudKey(secret, salt) {
    if (!secret) throw new Error('PIN or passphrase required');
    if (!salt || !salt.length) throw new Error('Salt required');
    const keyMat = await crypto.subtle.importKey(
      'raw', new TextEncoder().encode(String(secret)), 'PBKDF2', false, ['deriveBits'],
    );
    const bits = await crypto.subtle.deriveBits(
      { name: 'PBKDF2', salt, iterations: KDF_ITERATIONS, hash: 'SHA-256' },
      keyMat, 256,
    );
    return new Uint8Array(bits);
  }

  /**
   * Encrypt a private key for cloud storage.
   *
   * NIP-44 v2 is used as a general-purpose AEAD, with the PBKDF2 output
   * substituted for the usual ECDH conversation key. That gives us
   * ChaCha20 + HMAC-SHA256 encrypt-then-MAC and a padded, versioned wire format
   * for free, rather than hand-rolling one. The plaintext is the 32-byte key as
   * hex, matching wisp so the formats stay comparable.
   */
  async function encryptCloudBlob(privHex, secret) {
    const salt = crypto.getRandomValues(new Uint8Array(SALT_BYTES));
    const key  = await deriveCloudKey(secret, salt);
    return `${BLOB_VERSION}:${b64.enc(salt)}:${nip44_exports.v2.encrypt(privHex, key)}`;
  }

  /**
   * Decrypt a cloud blob. Throws on a wrong PIN — NIP-44's MAC check fails
   * before any plaintext is produced, so a bad PIN is indistinguishable from a
   * corrupt or unrelated file, which is what callers want when scanning several
   * backups with one PIN.
   */
  async function decryptCloudBlob(blob, secret) {
    const parts = String(blob).trim().split(':');
    if (parts.length !== 3 || parts[0] !== BLOB_VERSION) {
      throw new Error('Not a mill backup');
    }
    const salt = b64.dec(parts[1]);
    const key  = await deriveCloudKey(secret, salt);
    const hex  = nip44_exports.v2.decrypt(parts[2], key);
    if (!/^[0-9a-f]{64}$/i.test(hex)) throw new Error('Decrypted payload is not a private key');
    return hex.toLowerCase();
  }

  /**
   * Portable export for the "take control of my keys" path.
   *
   * NIP-49 (scrypt + XChaCha20-Poly1305, bech32 `ncryptsec1`) rather than our own
   * format, because the entire point of this path is that another Nostr client
   * can import it. Takes a real passphrase, not the PIN — the PIN's entropy is
   * only defensible behind the provider's access control, and an exported file
   * has no such protection.
   */
  function exportNcryptsec(privHex, passphrase, logN = 16) {
    if (!passphrase || passphrase.length < 8) {
      throw new Error('Use a passphrase of at least 8 characters for an exported key');
    }
    // 0x02 = "key security unknown"; we cannot vouch for how the user stores it.
    return encrypt(hexToBytes$2(privHex), passphrase, logN, 0x02);
  }

  /**
   * MILL — drive.js
   * Google Drive appDataFolder access for cloud-backed keys.
   *
   * Raw Drive REST v3 over fetch — no SDK, keeping mill dependency-free. The
   * appDataFolder is a per-application hidden space: the user can see that an
   * app stores data and can delete it, but cannot browse it, and other apps
   * cannot see it at all.
   *
   * `drive.appdata` is classified NON-SENSITIVE by Google, so it needs no
   * security assessment, no audit and no verification video — the one Drive
   * scope that avoids all of it.
   */


  const API    = 'https://www.googleapis.com/drive/v3';
  const UPLOAD = 'https://www.googleapis.com/upload/drive/v3';

  // Opaque filenames, stolen from wisp's design. If backups were named by npub,
  // anyone with access to the Drive account could map a Google identity to a
  // Nostr identity without ever decrypting anything. The npub is recoverable
  // only by decrypting the contents.
  const PREFIX = 'mill_bk_';
  const SUFFIX = '.bin';

  class DriveAuthError extends Error {
    constructor(msg = 'Drive authorization expired') { super(msg); this.name = 'DriveAuthError'; }
  }

  function newBackupName() {
    const uuid = (crypto.randomUUID?.() || Math.random().toString(36).slice(2) + Date.now().toString(36));
    return `${PREFIX}${uuid}${SUFFIX}`;
  }

  async function driveFetch(url, token, init = {}) {
    const res = await fetch(url, {
      ...init,
      headers: { ...(init.headers || {}), Authorization: `Bearer ${token}` },
    });
    // 401 means the access token lapsed. Surface it as a distinct type so the
    // caller can silently re-request a token and retry exactly once, rather than
    // showing the user an error for something we can fix.
    if (res.status === 401) throw new DriveAuthError();
    if (!res.ok) {
      const body = await res.text().catch(() => '');
      throw new Error(`Drive ${res.status}: ${body.slice(0, 200)}`);
    }
    return res;
  }

  /** List mill backup files in the app data folder, newest first. */
  async function listBackups(token) {
    const params = new URLSearchParams({
      spaces: 'appDataFolder',
      fields: 'files(id,name,modifiedTime,size)',
      orderBy: 'modifiedTime desc',
      pageSize: '100',
      q: `name contains '${PREFIX}' and trashed = false`,
    });
    const res = await driveFetch(`${API}/files?${params}`, token);
    const data = await res.json();
    return (data.files || []).filter(f => f.name?.startsWith(PREFIX));
  }

  /** Fetch one backup's contents as text (the NIP-44 blob). */
  async function downloadBackup(token, fileId) {
    const res = await driveFetch(`${API}/files/${encodeURIComponent(fileId)}?alt=media`, token);
    return res.text();
  }

  /**
   * Write a new backup. Always creates a fresh file rather than updating in
   * place — wisp does the same, to sidestep the delete-then-upload race where a
   * failure between the two steps leaves the user with no backup at all. Callers
   * that are replacing a key should upload first, verify, then delete the old id.
   */
  async function uploadBackup(token, content) {
    const boundary = `mill${Math.random().toString(36).slice(2)}`;
    const metadata = { name: newBackupName(), parents: ['appDataFolder'] };
    const body =
      `--${boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n` +
      `${JSON.stringify(metadata)}\r\n` +
      `--${boundary}\r\nContent-Type: application/octet-stream\r\n\r\n` +
      `${content}\r\n` +
      `--${boundary}--`;

    const res = await driveFetch(`${UPLOAD}/files?uploadType=multipart&fields=id,name`, token, {
      method: 'POST',
      headers: { 'Content-Type': `multipart/related; boundary=${boundary}` },
      body,
    });
    return res.json();
  }

  async function deleteBackup(token, fileId) {
    await driveFetch(`${API}/files/${encodeURIComponent(fileId)}`, token, { method: 'DELETE' });
  }

  /**
   * Run a Drive operation, refreshing the token once if it has expired.
   * `getToken(forceRefresh)` should return a valid access token.
   */
  async function withAuth(getToken, fn) {
    let token = await getToken(false);
    try {
      return await fn(token);
    } catch (e) {
      if (!(e instanceof DriveAuthError)) throw e;
      token = await getToken(true);
      return fn(token);
    }
  }

  /**
   * Internal helpers for blake hash.
   * @module
   */
  /**
   * Internal blake variable.
   * For BLAKE2b, the two extra permutations for rounds 10 and 11 are SIGMA[10..11] = SIGMA[0..1].
   */
  // prettier-ignore
  const BSIGMA = /* @__PURE__ */ Uint8Array.from([
      0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15,
      14, 10, 4, 8, 9, 15, 13, 6, 1, 12, 0, 2, 11, 7, 5, 3,
      11, 8, 12, 0, 5, 2, 15, 13, 10, 14, 3, 6, 7, 1, 9, 4,
      7, 9, 3, 1, 13, 12, 11, 14, 2, 6, 5, 10, 4, 0, 15, 8,
      9, 0, 5, 7, 2, 4, 10, 15, 14, 1, 11, 12, 6, 8, 3, 13,
      2, 12, 6, 10, 0, 11, 8, 3, 4, 13, 7, 5, 15, 14, 1, 9,
      12, 5, 1, 15, 14, 13, 4, 10, 0, 7, 6, 3, 9, 2, 8, 11,
      13, 11, 7, 14, 12, 1, 3, 9, 5, 0, 15, 4, 8, 6, 2, 10,
      6, 15, 14, 9, 11, 3, 0, 8, 12, 2, 13, 7, 1, 4, 10, 5,
      10, 2, 8, 4, 7, 6, 1, 5, 15, 11, 9, 14, 3, 12, 13, 0,
      0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15,
      14, 10, 4, 8, 9, 15, 13, 6, 1, 12, 0, 2, 11, 7, 5, 3,
      // Blake1, unused in others
      11, 8, 12, 0, 5, 2, 15, 13, 10, 14, 3, 6, 7, 1, 9, 4,
      7, 9, 3, 1, 13, 12, 11, 14, 2, 6, 5, 10, 4, 0, 15, 8,
      9, 0, 5, 7, 2, 4, 10, 15, 14, 1, 11, 12, 6, 8, 3, 13,
      2, 12, 6, 10, 0, 11, 8, 3, 4, 13, 7, 5, 15, 14, 1, 9,
  ]);

  /**
   * blake2b (64-bit) & blake2s (8 to 32-bit) hash functions.
   * b could have been faster, but there is no fast u64 in js, so s is 1.5x faster.
   * @module
   */
  // Same as SHA512_IV, but swapped endianness: LE instead of BE. iv[1] is iv[0], etc.
  const B2B_IV = /* @__PURE__ */ Uint32Array.from([
      0xf3bcc908, 0x6a09e667, 0x84caa73b, 0xbb67ae85, 0xfe94f82b, 0x3c6ef372, 0x5f1d36f1, 0xa54ff53a,
      0xade682d1, 0x510e527f, 0x2b3e6c1f, 0x9b05688c, 0xfb41bd6b, 0x1f83d9ab, 0x137e2179, 0x5be0cd19,
  ]);
  // Temporary buffer
  const BBUF = /* @__PURE__ */ new Uint32Array(32);
  // Mixing function G splitted in two halfs
  function G1b(a, b, c, d, msg, x) {
      // NOTE: V is LE here
      const Xl = msg[x], Xh = msg[x + 1]; // prettier-ignore
      let Al = BBUF[2 * a], Ah = BBUF[2 * a + 1]; // prettier-ignore
      let Bl = BBUF[2 * b], Bh = BBUF[2 * b + 1]; // prettier-ignore
      let Cl = BBUF[2 * c], Ch = BBUF[2 * c + 1]; // prettier-ignore
      let Dl = BBUF[2 * d], Dh = BBUF[2 * d + 1]; // prettier-ignore
      // v[a] = (v[a] + v[b] + x) | 0;
      let ll = add3L(Al, Bl, Xl);
      Ah = add3H(ll, Ah, Bh, Xh);
      Al = ll | 0;
      // v[d] = rotr(v[d] ^ v[a], 32)
      ({ Dh, Dl } = { Dh: Dh ^ Ah, Dl: Dl ^ Al });
      ({ Dh, Dl } = { Dh: rotr32H(Dh, Dl), Dl: rotr32L(Dh) });
      // v[c] = (v[c] + v[d]) | 0;
      ({ h: Ch, l: Cl } = add(Ch, Cl, Dh, Dl));
      // v[b] = rotr(v[b] ^ v[c], 24)
      ({ Bh, Bl } = { Bh: Bh ^ Ch, Bl: Bl ^ Cl });
      ({ Bh, Bl } = { Bh: rotrSH(Bh, Bl, 24), Bl: rotrSL(Bh, Bl, 24) });
      ((BBUF[2 * a] = Al), (BBUF[2 * a + 1] = Ah));
      ((BBUF[2 * b] = Bl), (BBUF[2 * b + 1] = Bh));
      ((BBUF[2 * c] = Cl), (BBUF[2 * c + 1] = Ch));
      ((BBUF[2 * d] = Dl), (BBUF[2 * d + 1] = Dh));
  }
  function G2b(a, b, c, d, msg, x) {
      // NOTE: V is LE here
      const Xl = msg[x], Xh = msg[x + 1]; // prettier-ignore
      let Al = BBUF[2 * a], Ah = BBUF[2 * a + 1]; // prettier-ignore
      let Bl = BBUF[2 * b], Bh = BBUF[2 * b + 1]; // prettier-ignore
      let Cl = BBUF[2 * c], Ch = BBUF[2 * c + 1]; // prettier-ignore
      let Dl = BBUF[2 * d], Dh = BBUF[2 * d + 1]; // prettier-ignore
      // v[a] = (v[a] + v[b] + x) | 0;
      let ll = add3L(Al, Bl, Xl);
      Ah = add3H(ll, Ah, Bh, Xh);
      Al = ll | 0;
      // v[d] = rotr(v[d] ^ v[a], 16)
      ({ Dh, Dl } = { Dh: Dh ^ Ah, Dl: Dl ^ Al });
      ({ Dh, Dl } = { Dh: rotrSH(Dh, Dl, 16), Dl: rotrSL(Dh, Dl, 16) });
      // v[c] = (v[c] + v[d]) | 0;
      ({ h: Ch, l: Cl } = add(Ch, Cl, Dh, Dl));
      // v[b] = rotr(v[b] ^ v[c], 63)
      ({ Bh, Bl } = { Bh: Bh ^ Ch, Bl: Bl ^ Cl });
      ({ Bh, Bl } = { Bh: rotrBH(Bh, Bl, 63), Bl: rotrBL(Bh, Bl, 63) });
      ((BBUF[2 * a] = Al), (BBUF[2 * a + 1] = Ah));
      ((BBUF[2 * b] = Bl), (BBUF[2 * b + 1] = Bh));
      ((BBUF[2 * c] = Cl), (BBUF[2 * c + 1] = Ch));
      ((BBUF[2 * d] = Dl), (BBUF[2 * d + 1] = Dh));
  }
  function checkBlake2Opts(outputLen, opts = {}, keyLen, saltLen, persLen) {
      anumber$3(keyLen);
      if (outputLen < 0 || outputLen > keyLen)
          throw new Error('outputLen bigger than keyLen');
      const { key, salt, personalization } = opts;
      if (key !== undefined && (key.length < 1 || key.length > keyLen))
          throw new Error('"key" expected to be undefined or of length=1..' + keyLen);
      if (salt !== undefined)
          abytes$3(salt, saltLen, 'salt');
      if (personalization !== undefined)
          abytes$3(personalization, persLen, 'personalization');
  }
  /** Internal base class for BLAKE2. */
  class _BLAKE2 {
      buffer;
      buffer32;
      finished = false;
      destroyed = false;
      length = 0;
      pos = 0;
      blockLen;
      outputLen;
      constructor(blockLen, outputLen) {
          anumber$3(blockLen);
          anumber$3(outputLen);
          this.blockLen = blockLen;
          this.outputLen = outputLen;
          this.buffer = new Uint8Array(blockLen);
          this.buffer32 = u32$1(this.buffer);
      }
      update(data) {
          aexists$2(this);
          abytes$3(data);
          // Main difference with other hashes: there is flag for last block,
          // so we cannot process current block before we know that there
          // is the next one. This significantly complicates logic and reduces ability
          // to do zero-copy processing
          const { blockLen, buffer, buffer32 } = this;
          const len = data.length;
          const offset = data.byteOffset;
          const buf = data.buffer;
          for (let pos = 0; pos < len;) {
              // If buffer is full and we still have input (don't process last block, same as blake2s)
              if (this.pos === blockLen) {
                  swap32IfBE(buffer32);
                  this.compress(buffer32, 0, false);
                  swap32IfBE(buffer32);
                  this.pos = 0;
              }
              const take = Math.min(blockLen - this.pos, len - pos);
              const dataOffset = offset + pos;
              // full block && aligned to 4 bytes && not last in input
              if (take === blockLen && !(dataOffset % 4) && pos + take < len) {
                  const data32 = new Uint32Array(buf, dataOffset, Math.floor((len - pos) / 4));
                  swap32IfBE(data32);
                  for (let pos32 = 0; pos + blockLen < len; pos32 += buffer32.length, pos += blockLen) {
                      this.length += blockLen;
                      this.compress(data32, pos32, false);
                  }
                  swap32IfBE(data32);
                  continue;
              }
              buffer.set(data.subarray(pos, pos + take), this.pos);
              this.pos += take;
              this.length += take;
              pos += take;
          }
          return this;
      }
      digestInto(out) {
          aexists$2(this);
          aoutput$2(out, this);
          const { pos, buffer32 } = this;
          this.finished = true;
          // Padding
          clean$2(this.buffer.subarray(pos));
          swap32IfBE(buffer32);
          this.compress(buffer32, 0, true);
          swap32IfBE(buffer32);
          const out32 = u32$1(out);
          this.get().forEach((v, i) => (out32[i] = swap8IfBE(v)));
      }
      digest() {
          const { buffer, outputLen } = this;
          this.digestInto(buffer);
          const res = buffer.slice(0, outputLen);
          this.destroy();
          return res;
      }
      _cloneInto(to) {
          const { buffer, length, finished, destroyed, outputLen, pos } = this;
          to ||= new this.constructor({ dkLen: outputLen });
          to.set(...this.get());
          to.buffer.set(buffer);
          to.destroyed = destroyed;
          to.finished = finished;
          to.length = length;
          to.pos = pos;
          // @ts-ignore
          to.outputLen = outputLen;
          return to;
      }
      clone() {
          return this._cloneInto();
      }
  }
  /** Internal blake2b hash class. */
  class _BLAKE2b extends _BLAKE2 {
      // Same as SHA-512, but LE
      v0l = B2B_IV[0] | 0;
      v0h = B2B_IV[1] | 0;
      v1l = B2B_IV[2] | 0;
      v1h = B2B_IV[3] | 0;
      v2l = B2B_IV[4] | 0;
      v2h = B2B_IV[5] | 0;
      v3l = B2B_IV[6] | 0;
      v3h = B2B_IV[7] | 0;
      v4l = B2B_IV[8] | 0;
      v4h = B2B_IV[9] | 0;
      v5l = B2B_IV[10] | 0;
      v5h = B2B_IV[11] | 0;
      v6l = B2B_IV[12] | 0;
      v6h = B2B_IV[13] | 0;
      v7l = B2B_IV[14] | 0;
      v7h = B2B_IV[15] | 0;
      constructor(opts = {}) {
          const olen = opts.dkLen === undefined ? 64 : opts.dkLen;
          super(128, olen);
          checkBlake2Opts(olen, opts, 64, 16, 16);
          let { key, personalization, salt } = opts;
          let keyLength = 0;
          if (key !== undefined) {
              abytes$3(key, undefined, 'key');
              keyLength = key.length;
          }
          this.v0l ^= this.outputLen | (keyLength << 8) | (0x01 << 16) | (0x01 << 24);
          if (salt !== undefined) {
              abytes$3(salt, undefined, 'salt');
              const slt = u32$1(salt);
              this.v4l ^= swap8IfBE(slt[0]);
              this.v4h ^= swap8IfBE(slt[1]);
              this.v5l ^= swap8IfBE(slt[2]);
              this.v5h ^= swap8IfBE(slt[3]);
          }
          if (personalization !== undefined) {
              abytes$3(personalization, undefined, 'personalization');
              const pers = u32$1(personalization);
              this.v6l ^= swap8IfBE(pers[0]);
              this.v6h ^= swap8IfBE(pers[1]);
              this.v7l ^= swap8IfBE(pers[2]);
              this.v7h ^= swap8IfBE(pers[3]);
          }
          if (key !== undefined) {
              // Pad to blockLen and update
              const tmp = new Uint8Array(this.blockLen);
              tmp.set(key);
              this.update(tmp);
          }
      }
      // prettier-ignore
      get() {
          let { v0l, v0h, v1l, v1h, v2l, v2h, v3l, v3h, v4l, v4h, v5l, v5h, v6l, v6h, v7l, v7h } = this;
          return [v0l, v0h, v1l, v1h, v2l, v2h, v3l, v3h, v4l, v4h, v5l, v5h, v6l, v6h, v7l, v7h];
      }
      // prettier-ignore
      set(v0l, v0h, v1l, v1h, v2l, v2h, v3l, v3h, v4l, v4h, v5l, v5h, v6l, v6h, v7l, v7h) {
          this.v0l = v0l | 0;
          this.v0h = v0h | 0;
          this.v1l = v1l | 0;
          this.v1h = v1h | 0;
          this.v2l = v2l | 0;
          this.v2h = v2h | 0;
          this.v3l = v3l | 0;
          this.v3h = v3h | 0;
          this.v4l = v4l | 0;
          this.v4h = v4h | 0;
          this.v5l = v5l | 0;
          this.v5h = v5h | 0;
          this.v6l = v6l | 0;
          this.v6h = v6h | 0;
          this.v7l = v7l | 0;
          this.v7h = v7h | 0;
      }
      compress(msg, offset, isLast) {
          this.get().forEach((v, i) => (BBUF[i] = v)); // First half from state.
          BBUF.set(B2B_IV, 16); // Second half from IV.
          let { h, l } = fromBig(BigInt(this.length));
          BBUF[24] = B2B_IV[8] ^ l; // Low word of the offset.
          BBUF[25] = B2B_IV[9] ^ h; // High word.
          // Invert all bits for last block
          if (isLast) {
              BBUF[28] = ~BBUF[28];
              BBUF[29] = ~BBUF[29];
          }
          let j = 0;
          const s = BSIGMA;
          for (let i = 0; i < 12; i++) {
              G1b(0, 4, 8, 12, msg, offset + 2 * s[j++]);
              G2b(0, 4, 8, 12, msg, offset + 2 * s[j++]);
              G1b(1, 5, 9, 13, msg, offset + 2 * s[j++]);
              G2b(1, 5, 9, 13, msg, offset + 2 * s[j++]);
              G1b(2, 6, 10, 14, msg, offset + 2 * s[j++]);
              G2b(2, 6, 10, 14, msg, offset + 2 * s[j++]);
              G1b(3, 7, 11, 15, msg, offset + 2 * s[j++]);
              G2b(3, 7, 11, 15, msg, offset + 2 * s[j++]);
              G1b(0, 5, 10, 15, msg, offset + 2 * s[j++]);
              G2b(0, 5, 10, 15, msg, offset + 2 * s[j++]);
              G1b(1, 6, 11, 12, msg, offset + 2 * s[j++]);
              G2b(1, 6, 11, 12, msg, offset + 2 * s[j++]);
              G1b(2, 7, 8, 13, msg, offset + 2 * s[j++]);
              G2b(2, 7, 8, 13, msg, offset + 2 * s[j++]);
              G1b(3, 4, 9, 14, msg, offset + 2 * s[j++]);
              G2b(3, 4, 9, 14, msg, offset + 2 * s[j++]);
          }
          this.v0l ^= BBUF[0] ^ BBUF[16];
          this.v0h ^= BBUF[1] ^ BBUF[17];
          this.v1l ^= BBUF[2] ^ BBUF[18];
          this.v1h ^= BBUF[3] ^ BBUF[19];
          this.v2l ^= BBUF[4] ^ BBUF[20];
          this.v2h ^= BBUF[5] ^ BBUF[21];
          this.v3l ^= BBUF[6] ^ BBUF[22];
          this.v3h ^= BBUF[7] ^ BBUF[23];
          this.v4l ^= BBUF[8] ^ BBUF[24];
          this.v4h ^= BBUF[9] ^ BBUF[25];
          this.v5l ^= BBUF[10] ^ BBUF[26];
          this.v5h ^= BBUF[11] ^ BBUF[27];
          this.v6l ^= BBUF[12] ^ BBUF[28];
          this.v6h ^= BBUF[13] ^ BBUF[29];
          this.v7l ^= BBUF[14] ^ BBUF[30];
          this.v7h ^= BBUF[15] ^ BBUF[31];
          clean$2(BBUF);
      }
      destroy() {
          this.destroyed = true;
          clean$2(this.buffer32);
          this.set(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
      }
  }
  /**
   * Blake2b hash function. 64-bit. 1.5x slower than blake2s in JS.
   * @param msg - message that would be hashed
   * @param opts - dkLen output length, key for MAC mode, salt, personalization
   */
  const blake2b = /* @__PURE__ */ createHasher$1((opts) => new _BLAKE2b(opts));

  /**
   * Argon2 KDF from RFC 9106. Can be used to create a key from password and salt.
   * We suggest to use Scrypt. JS Argon is 2-10x slower than native code because of 64-bitness:
   * * argon uses uint64, but JS doesn't have fast uint64array
   * * uint64 multiplication is 1/3 of time
   * * `P` function would be very nice with u64, because most of value will be in registers,
   *   hovewer with u32 it will require 32 registers, which is too much.
   * * JS arrays do slow bound checks, so reading from `A2_BUF` slows it down
   * @module
   */
  const AT = { Argond2d: 0, Argon2i: 1, Argon2id: 2 };
  const ARGON2_SYNC_POINTS = 4;
  const abytesOrZero = (buf, errorTitle = '') => {
      if (buf === undefined)
          return Uint8Array.of();
      return kdfInputToBytes(buf, errorTitle);
  };
  // u32 * u32 = u64
  function mul(a, b) {
      const aL = a & 0xffff;
      const aH = a >>> 16;
      const bL = b & 0xffff;
      const bH = b >>> 16;
      const ll = Math.imul(aL, bL);
      const hl = Math.imul(aH, bL);
      const lh = Math.imul(aL, bH);
      const hh = Math.imul(aH, bH);
      const carry = (ll >>> 16) + (hl & 0xffff) + lh;
      const high = (hh + (hl >>> 16) + (carry >>> 16)) | 0;
      const low = (carry << 16) | (ll & 0xffff);
      return { h: high, l: low };
  }
  function mul2(a, b) {
      // 2 * a * b (via shifts)
      const { h, l } = mul(a, b);
      return { h: ((h << 1) | (l >>> 31)) & 0xffff_ffff, l: (l << 1) & 0xffff_ffff };
  }
  // BlaMka permutation for Argon2
  // A + B + (2 * u32(A) * u32(B))
  function blamka(Ah, Al, Bh, Bl) {
      const { h: Ch, l: Cl } = mul2(Al, Bl);
      // A + B + (2 * A * B)
      const Rll = add3L(Al, Bl, Cl);
      return { h: add3H(Rll, Ah, Bh, Ch), l: Rll | 0 };
  }
  // Temporary block buffer
  const A2_BUF = new Uint32Array(256); // 1024 bytes (matrix 16x16)
  function G$1(a, b, c, d) {
      let Al = A2_BUF[2 * a], Ah = A2_BUF[2 * a + 1]; // prettier-ignore
      let Bl = A2_BUF[2 * b], Bh = A2_BUF[2 * b + 1]; // prettier-ignore
      let Cl = A2_BUF[2 * c], Ch = A2_BUF[2 * c + 1]; // prettier-ignore
      let Dl = A2_BUF[2 * d], Dh = A2_BUF[2 * d + 1]; // prettier-ignore
      ({ h: Ah, l: Al } = blamka(Ah, Al, Bh, Bl));
      ({ Dh, Dl } = { Dh: Dh ^ Ah, Dl: Dl ^ Al });
      ({ Dh, Dl } = { Dh: rotr32H(Dh, Dl), Dl: rotr32L(Dh) });
      ({ h: Ch, l: Cl } = blamka(Ch, Cl, Dh, Dl));
      ({ Bh, Bl } = { Bh: Bh ^ Ch, Bl: Bl ^ Cl });
      ({ Bh, Bl } = { Bh: rotrSH(Bh, Bl, 24), Bl: rotrSL(Bh, Bl, 24) });
      ({ h: Ah, l: Al } = blamka(Ah, Al, Bh, Bl));
      ({ Dh, Dl } = { Dh: Dh ^ Ah, Dl: Dl ^ Al });
      ({ Dh, Dl } = { Dh: rotrSH(Dh, Dl, 16), Dl: rotrSL(Dh, Dl, 16) });
      ({ h: Ch, l: Cl } = blamka(Ch, Cl, Dh, Dl));
      ({ Bh, Bl } = { Bh: Bh ^ Ch, Bl: Bl ^ Cl });
      ({ Bh, Bl } = { Bh: rotrBH(Bh, Bl, 63), Bl: rotrBL(Bh, Bl, 63) });
      ((A2_BUF[2 * a] = Al), (A2_BUF[2 * a + 1] = Ah));
      ((A2_BUF[2 * b] = Bl), (A2_BUF[2 * b + 1] = Bh));
      ((A2_BUF[2 * c] = Cl), (A2_BUF[2 * c + 1] = Ch));
      ((A2_BUF[2 * d] = Dl), (A2_BUF[2 * d + 1] = Dh));
  }
  // prettier-ignore
  function P(v00, v01, v02, v03, v04, v05, v06, v07, v08, v09, v10, v11, v12, v13, v14, v15) {
      G$1(v00, v04, v08, v12);
      G$1(v01, v05, v09, v13);
      G$1(v02, v06, v10, v14);
      G$1(v03, v07, v11, v15);
      G$1(v00, v05, v10, v15);
      G$1(v01, v06, v11, v12);
      G$1(v02, v07, v08, v13);
      G$1(v03, v04, v09, v14);
  }
  function block(x, xPos, yPos, outPos, needXor) {
      for (let i = 0; i < 256; i++)
          A2_BUF[i] = x[xPos + i] ^ x[yPos + i];
      // columns (8)
      for (let i = 0; i < 128; i += 16) {
          // prettier-ignore
          P(i, i + 1, i + 2, i + 3, i + 4, i + 5, i + 6, i + 7, i + 8, i + 9, i + 10, i + 11, i + 12, i + 13, i + 14, i + 15);
      }
      // rows (8)
      for (let i = 0; i < 16; i += 2) {
          // prettier-ignore
          P(i, i + 1, i + 16, i + 17, i + 32, i + 33, i + 48, i + 49, i + 64, i + 65, i + 80, i + 81, i + 96, i + 97, i + 112, i + 113);
      }
      if (needXor)
          for (let i = 0; i < 256; i++)
              x[outPos + i] ^= A2_BUF[i] ^ x[xPos + i] ^ x[yPos + i];
      else
          for (let i = 0; i < 256; i++)
              x[outPos + i] = A2_BUF[i] ^ x[xPos + i] ^ x[yPos + i];
      clean$2(A2_BUF);
  }
  // Variable-Length Hash Function H'
  function Hp(A, dkLen) {
      const A8 = u8(A);
      const T = new Uint32Array(1);
      const T8 = u8(T);
      T[0] = dkLen;
      // Fast path
      if (dkLen <= 64)
          return blake2b.create({ dkLen }).update(T8).update(A8).digest();
      const out = new Uint8Array(dkLen);
      let V = blake2b.create({}).update(T8).update(A8).digest();
      let pos = 0;
      // First block
      out.set(V.subarray(0, 32));
      pos += 32;
      // Rest blocks
      for (; dkLen - pos > 64; pos += 32) {
          const Vh = blake2b.create({}).update(V);
          Vh.digestInto(V);
          Vh.destroy();
          out.set(V.subarray(0, 32), pos);
      }
      // Last block
      out.set(blake2b(V, { dkLen: dkLen - pos }), pos);
      clean$2(V, T);
      return u32$1(out);
  }
  // Used only inside process block!
  function indexAlpha(r, s, laneLen, segmentLen, index, randL, sameLane = false) {
      // This is ugly, but close enough to reference implementation.
      let area;
      if (r === 0) {
          if (s === 0)
              area = index - 1;
          else if (sameLane)
              area = s * segmentLen + index - 1;
          else
              area = s * segmentLen + (index == 0 ? -1 : 0);
      }
      else if (sameLane)
          area = laneLen - segmentLen + index - 1;
      else
          area = laneLen - segmentLen + (index == 0 ? -1 : 0);
      const startPos = r !== 0 && s !== ARGON2_SYNC_POINTS - 1 ? (s + 1) * segmentLen : 0;
      const rel = area - 1 - mul(area, mul(randL, randL).h).h;
      return (startPos + rel) % laneLen;
  }
  const maxUint32 = Math.pow(2, 32);
  function isU32(num) {
      return Number.isSafeInteger(num) && num >= 0 && num < maxUint32;
  }
  function argon2Opts(opts) {
      const merged = {
          version: 0x13,
          dkLen: 32,
          maxmem: maxUint32 - 1,
          asyncTick: 10,
      };
      for (let [k, v] of Object.entries(opts))
          if (v !== undefined)
              merged[k] = v;
      const { dkLen, p, m, t, version, onProgress, asyncTick } = merged;
      if (!isU32(dkLen) || dkLen < 4)
          throw new Error('"dkLen" must be 4..');
      if (!isU32(p) || p < 1 || p >= Math.pow(2, 24))
          throw new Error('"p" must be 1..2^24');
      if (!isU32(m))
          throw new Error('"m" must be 0..2^32');
      if (!isU32(t) || t < 1)
          throw new Error('"t" (iterations) must be 1..2^32');
      if (onProgress !== undefined && typeof onProgress !== 'function')
          throw new Error('"progressCb" must be a function');
      anumber$3(asyncTick, 'asyncTick');
      /*
      Memory size m MUST be an integer number of kibibytes from 8*p to 2^(32)-1. The actual number of blocks is m', which is m rounded down to the nearest multiple of 4*p.
      */
      if (!isU32(m) || m < 8 * p)
          throw new Error('"m" (memory) must be at least 8*p bytes');
      if (version !== 0x10 && version !== 0x13)
          throw new Error('"version" must be 0x10 or 0x13, got ' + version);
      return merged;
  }
  function argon2Init(password, salt, type, opts) {
      password = kdfInputToBytes(password, 'password');
      salt = kdfInputToBytes(salt, 'salt');
      if (!isU32(password.length))
          throw new Error('"password" must be less of length 1..4Gb');
      if (!isU32(salt.length) || salt.length < 8)
          throw new Error('"salt" must be of length 8..4Gb');
      if (!Object.values(AT).includes(type))
          throw new Error('"type" was invalid');
      let { p, dkLen, m, t, version, key, personalization, maxmem, onProgress, asyncTick } = argon2Opts(opts);
      // Validation
      key = abytesOrZero(key, 'key');
      personalization = abytesOrZero(personalization, 'personalization');
      // H_0 = H^(64)(LE32(p) || LE32(T) || LE32(m) || LE32(t) ||
      //       LE32(v) || LE32(y) || LE32(length(P)) || P ||
      //       LE32(length(S)) || S ||  LE32(length(K)) || K ||
      //       LE32(length(X)) || X)
      const h = blake2b.create();
      const BUF = new Uint32Array(1);
      const BUF8 = u8(BUF);
      for (let item of [p, dkLen, m, t, version, type]) {
          BUF[0] = item;
          h.update(BUF8);
      }
      for (let i of [password, salt, key, personalization]) {
          BUF[0] = i.length; // BUF is u32 array, this is valid
          h.update(BUF8).update(i);
      }
      const H0 = new Uint32Array(18);
      const H0_8 = u8(H0);
      h.digestInto(H0_8);
      // 256 u32 = 1024 (BLOCK_SIZE), fills A2_BUF on processing
      // Params
      const lanes = p;
      // m' = 4 * p * floor (m / 4p)
      const mP = 4 * p * Math.floor(m / (ARGON2_SYNC_POINTS * p));
      //q = m' / p columns
      const laneLen = Math.floor(mP / p);
      const segmentLen = Math.floor(laneLen / ARGON2_SYNC_POINTS);
      const memUsed = mP * 256;
      if (!isU32(maxmem) || memUsed > maxmem)
          throw new Error('"maxmem" expected <2**32, got: maxmem=' + maxmem + ', memused=' + memUsed);
      const B = new Uint32Array(memUsed);
      // Fill first blocks
      for (let l = 0; l < p; l++) {
          const i = 256 * laneLen * l;
          // B[i][0] = H'^(1024)(H_0 || LE32(0) || LE32(i))
          H0[17] = l;
          H0[16] = 0;
          B.set(Hp(H0, 1024), i);
          // B[i][1] = H'^(1024)(H_0 || LE32(1) || LE32(i))
          H0[16] = 1;
          B.set(Hp(H0, 1024), i + 256);
      }
      let perBlock = () => { };
      if (onProgress) {
          const totalBlock = t * ARGON2_SYNC_POINTS * p * segmentLen;
          // Invoke callback if progress changes from 10.01 to 10.02
          // Allows to draw smooth progress bar on up to 8K screen
          const callbackPer = Math.max(Math.floor(totalBlock / 10000), 1);
          let blockCnt = 0;
          perBlock = () => {
              blockCnt++;
              if (onProgress && (!(blockCnt % callbackPer) || blockCnt === totalBlock))
                  onProgress(blockCnt / totalBlock);
          };
      }
      clean$2(BUF, H0);
      return { type, mP, p, t, version, B, laneLen, lanes, segmentLen, dkLen, perBlock, asyncTick };
  }
  function argon2Output(B, p, laneLen, dkLen) {
      const B_final = new Uint32Array(256);
      for (let l = 0; l < p; l++)
          for (let j = 0; j < 256; j++)
              B_final[j] ^= B[256 * (laneLen * l + laneLen - 1) + j];
      const res = u8(Hp(B_final, dkLen));
      clean$2(B_final);
      return res;
  }
  function processBlock(B, address, l, r, s, index, laneLen, segmentLen, lanes, offset, prev, dataIndependent, needXor) {
      if (offset % laneLen)
          prev = offset - 1;
      let randL, randH;
      if (dataIndependent) {
          let i128 = index % 128;
          if (i128 === 0) {
              address[256 + 12]++;
              block(address, 256, 2 * 256, 0, false);
              block(address, 0, 2 * 256, 0, false);
          }
          randL = address[2 * i128];
          randH = address[2 * i128 + 1];
      }
      else {
          const T = 256 * prev;
          randL = B[T];
          randH = B[T + 1];
      }
      // address block
      const refLane = r === 0 && s === 0 ? l : randH % lanes;
      const refPos = indexAlpha(r, s, laneLen, segmentLen, index, randL, refLane == l);
      const refBlock = laneLen * refLane + refPos;
      // B[i][j] = G(B[i][j-1], B[l][z])
      block(B, 256 * prev, 256 * refBlock, offset * 256, needXor);
  }
  function argon2(type, password, salt, opts) {
      const { mP, p, t, version, B, laneLen, lanes, segmentLen, dkLen, perBlock } = argon2Init(password, salt, type, opts);
      // Pre-loop setup
      // [address, input, zero_block] format so we can pass single U32 to block function
      const address = new Uint32Array(3 * 256);
      address[256 + 6] = mP;
      address[256 + 8] = t;
      address[256 + 10] = type;
      for (let r = 0; r < t; r++) {
          const needXor = r !== 0 && version === 0x13;
          address[256 + 0] = r;
          for (let s = 0; s < ARGON2_SYNC_POINTS; s++) {
              address[256 + 4] = s;
              const dataIndependent = (r === 0 && s < 2);
              for (let l = 0; l < p; l++) {
                  address[256 + 2] = l;
                  address[256 + 12] = 0;
                  let startPos = 0;
                  if (r === 0 && s === 0) {
                      startPos = 2;
                      if (dataIndependent) {
                          address[256 + 12]++;
                          block(address, 256, 2 * 256, 0, false);
                          block(address, 0, 2 * 256, 0, false);
                      }
                  }
                  // current block postion
                  let offset = l * laneLen + s * segmentLen + startPos;
                  // previous block position
                  let prev = offset % laneLen ? offset - 1 : offset + laneLen - 1;
                  for (let index = startPos; index < segmentLen; index++, offset++, prev++) {
                      perBlock();
                      processBlock(B, address, l, r, s, index, laneLen, segmentLen, lanes, offset, prev, dataIndependent, needXor);
                  }
              }
          }
      }
      clean$2(address);
      return argon2Output(B, p, laneLen, dkLen);
  }
  /** argon2id, combining i+d, the most popular version from RFC 9106 */
  const argon2id = (password, salt, opts) => argon2(AT.Argon2id, password, salt, opts);

  const crypto$2 = typeof globalThis === 'object' && 'crypto' in globalThis ? globalThis.crypto : undefined;

  /**
   * Utilities for hex, bytes, CSPRNG.
   * @module
   */
  /*! noble-hashes - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // We use WebCrypto aka globalThis.crypto, which exists in browsers and node.js 16+.
  // node.js versions earlier than v19 don't declare it in global scope.
  // For node.js, package.json#exports field mapping rewrites import
  // from `crypto` to `cryptoNode`, which imports native module.
  // Makes the utils un-importable in browsers without a bundler.
  // Once node.js 18 is deprecated (2025-04-30), we can just drop the import.
  /** Checks if something is Uint8Array. Be careful: nodejs Buffer will return true. */
  function isBytes(a) {
      return a instanceof Uint8Array || (ArrayBuffer.isView(a) && a.constructor.name === 'Uint8Array');
  }
  /** Asserts something is positive integer. */
  function anumber(n) {
      if (!Number.isSafeInteger(n) || n < 0)
          throw new Error('positive integer expected, got ' + n);
  }
  /** Asserts something is Uint8Array. */
  function abytes(b, ...lengths) {
      if (!isBytes(b))
          throw new Error('Uint8Array expected');
      if (lengths.length > 0 && !lengths.includes(b.length))
          throw new Error('Uint8Array expected of length ' + lengths + ', got length=' + b.length);
  }
  /** Asserts something is hash */
  function ahash(h) {
      if (typeof h !== 'function' || typeof h.create !== 'function')
          throw new Error('Hash should be wrapped by utils.createHasher');
      anumber(h.outputLen);
      anumber(h.blockLen);
  }
  /** Asserts a hash instance has not been destroyed / finished */
  function aexists(instance, checkFinished = true) {
      if (instance.destroyed)
          throw new Error('Hash instance has been destroyed');
      if (checkFinished && instance.finished)
          throw new Error('Hash#digest() has already been called');
  }
  /** Asserts output is properly-sized byte array */
  function aoutput(out, instance) {
      abytes(out);
      const min = instance.outputLen;
      if (out.length < min) {
          throw new Error('digestInto() expects output buffer of length at least ' + min);
      }
  }
  /** Zeroize a byte array. Warning: JS provides no guarantees. */
  function clean(...arrays) {
      for (let i = 0; i < arrays.length; i++) {
          arrays[i].fill(0);
      }
  }
  /** Create DataView of an array for easy byte-level manipulation. */
  function createView$2(arr) {
      return new DataView(arr.buffer, arr.byteOffset, arr.byteLength);
  }
  /** The rotate right (circular right shift) operation for uint32 */
  function rotr$2(word, shift) {
      return (word << (32 - shift)) | (word >>> shift);
  }
  // Built-in hex conversion https://caniuse.com/mdn-javascript_builtins_uint8array_fromhex
  const hasHexBuiltin = /* @__PURE__ */ (() => 
  // @ts-ignore
  typeof Uint8Array.from([]).toHex === 'function' && typeof Uint8Array.fromHex === 'function')();
  // Array where index 0xf0 (240) is mapped to string 'f0'
  const hexes$2 = /* @__PURE__ */ Array.from({ length: 256 }, (_, i) => i.toString(16).padStart(2, '0'));
  /**
   * Convert byte array to hex string. Uses built-in function, when available.
   * @example bytesToHex(Uint8Array.from([0xca, 0xfe, 0x01, 0x23])) // 'cafe0123'
   */
  function bytesToHex$3(bytes) {
      abytes(bytes);
      // @ts-ignore
      if (hasHexBuiltin)
          return bytes.toHex();
      // pre-caching improves the speed 6x
      let hex = '';
      for (let i = 0; i < bytes.length; i++) {
          hex += hexes$2[bytes[i]];
      }
      return hex;
  }
  // We use optimized technique to convert hex string to byte array
  const asciis = { _0: 48, _9: 57, A: 65, F: 70, a: 97, f: 102 };
  function asciiToBase16(ch) {
      if (ch >= asciis._0 && ch <= asciis._9)
          return ch - asciis._0; // '2' => 50-48
      if (ch >= asciis.A && ch <= asciis.F)
          return ch - (asciis.A - 10); // 'B' => 66-(65-10)
      if (ch >= asciis.a && ch <= asciis.f)
          return ch - (asciis.a - 10); // 'b' => 98-(97-10)
      return;
  }
  /**
   * Convert hex string to byte array. Uses built-in function, when available.
   * @example hexToBytes('cafe0123') // Uint8Array.from([0xca, 0xfe, 0x01, 0x23])
   */
  function hexToBytes$1(hex) {
      if (typeof hex !== 'string')
          throw new Error('hex string expected, got ' + typeof hex);
      // @ts-ignore
      if (hasHexBuiltin)
          return Uint8Array.fromHex(hex);
      const hl = hex.length;
      const al = hl / 2;
      if (hl % 2)
          throw new Error('hex string expected, got unpadded hex of length ' + hl);
      const array = new Uint8Array(al);
      for (let ai = 0, hi = 0; ai < al; ai++, hi += 2) {
          const n1 = asciiToBase16(hex.charCodeAt(hi));
          const n2 = asciiToBase16(hex.charCodeAt(hi + 1));
          if (n1 === undefined || n2 === undefined) {
              const char = hex[hi] + hex[hi + 1];
              throw new Error('hex string expected, got non-hex character "' + char + '" at index ' + hi);
          }
          array[ai] = n1 * 16 + n2; // multiply first octet, e.g. 'a3' => 10*16+3 => 160 + 3 => 163
      }
      return array;
  }
  /**
   * Converts string to bytes using UTF8 encoding.
   * @example utf8ToBytes('abc') // Uint8Array.from([97, 98, 99])
   */
  function utf8ToBytes$2(str) {
      if (typeof str !== 'string')
          throw new Error('string expected');
      return new Uint8Array(new TextEncoder().encode(str)); // https://bugzil.la/1681809
  }
  /**
   * Normalizes (non-hex) string or Uint8Array to Uint8Array.
   * Warning: when Uint8Array is passed, it would NOT get copied.
   * Keep in mind for future mutable operations.
   */
  function toBytes$2(data) {
      if (typeof data === 'string')
          data = utf8ToBytes$2(data);
      abytes(data);
      return data;
  }
  /** Copies several Uint8Arrays into one. */
  function concatBytes$2(...arrays) {
      let sum = 0;
      for (let i = 0; i < arrays.length; i++) {
          const a = arrays[i];
          abytes(a);
          sum += a.length;
      }
      const res = new Uint8Array(sum);
      for (let i = 0, pad = 0; i < arrays.length; i++) {
          const a = arrays[i];
          res.set(a, pad);
          pad += a.length;
      }
      return res;
  }
  /** For runtime check if class implements interface */
  let Hash$2 = class Hash {
  };
  /** Wraps hash function, creating an interface on top of it */
  function createHasher(hashCons) {
      const hashC = (msg) => hashCons().update(toBytes$2(msg)).digest();
      const tmp = hashCons();
      hashC.outputLen = tmp.outputLen;
      hashC.blockLen = tmp.blockLen;
      hashC.create = () => hashCons();
      return hashC;
  }
  /** Cryptographically secure PRNG. Uses internal OS-level `crypto.getRandomValues`. */
  function randomBytes$1(bytesLength = 32) {
      if (crypto$2 && typeof crypto$2.getRandomValues === 'function') {
          return crypto$2.getRandomValues(new Uint8Array(bytesLength));
      }
      // Legacy Node.js compatibility
      if (crypto$2 && typeof crypto$2.randomBytes === 'function') {
          return Uint8Array.from(crypto$2.randomBytes(bytesLength));
      }
      throw new Error('crypto.getRandomValues must be defined');
  }

  /**
   * Hex, bytes and number utilities.
   * @module
   */
  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  const _0n$7 = /* @__PURE__ */ BigInt(0);
  const _1n$8 = /* @__PURE__ */ BigInt(1);
  // tmp name until v2
  function _abool2(value, title = '') {
      if (typeof value !== 'boolean') {
          const prefix = title && `"${title}"`;
          throw new Error(prefix + 'expected boolean, got type=' + typeof value);
      }
      return value;
  }
  // tmp name until v2
  /** Asserts something is Uint8Array. */
  function _abytes2(value, length, title = '') {
      const bytes = isBytes(value);
      const len = value?.length;
      const needsLen = length !== undefined;
      if (!bytes || (needsLen && len !== length)) {
          const prefix = title && `"${title}" `;
          const ofLen = needsLen ? ` of length ${length}` : '';
          const got = bytes ? `length=${len}` : `type=${typeof value}`;
          throw new Error(prefix + 'expected Uint8Array' + ofLen + ', got ' + got);
      }
      return value;
  }
  // Used in weierstrass, der
  function numberToHexUnpadded(num) {
      const hex = num.toString(16);
      return hex.length & 1 ? '0' + hex : hex;
  }
  function hexToNumber$1(hex) {
      if (typeof hex !== 'string')
          throw new Error('hex string expected, got ' + typeof hex);
      return hex === '' ? _0n$7 : BigInt('0x' + hex); // Big Endian
  }
  // BE: Big Endian, LE: Little Endian
  function bytesToNumberBE$2(bytes) {
      return hexToNumber$1(bytesToHex$3(bytes));
  }
  function bytesToNumberLE$1(bytes) {
      abytes(bytes);
      return hexToNumber$1(bytesToHex$3(Uint8Array.from(bytes).reverse()));
  }
  function numberToBytesBE$2(n, len) {
      return hexToBytes$1(n.toString(16).padStart(len * 2, '0'));
  }
  function numberToBytesLE$1(n, len) {
      return numberToBytesBE$2(n, len).reverse();
  }
  /**
   * Takes hex string or Uint8Array, converts to Uint8Array.
   * Validates output length.
   * Will throw error for other types.
   * @param title descriptive title for an error e.g. 'secret key'
   * @param hex hex string or Uint8Array
   * @param expectedLength optional, will compare to result array's length
   * @returns
   */
  function ensureBytes$1(title, hex, expectedLength) {
      let res;
      if (typeof hex === 'string') {
          try {
              res = hexToBytes$1(hex);
          }
          catch (e) {
              throw new Error(title + ' must be hex string or Uint8Array, cause: ' + e);
          }
      }
      else if (isBytes(hex)) {
          // Uint8Array.from() instead of hash.slice() because node.js Buffer
          // is instance of Uint8Array, and its slice() creates **mutable** copy
          res = Uint8Array.from(hex);
      }
      else {
          throw new Error(title + ' must be hex string or Uint8Array');
      }
      const len = res.length;
      if (typeof expectedLength === 'number' && len !== expectedLength)
          throw new Error(title + ' of length ' + expectedLength + ' expected, got ' + len);
      return res;
  }
  /**
   * @example utf8ToBytes('abc') // new Uint8Array([97, 98, 99])
   */
  // export const utf8ToBytes: typeof utf8ToBytes_ = utf8ToBytes_;
  /**
   * Converts bytes to string using UTF8 encoding.
   * @example bytesToUtf8(Uint8Array.from([97, 98, 99])) // 'abc'
   */
  // export const bytesToUtf8: typeof bytesToUtf8_ = bytesToUtf8_;
  // Is positive bigint
  const isPosBig = (n) => typeof n === 'bigint' && _0n$7 <= n;
  function inRange(n, min, max) {
      return isPosBig(n) && isPosBig(min) && isPosBig(max) && min <= n && n < max;
  }
  /**
   * Asserts min <= n < max. NOTE: It's < max and not <= max.
   * @example
   * aInRange('x', x, 1n, 256n); // would assume x is in (1n..255n)
   */
  function aInRange(title, n, min, max) {
      // Why min <= n < max and not a (min < n < max) OR b (min <= n <= max)?
      // consider P=256n, min=0n, max=P
      // - a for min=0 would require -1:          `inRange('x', x, -1n, P)`
      // - b would commonly require subtraction:  `inRange('x', x, 0n, P - 1n)`
      // - our way is the cleanest:               `inRange('x', x, 0n, P)
      if (!inRange(n, min, max))
          throw new Error('expected valid ' + title + ': ' + min + ' <= n < ' + max + ', got ' + n);
  }
  // Bit operations
  /**
   * Calculates amount of bits in a bigint.
   * Same as `n.toString(2).length`
   * TODO: merge with nLength in modular
   */
  function bitLen(n) {
      let len;
      for (len = 0; n > _0n$7; n >>= _1n$8, len += 1)
          ;
      return len;
  }
  /**
   * Calculate mask for N bits. Not using ** operator with bigints because of old engines.
   * Same as BigInt(`0b${Array(i).fill('1').join('')}`)
   */
  const bitMask$1 = (n) => (_1n$8 << BigInt(n)) - _1n$8;
  /**
   * Minimal HMAC-DRBG from NIST 800-90 for RFC6979 sigs.
   * @returns function that will call DRBG until 2nd arg returns something meaningful
   * @example
   *   const drbg = createHmacDRBG<Key>(32, 32, hmac);
   *   drbg(seed, bytesToKey); // bytesToKey must return Key or undefined
   */
  function createHmacDrbg$1(hashLen, qByteLen, hmacFn) {
      if (typeof hashLen !== 'number' || hashLen < 2)
          throw new Error('hashLen must be a number');
      if (typeof qByteLen !== 'number' || qByteLen < 2)
          throw new Error('qByteLen must be a number');
      if (typeof hmacFn !== 'function')
          throw new Error('hmacFn must be a function');
      // Step B, Step C: set hashLen to 8*ceil(hlen/8)
      const u8n = (len) => new Uint8Array(len); // creates Uint8Array
      const u8of = (byte) => Uint8Array.of(byte); // another shortcut
      let v = u8n(hashLen); // Minimal non-full-spec HMAC-DRBG from NIST 800-90 for RFC6979 sigs.
      let k = u8n(hashLen); // Steps B and C of RFC6979 3.2: set hashLen, in our case always same
      let i = 0; // Iterations counter, will throw when over 1000
      const reset = () => {
          v.fill(1);
          k.fill(0);
          i = 0;
      };
      const h = (...b) => hmacFn(k, v, ...b); // hmac(k)(v, ...values)
      const reseed = (seed = u8n(0)) => {
          // HMAC-DRBG reseed() function. Steps D-G
          k = h(u8of(0x00), seed); // k = hmac(k || v || 0x00 || seed)
          v = h(); // v = hmac(k || v)
          if (seed.length === 0)
              return;
          k = h(u8of(0x01), seed); // k = hmac(k || v || 0x01 || seed)
          v = h(); // v = hmac(k || v)
      };
      const gen = () => {
          // HMAC-DRBG generate() function
          if (i++ >= 1000)
              throw new Error('drbg: tried 1000 values');
          let len = 0;
          const out = [];
          while (len < qByteLen) {
              v = h();
              const sl = v.slice();
              out.push(sl);
              len += v.length;
          }
          return concatBytes$2(...out);
      };
      const genUntil = (seed, pred) => {
          reset();
          reseed(seed); // Steps D-G
          let res = undefined; // Step H: grind until k is in [1..n-1]
          while (!(res = pred(gen())))
              reseed();
          reset();
          return res;
      };
      return genUntil;
  }
  function _validateObject(object, fields, optFields = {}) {
      if (!object || typeof object !== 'object')
          throw new Error('expected valid options object');
      function checkField(fieldName, expectedType, isOpt) {
          const val = object[fieldName];
          if (isOpt && val === undefined)
              return;
          const current = typeof val;
          if (current !== expectedType || val === null)
              throw new Error(`param "${fieldName}" is invalid: expected ${expectedType}, got ${current}`);
      }
      Object.entries(fields).forEach(([k, v]) => checkField(k, v, false));
      Object.entries(optFields).forEach(([k, v]) => checkField(k, v, true));
  }
  /**
   * Memoizes (caches) computation result.
   * Uses WeakMap: the value is going auto-cleaned by GC after last reference is removed.
   */
  function memoized(fn) {
      const map = new WeakMap();
      return (arg, ...args) => {
          const val = map.get(arg);
          if (val !== undefined)
              return val;
          const computed = fn(arg, ...args);
          map.set(arg, computed);
          return computed;
      };
  }

  /**
   * Deprecated module: moved from curves/abstract/utils.js to curves/utils.js
   * @module
   */
  /** @deprecated moved to `@noble/curves/utils.js` */
  const bytesToHex$2 = bytesToHex$3;
  /** @deprecated moved to `@noble/curves/utils.js` */
  const bytesToNumberBE$1 = bytesToNumberBE$2;
  /** @deprecated moved to `@noble/curves/utils.js` */
  const numberToBytesBE$1 = numberToBytesBE$2;

  /**
   * Internal Merkle-Damgard hash utils.
   * @module
   */
  /** Polyfill for Safari 14. https://caniuse.com/mdn-javascript_builtins_dataview_setbiguint64 */
  function setBigUint64$2(view, byteOffset, value, isLE) {
      if (typeof view.setBigUint64 === 'function')
          return view.setBigUint64(byteOffset, value, isLE);
      const _32n = BigInt(32);
      const _u32_max = BigInt(0xffffffff);
      const wh = Number((value >> _32n) & _u32_max);
      const wl = Number(value & _u32_max);
      const h = isLE ? 4 : 0;
      const l = isLE ? 0 : 4;
      view.setUint32(byteOffset + h, wh, isLE);
      view.setUint32(byteOffset + l, wl, isLE);
  }
  /** Choice: a ? b : c */
  function Chi$2(a, b, c) {
      return (a & b) ^ (~a & c);
  }
  /** Majority function, true if any two inputs is true. */
  function Maj$2(a, b, c) {
      return (a & b) ^ (a & c) ^ (b & c);
  }
  /**
   * Merkle-Damgard hash construction base class.
   * Could be used to create MD5, RIPEMD, SHA1, SHA2.
   */
  class HashMD extends Hash$2 {
      constructor(blockLen, outputLen, padOffset, isLE) {
          super();
          this.finished = false;
          this.length = 0;
          this.pos = 0;
          this.destroyed = false;
          this.blockLen = blockLen;
          this.outputLen = outputLen;
          this.padOffset = padOffset;
          this.isLE = isLE;
          this.buffer = new Uint8Array(blockLen);
          this.view = createView$2(this.buffer);
      }
      update(data) {
          aexists(this);
          data = toBytes$2(data);
          abytes(data);
          const { view, buffer, blockLen } = this;
          const len = data.length;
          for (let pos = 0; pos < len;) {
              const take = Math.min(blockLen - this.pos, len - pos);
              // Fast path: we have at least one block in input, cast it to view and process
              if (take === blockLen) {
                  const dataView = createView$2(data);
                  for (; blockLen <= len - pos; pos += blockLen)
                      this.process(dataView, pos);
                  continue;
              }
              buffer.set(data.subarray(pos, pos + take), this.pos);
              this.pos += take;
              pos += take;
              if (this.pos === blockLen) {
                  this.process(view, 0);
                  this.pos = 0;
              }
          }
          this.length += data.length;
          this.roundClean();
          return this;
      }
      digestInto(out) {
          aexists(this);
          aoutput(out, this);
          this.finished = true;
          // Padding
          // We can avoid allocation of buffer for padding completely if it
          // was previously not allocated here. But it won't change performance.
          const { buffer, view, blockLen, isLE } = this;
          let { pos } = this;
          // append the bit '1' to the message
          buffer[pos++] = 0b10000000;
          clean(this.buffer.subarray(pos));
          // we have less than padOffset left in buffer, so we cannot put length in
          // current block, need process it and pad again
          if (this.padOffset > blockLen - pos) {
              this.process(view, 0);
              pos = 0;
          }
          // Pad until full block byte with zeros
          for (let i = pos; i < blockLen; i++)
              buffer[i] = 0;
          // Note: sha512 requires length to be 128bit integer, but length in JS will overflow before that
          // You need to write around 2 exabytes (u64_max / 8 / (1024**6)) for this to happen.
          // So we just write lowest 64 bits of that value.
          setBigUint64$2(view, blockLen - 8, BigInt(this.length * 8), isLE);
          this.process(view, 0);
          const oview = createView$2(out);
          const len = this.outputLen;
          // NOTE: we do division by 4 later, which should be fused in single op with modulo by JIT
          if (len % 4)
              throw new Error('_sha2: outputLen should be aligned to 32bit');
          const outLen = len / 4;
          const state = this.get();
          if (outLen > state.length)
              throw new Error('_sha2: outputLen bigger than state');
          for (let i = 0; i < outLen; i++)
              oview.setUint32(4 * i, state[i], isLE);
      }
      digest() {
          const { buffer, outputLen } = this;
          this.digestInto(buffer);
          const res = buffer.slice(0, outputLen);
          this.destroy();
          return res;
      }
      _cloneInto(to) {
          to || (to = new this.constructor());
          to.set(...this.get());
          const { blockLen, buffer, length, finished, destroyed, pos } = this;
          to.destroyed = destroyed;
          to.finished = finished;
          to.length = length;
          to.pos = pos;
          if (length % blockLen)
              to.buffer.set(buffer);
          return to;
      }
      clone() {
          return this._cloneInto();
      }
  }
  /**
   * Initial SHA-2 state: fractional parts of square roots of first 16 primes 2..53.
   * Check out `test/misc/sha2-gen-iv.js` for recomputation guide.
   */
  /** Initial SHA256 state. Bits 0..32 of frac part of sqrt of primes 2..19 */
  const SHA256_IV = /* @__PURE__ */ Uint32Array.from([
      0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19,
  ]);

  /**
   * SHA2 hash function. A.k.a. sha256, sha384, sha512, sha512_224, sha512_256.
   * SHA256 is the fastest hash implementable in JS, even faster than Blake3.
   * Check out [RFC 4634](https://datatracker.ietf.org/doc/html/rfc4634) and
   * [FIPS 180-4](https://nvlpubs.nist.gov/nistpubs/FIPS/NIST.FIPS.180-4.pdf).
   * @module
   */
  /**
   * Round constants:
   * First 32 bits of fractional parts of the cube roots of the first 64 primes 2..311)
   */
  // prettier-ignore
  const SHA256_K$2 = /* @__PURE__ */ Uint32Array.from([
      0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
      0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
      0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
      0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
      0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
      0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
      0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
      0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
  ]);
  /** Reusable temporary buffer. "W" comes straight from spec. */
  const SHA256_W$2 = /* @__PURE__ */ new Uint32Array(64);
  let SHA256$2 = class SHA256 extends HashMD {
      constructor(outputLen = 32) {
          super(64, outputLen, 8, false);
          // We cannot use array here since array allows indexing by variable
          // which means optimizer/compiler cannot use registers.
          this.A = SHA256_IV[0] | 0;
          this.B = SHA256_IV[1] | 0;
          this.C = SHA256_IV[2] | 0;
          this.D = SHA256_IV[3] | 0;
          this.E = SHA256_IV[4] | 0;
          this.F = SHA256_IV[5] | 0;
          this.G = SHA256_IV[6] | 0;
          this.H = SHA256_IV[7] | 0;
      }
      get() {
          const { A, B, C, D, E, F, G, H } = this;
          return [A, B, C, D, E, F, G, H];
      }
      // prettier-ignore
      set(A, B, C, D, E, F, G, H) {
          this.A = A | 0;
          this.B = B | 0;
          this.C = C | 0;
          this.D = D | 0;
          this.E = E | 0;
          this.F = F | 0;
          this.G = G | 0;
          this.H = H | 0;
      }
      process(view, offset) {
          // Extend the first 16 words into the remaining 48 words w[16..63] of the message schedule array
          for (let i = 0; i < 16; i++, offset += 4)
              SHA256_W$2[i] = view.getUint32(offset, false);
          for (let i = 16; i < 64; i++) {
              const W15 = SHA256_W$2[i - 15];
              const W2 = SHA256_W$2[i - 2];
              const s0 = rotr$2(W15, 7) ^ rotr$2(W15, 18) ^ (W15 >>> 3);
              const s1 = rotr$2(W2, 17) ^ rotr$2(W2, 19) ^ (W2 >>> 10);
              SHA256_W$2[i] = (s1 + SHA256_W$2[i - 7] + s0 + SHA256_W$2[i - 16]) | 0;
          }
          // Compression function main loop, 64 rounds
          let { A, B, C, D, E, F, G, H } = this;
          for (let i = 0; i < 64; i++) {
              const sigma1 = rotr$2(E, 6) ^ rotr$2(E, 11) ^ rotr$2(E, 25);
              const T1 = (H + sigma1 + Chi$2(E, F, G) + SHA256_K$2[i] + SHA256_W$2[i]) | 0;
              const sigma0 = rotr$2(A, 2) ^ rotr$2(A, 13) ^ rotr$2(A, 22);
              const T2 = (sigma0 + Maj$2(A, B, C)) | 0;
              H = G;
              G = F;
              F = E;
              E = (D + T1) | 0;
              D = C;
              C = B;
              B = A;
              A = (T1 + T2) | 0;
          }
          // Add the compressed chunk to the current hash value
          A = (A + this.A) | 0;
          B = (B + this.B) | 0;
          C = (C + this.C) | 0;
          D = (D + this.D) | 0;
          E = (E + this.E) | 0;
          F = (F + this.F) | 0;
          G = (G + this.G) | 0;
          H = (H + this.H) | 0;
          this.set(A, B, C, D, E, F, G, H);
      }
      roundClean() {
          clean(SHA256_W$2);
      }
      destroy() {
          this.set(0, 0, 0, 0, 0, 0, 0, 0);
          clean(this.buffer);
      }
  };
  /**
   * SHA2-256 hash function from RFC 4634.
   *
   * It is the fastest JS hash, even faster than Blake3.
   * To break sha256 using birthday attack, attackers need to try 2^128 hashes.
   * BTC network is doing 2^70 hashes/sec (2^95 hashes/year) as per 2025.
   */
  const sha256$2 = /* @__PURE__ */ createHasher(() => new SHA256$2());

  /**
   * HMAC: RFC2104 message authentication code.
   * @module
   */
  let HMAC$1 = class HMAC extends Hash$2 {
      constructor(hash, _key) {
          super();
          this.finished = false;
          this.destroyed = false;
          ahash(hash);
          const key = toBytes$2(_key);
          this.iHash = hash.create();
          if (typeof this.iHash.update !== 'function')
              throw new Error('Expected instance of class which extends utils.Hash');
          this.blockLen = this.iHash.blockLen;
          this.outputLen = this.iHash.outputLen;
          const blockLen = this.blockLen;
          const pad = new Uint8Array(blockLen);
          // blockLen can be bigger than outputLen
          pad.set(key.length > blockLen ? hash.create().update(key).digest() : key);
          for (let i = 0; i < pad.length; i++)
              pad[i] ^= 0x36;
          this.iHash.update(pad);
          // By doing update (processing of first block) of outer hash here we can re-use it between multiple calls via clone
          this.oHash = hash.create();
          // Undo internal XOR && apply outer XOR
          for (let i = 0; i < pad.length; i++)
              pad[i] ^= 0x36 ^ 0x5c;
          this.oHash.update(pad);
          clean(pad);
      }
      update(buf) {
          aexists(this);
          this.iHash.update(buf);
          return this;
      }
      digestInto(out) {
          aexists(this);
          abytes(out, this.outputLen);
          this.finished = true;
          this.iHash.digestInto(out);
          this.oHash.update(out);
          this.oHash.digestInto(out);
          this.destroy();
      }
      digest() {
          const out = new Uint8Array(this.oHash.outputLen);
          this.digestInto(out);
          return out;
      }
      _cloneInto(to) {
          // Create new instance without calling constructor since key already in state and we don't know it.
          to || (to = Object.create(Object.getPrototypeOf(this), {}));
          const { oHash, iHash, finished, destroyed, blockLen, outputLen } = this;
          to = to;
          to.finished = finished;
          to.destroyed = destroyed;
          to.blockLen = blockLen;
          to.outputLen = outputLen;
          to.oHash = oHash._cloneInto(to.oHash);
          to.iHash = iHash._cloneInto(to.iHash);
          return to;
      }
      clone() {
          return this._cloneInto();
      }
      destroy() {
          this.destroyed = true;
          this.oHash.destroy();
          this.iHash.destroy();
      }
  };
  /**
   * HMAC: RFC2104 message authentication code.
   * @param hash - function that would be used e.g. sha256
   * @param key - message key
   * @param message - message data
   * @example
   * import { hmac } from '@noble/hashes/hmac';
   * import { sha256 } from '@noble/hashes/sha2';
   * const mac1 = hmac(sha256, 'key', 'message');
   */
  const hmac$1 = (hash, key, message) => new HMAC$1(hash, key).update(message).digest();
  hmac$1.create = (hash, key) => new HMAC$1(hash, key);

  /**
   * Utils for modular division and fields.
   * Field over 11 is a finite (Galois) field is integer number operations `mod 11`.
   * There is no division: it is replaced by modular multiplicative inverse.
   * @module
   */
  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // prettier-ignore
  const _0n$6 = BigInt(0), _1n$7 = BigInt(1), _2n$5 = /* @__PURE__ */ BigInt(2), _3n$3 = /* @__PURE__ */ BigInt(3);
  // prettier-ignore
  const _4n$2 = /* @__PURE__ */ BigInt(4), _5n$1 = /* @__PURE__ */ BigInt(5), _7n = /* @__PURE__ */ BigInt(7);
  // prettier-ignore
  const _8n$1 = /* @__PURE__ */ BigInt(8), _9n = /* @__PURE__ */ BigInt(9), _16n = /* @__PURE__ */ BigInt(16);
  // Calculates a modulo b
  function mod$2(a, b) {
      const result = a % b;
      return result >= _0n$6 ? result : b + result;
  }
  /** Does `x^(2^power)` mod p. `pow2(30, 4)` == `30^(2^4)` */
  function pow2$1(x, power, modulo) {
      let res = x;
      while (power-- > _0n$6) {
          res *= res;
          res %= modulo;
      }
      return res;
  }
  /**
   * Inverses number over modulo.
   * Implemented using [Euclidean GCD](https://brilliant.org/wiki/extended-euclidean-algorithm/).
   */
  function invert$2(number, modulo) {
      if (number === _0n$6)
          throw new Error('invert: expected non-zero number');
      if (modulo <= _0n$6)
          throw new Error('invert: expected positive modulus, got ' + modulo);
      // Fermat's little theorem "CT-like" version inv(n) = n^(m-2) mod m is 30x slower.
      let a = mod$2(number, modulo);
      let b = modulo;
      // prettier-ignore
      let x = _0n$6, u = _1n$7;
      while (a !== _0n$6) {
          // JIT applies optimization if those two lines follow each other
          const q = b / a;
          const r = b % a;
          const m = x - u * q;
          // prettier-ignore
          b = a, a = r, x = u, u = m;
      }
      const gcd = b;
      if (gcd !== _1n$7)
          throw new Error('invert: does not exist');
      return mod$2(x, modulo);
  }
  function assertIsSquare(Fp, root, n) {
      if (!Fp.eql(Fp.sqr(root), n))
          throw new Error('Cannot find square root');
  }
  // Not all roots are possible! Example which will throw:
  // const NUM =
  // n = 72057594037927816n;
  // Fp = Field(BigInt('0x1a0111ea397fe69a4b1ba7b6434bacd764774b84f38512bf6730d2a0f6b0f6241eabfffeb153ffffb9feffffffffaaab'));
  function sqrt3mod4(Fp, n) {
      const p1div4 = (Fp.ORDER + _1n$7) / _4n$2;
      const root = Fp.pow(n, p1div4);
      assertIsSquare(Fp, root, n);
      return root;
  }
  function sqrt5mod8(Fp, n) {
      const p5div8 = (Fp.ORDER - _5n$1) / _8n$1;
      const n2 = Fp.mul(n, _2n$5);
      const v = Fp.pow(n2, p5div8);
      const nv = Fp.mul(n, v);
      const i = Fp.mul(Fp.mul(nv, _2n$5), v);
      const root = Fp.mul(nv, Fp.sub(i, Fp.ONE));
      assertIsSquare(Fp, root, n);
      return root;
  }
  // Based on RFC9380, Kong algorithm
  // prettier-ignore
  function sqrt9mod16(P) {
      const Fp_ = Field$1(P);
      const tn = tonelliShanks$1(P);
      const c1 = tn(Fp_, Fp_.neg(Fp_.ONE)); //  1. c1 = sqrt(-1) in F, i.e., (c1^2) == -1 in F
      const c2 = tn(Fp_, c1); //  2. c2 = sqrt(c1) in F, i.e., (c2^2) == c1 in F
      const c3 = tn(Fp_, Fp_.neg(c1)); //  3. c3 = sqrt(-c1) in F, i.e., (c3^2) == -c1 in F
      const c4 = (P + _7n) / _16n; //  4. c4 = (q + 7) / 16        # Integer arithmetic
      return (Fp, n) => {
          let tv1 = Fp.pow(n, c4); //  1. tv1 = x^c4
          let tv2 = Fp.mul(tv1, c1); //  2. tv2 = c1 * tv1
          const tv3 = Fp.mul(tv1, c2); //  3. tv3 = c2 * tv1
          const tv4 = Fp.mul(tv1, c3); //  4. tv4 = c3 * tv1
          const e1 = Fp.eql(Fp.sqr(tv2), n); //  5.  e1 = (tv2^2) == x
          const e2 = Fp.eql(Fp.sqr(tv3), n); //  6.  e2 = (tv3^2) == x
          tv1 = Fp.cmov(tv1, tv2, e1); //  7. tv1 = CMOV(tv1, tv2, e1)  # Select tv2 if (tv2^2) == x
          tv2 = Fp.cmov(tv4, tv3, e2); //  8. tv2 = CMOV(tv4, tv3, e2)  # Select tv3 if (tv3^2) == x
          const e3 = Fp.eql(Fp.sqr(tv2), n); //  9.  e3 = (tv2^2) == x
          const root = Fp.cmov(tv1, tv2, e3); // 10.  z = CMOV(tv1, tv2, e3)   # Select sqrt from tv1 & tv2
          assertIsSquare(Fp, root, n);
          return root;
      };
  }
  /**
   * Tonelli-Shanks square root search algorithm.
   * 1. https://eprint.iacr.org/2012/685.pdf (page 12)
   * 2. Square Roots from 1; 24, 51, 10 to Dan Shanks
   * @param P field order
   * @returns function that takes field Fp (created from P) and number n
   */
  function tonelliShanks$1(P) {
      // Initialization (precomputation).
      // Caching initialization could boost perf by 7%.
      if (P < _3n$3)
          throw new Error('sqrt is not defined for small field');
      // Factor P - 1 = Q * 2^S, where Q is odd
      let Q = P - _1n$7;
      let S = 0;
      while (Q % _2n$5 === _0n$6) {
          Q /= _2n$5;
          S++;
      }
      // Find the first quadratic non-residue Z >= 2
      let Z = _2n$5;
      const _Fp = Field$1(P);
      while (FpLegendre(_Fp, Z) === 1) {
          // Basic primality test for P. After x iterations, chance of
          // not finding quadratic non-residue is 2^x, so 2^1000.
          if (Z++ > 1000)
              throw new Error('Cannot find square root: probably non-prime P');
      }
      // Fast-path; usually done before Z, but we do "primality test".
      if (S === 1)
          return sqrt3mod4;
      // Slow-path
      // TODO: test on Fp2 and others
      let cc = _Fp.pow(Z, Q); // c = z^Q
      const Q1div2 = (Q + _1n$7) / _2n$5;
      return function tonelliSlow(Fp, n) {
          if (Fp.is0(n))
              return n;
          // Check if n is a quadratic residue using Legendre symbol
          if (FpLegendre(Fp, n) !== 1)
              throw new Error('Cannot find square root');
          // Initialize variables for the main loop
          let M = S;
          let c = Fp.mul(Fp.ONE, cc); // c = z^Q, move cc from field _Fp into field Fp
          let t = Fp.pow(n, Q); // t = n^Q, first guess at the fudge factor
          let R = Fp.pow(n, Q1div2); // R = n^((Q+1)/2), first guess at the square root
          // Main loop
          // while t != 1
          while (!Fp.eql(t, Fp.ONE)) {
              if (Fp.is0(t))
                  return Fp.ZERO; // if t=0 return R=0
              let i = 1;
              // Find the smallest i >= 1 such that t^(2^i) ≡ 1 (mod P)
              let t_tmp = Fp.sqr(t); // t^(2^1)
              while (!Fp.eql(t_tmp, Fp.ONE)) {
                  i++;
                  t_tmp = Fp.sqr(t_tmp); // t^(2^2)...
                  if (i === M)
                      throw new Error('Cannot find square root');
              }
              // Calculate the exponent for b: 2^(M - i - 1)
              const exponent = _1n$7 << BigInt(M - i - 1); // bigint is important
              const b = Fp.pow(c, exponent); // b = 2^(M - i - 1)
              // Update variables
              M = i;
              c = Fp.sqr(b); // c = b^2
              t = Fp.mul(t, c); // t = (t * b^2)
              R = Fp.mul(R, b); // R = R*b
          }
          return R;
      };
  }
  /**
   * Square root for a finite field. Will try optimized versions first:
   *
   * 1. P ≡ 3 (mod 4)
   * 2. P ≡ 5 (mod 8)
   * 3. P ≡ 9 (mod 16)
   * 4. Tonelli-Shanks algorithm
   *
   * Different algorithms can give different roots, it is up to user to decide which one they want.
   * For example there is FpSqrtOdd/FpSqrtEven to choice root based on oddness (used for hash-to-curve).
   */
  function FpSqrt$1(P) {
      // P ≡ 3 (mod 4) => √n = n^((P+1)/4)
      if (P % _4n$2 === _3n$3)
          return sqrt3mod4;
      // P ≡ 5 (mod 8) => Atkin algorithm, page 10 of https://eprint.iacr.org/2012/685.pdf
      if (P % _8n$1 === _5n$1)
          return sqrt5mod8;
      // P ≡ 9 (mod 16) => Kong algorithm, page 11 of https://eprint.iacr.org/2012/685.pdf (algorithm 4)
      if (P % _16n === _9n)
          return sqrt9mod16(P);
      // Tonelli-Shanks algorithm
      return tonelliShanks$1(P);
  }
  // prettier-ignore
  const FIELD_FIELDS$1 = [
      'create', 'isValid', 'is0', 'neg', 'inv', 'sqrt', 'sqr',
      'eql', 'add', 'sub', 'mul', 'pow', 'div',
      'addN', 'subN', 'mulN', 'sqrN'
  ];
  function validateField$1(field) {
      const initial = {
          ORDER: 'bigint',
          MASK: 'bigint',
          BYTES: 'number',
          BITS: 'number',
      };
      const opts = FIELD_FIELDS$1.reduce((map, val) => {
          map[val] = 'function';
          return map;
      }, initial);
      _validateObject(field, opts);
      // const max = 16384;
      // if (field.BYTES < 1 || field.BYTES > max) throw new Error('invalid field');
      // if (field.BITS < 1 || field.BITS > 8 * max) throw new Error('invalid field');
      return field;
  }
  // Generic field functions
  /**
   * Same as `pow` but for Fp: non-constant-time.
   * Unsafe in some contexts: uses ladder, so can expose bigint bits.
   */
  function FpPow$1(Fp, num, power) {
      if (power < _0n$6)
          throw new Error('invalid exponent, negatives unsupported');
      if (power === _0n$6)
          return Fp.ONE;
      if (power === _1n$7)
          return num;
      let p = Fp.ONE;
      let d = num;
      while (power > _0n$6) {
          if (power & _1n$7)
              p = Fp.mul(p, d);
          d = Fp.sqr(d);
          power >>= _1n$7;
      }
      return p;
  }
  /**
   * Efficiently invert an array of Field elements.
   * Exception-free. Will return `undefined` for 0 elements.
   * @param passZero map 0 to 0 (instead of undefined)
   */
  function FpInvertBatch$1(Fp, nums, passZero = false) {
      const inverted = new Array(nums.length).fill(passZero ? Fp.ZERO : undefined);
      // Walk from first to last, multiply them by each other MOD p
      const multipliedAcc = nums.reduce((acc, num, i) => {
          if (Fp.is0(num))
              return acc;
          inverted[i] = acc;
          return Fp.mul(acc, num);
      }, Fp.ONE);
      // Invert last element
      const invertedAcc = Fp.inv(multipliedAcc);
      // Walk from last to first, multiply them by inverted each other MOD p
      nums.reduceRight((acc, num, i) => {
          if (Fp.is0(num))
              return acc;
          inverted[i] = Fp.mul(acc, inverted[i]);
          return Fp.mul(acc, num);
      }, invertedAcc);
      return inverted;
  }
  /**
   * Legendre symbol.
   * Legendre constant is used to calculate Legendre symbol (a | p)
   * which denotes the value of a^((p-1)/2) (mod p).
   *
   * * (a | p) ≡ 1    if a is a square (mod p), quadratic residue
   * * (a | p) ≡ -1   if a is not a square (mod p), quadratic non residue
   * * (a | p) ≡ 0    if a ≡ 0 (mod p)
   */
  function FpLegendre(Fp, n) {
      // We can use 3rd argument as optional cache of this value
      // but seems unneeded for now. The operation is very fast.
      const p1mod2 = (Fp.ORDER - _1n$7) / _2n$5;
      const powered = Fp.pow(n, p1mod2);
      const yes = Fp.eql(powered, Fp.ONE);
      const zero = Fp.eql(powered, Fp.ZERO);
      const no = Fp.eql(powered, Fp.neg(Fp.ONE));
      if (!yes && !zero && !no)
          throw new Error('invalid Legendre symbol result');
      return yes ? 1 : zero ? 0 : -1;
  }
  // CURVE.n lengths
  function nLength$1(n, nBitLength) {
      // Bit size, byte size of CURVE.n
      if (nBitLength !== undefined)
          anumber(nBitLength);
      const _nBitLength = nBitLength !== undefined ? nBitLength : n.toString(2).length;
      const nByteLength = Math.ceil(_nBitLength / 8);
      return { nBitLength: _nBitLength, nByteLength };
  }
  /**
   * Creates a finite field. Major performance optimizations:
   * * 1. Denormalized operations like mulN instead of mul.
   * * 2. Identical object shape: never add or remove keys.
   * * 3. `Object.freeze`.
   * Fragile: always run a benchmark on a change.
   * Security note: operations don't check 'isValid' for all elements for performance reasons,
   * it is caller responsibility to check this.
   * This is low-level code, please make sure you know what you're doing.
   *
   * Note about field properties:
   * * CHARACTERISTIC p = prime number, number of elements in main subgroup.
   * * ORDER q = similar to cofactor in curves, may be composite `q = p^m`.
   *
   * @param ORDER field order, probably prime, or could be composite
   * @param bitLen how many bits the field consumes
   * @param isLE (default: false) if encoding / decoding should be in little-endian
   * @param redef optional faster redefinitions of sqrt and other methods
   */
  function Field$1(ORDER, bitLenOrOpts, // TODO: use opts only in v2?
  isLE = false, opts = {}) {
      if (ORDER <= _0n$6)
          throw new Error('invalid field: expected ORDER > 0, got ' + ORDER);
      let _nbitLength = undefined;
      let _sqrt = undefined;
      let modFromBytes = false;
      let allowedLengths = undefined;
      if (typeof bitLenOrOpts === 'object' && bitLenOrOpts != null) {
          if (opts.sqrt || isLE)
              throw new Error('cannot specify opts in two arguments');
          const _opts = bitLenOrOpts;
          if (_opts.BITS)
              _nbitLength = _opts.BITS;
          if (_opts.sqrt)
              _sqrt = _opts.sqrt;
          if (typeof _opts.isLE === 'boolean')
              isLE = _opts.isLE;
          if (typeof _opts.modFromBytes === 'boolean')
              modFromBytes = _opts.modFromBytes;
          allowedLengths = _opts.allowedLengths;
      }
      else {
          if (typeof bitLenOrOpts === 'number')
              _nbitLength = bitLenOrOpts;
          if (opts.sqrt)
              _sqrt = opts.sqrt;
      }
      const { nBitLength: BITS, nByteLength: BYTES } = nLength$1(ORDER, _nbitLength);
      if (BYTES > 2048)
          throw new Error('invalid field: expected ORDER of <= 2048 bytes');
      let sqrtP; // cached sqrtP
      const f = Object.freeze({
          ORDER,
          isLE,
          BITS,
          BYTES,
          MASK: bitMask$1(BITS),
          ZERO: _0n$6,
          ONE: _1n$7,
          allowedLengths: allowedLengths,
          create: (num) => mod$2(num, ORDER),
          isValid: (num) => {
              if (typeof num !== 'bigint')
                  throw new Error('invalid field element: expected bigint, got ' + typeof num);
              return _0n$6 <= num && num < ORDER; // 0 is valid element, but it's not invertible
          },
          is0: (num) => num === _0n$6,
          // is valid and invertible
          isValidNot0: (num) => !f.is0(num) && f.isValid(num),
          isOdd: (num) => (num & _1n$7) === _1n$7,
          neg: (num) => mod$2(-num, ORDER),
          eql: (lhs, rhs) => lhs === rhs,
          sqr: (num) => mod$2(num * num, ORDER),
          add: (lhs, rhs) => mod$2(lhs + rhs, ORDER),
          sub: (lhs, rhs) => mod$2(lhs - rhs, ORDER),
          mul: (lhs, rhs) => mod$2(lhs * rhs, ORDER),
          pow: (num, power) => FpPow$1(f, num, power),
          div: (lhs, rhs) => mod$2(lhs * invert$2(rhs, ORDER), ORDER),
          // Same as above, but doesn't normalize
          sqrN: (num) => num * num,
          addN: (lhs, rhs) => lhs + rhs,
          subN: (lhs, rhs) => lhs - rhs,
          mulN: (lhs, rhs) => lhs * rhs,
          inv: (num) => invert$2(num, ORDER),
          sqrt: _sqrt ||
              ((n) => {
                  if (!sqrtP)
                      sqrtP = FpSqrt$1(ORDER);
                  return sqrtP(f, n);
              }),
          toBytes: (num) => (isLE ? numberToBytesLE$1(num, BYTES) : numberToBytesBE$2(num, BYTES)),
          fromBytes: (bytes, skipValidation = true) => {
              if (allowedLengths) {
                  if (!allowedLengths.includes(bytes.length) || bytes.length > BYTES) {
                      throw new Error('Field.fromBytes: expected ' + allowedLengths + ' bytes, got ' + bytes.length);
                  }
                  const padded = new Uint8Array(BYTES);
                  // isLE add 0 to right, !isLE to the left.
                  padded.set(bytes, isLE ? 0 : padded.length - bytes.length);
                  bytes = padded;
              }
              if (bytes.length !== BYTES)
                  throw new Error('Field.fromBytes: expected ' + BYTES + ' bytes, got ' + bytes.length);
              let scalar = isLE ? bytesToNumberLE$1(bytes) : bytesToNumberBE$2(bytes);
              if (modFromBytes)
                  scalar = mod$2(scalar, ORDER);
              if (!skipValidation)
                  if (!f.isValid(scalar))
                      throw new Error('invalid field element: outside of range 0..ORDER');
              // NOTE: we don't validate scalar here, please use isValid. This done such way because some
              // protocol may allow non-reduced scalar that reduced later or changed some other way.
              return scalar;
          },
          // TODO: we don't need it here, move out to separate fn
          invertBatch: (lst) => FpInvertBatch$1(f, lst),
          // We can't move this out because Fp6, Fp12 implement it
          // and it's unclear what to return in there.
          cmov: (a, b, c) => (c ? b : a),
      });
      return Object.freeze(f);
  }
  /**
   * Returns total number of bytes consumed by the field element.
   * For example, 32 bytes for usual 256-bit weierstrass curve.
   * @param fieldOrder number of field elements, usually CURVE.n
   * @returns byte length of field
   */
  function getFieldBytesLength$1(fieldOrder) {
      if (typeof fieldOrder !== 'bigint')
          throw new Error('field order must be bigint');
      const bitLength = fieldOrder.toString(2).length;
      return Math.ceil(bitLength / 8);
  }
  /**
   * Returns minimal amount of bytes that can be safely reduced
   * by field order.
   * Should be 2^-128 for 128-bit curve such as P256.
   * @param fieldOrder number of field elements, usually CURVE.n
   * @returns byte length of target hash
   */
  function getMinHashLength$1(fieldOrder) {
      const length = getFieldBytesLength$1(fieldOrder);
      return length + Math.ceil(length / 2);
  }
  /**
   * "Constant-time" private key generation utility.
   * Can take (n + n/2) or more bytes of uniform input e.g. from CSPRNG or KDF
   * and convert them into private scalar, with the modulo bias being negligible.
   * Needs at least 48 bytes of input for 32-byte private key.
   * https://research.kudelskisecurity.com/2020/07/28/the-definitive-guide-to-modulo-bias-and-how-to-avoid-it/
   * FIPS 186-5, A.2 https://csrc.nist.gov/publications/detail/fips/186/5/final
   * RFC 9380, https://www.rfc-editor.org/rfc/rfc9380#section-5
   * @param hash hash output from SHA3 or a similar function
   * @param groupOrder size of subgroup - (e.g. secp256k1.CURVE.n)
   * @param isLE interpret hash bytes as LE num
   * @returns valid private scalar
   */
  function mapHashToField$1(key, fieldOrder, isLE = false) {
      const len = key.length;
      const fieldLen = getFieldBytesLength$1(fieldOrder);
      const minLen = getMinHashLength$1(fieldOrder);
      // No small numbers: need to understand bias story. No huge numbers: easier to detect JS timings.
      if (len < 16 || len < minLen || len > 1024)
          throw new Error('expected ' + minLen + '-1024 bytes of input, got ' + len);
      const num = isLE ? bytesToNumberLE$1(key) : bytesToNumberBE$2(key);
      // `mod(x, 11)` can sometimes produce 0. `mod(x, 10) + 1` is the same, but no 0
      const reduced = mod$2(num, fieldOrder - _1n$7) + _1n$7;
      return isLE ? numberToBytesLE$1(reduced, fieldLen) : numberToBytesBE$2(reduced, fieldLen);
  }

  /**
   * Methods for elliptic curve multiplication by scalars.
   * Contains wNAF, pippenger.
   * @module
   */
  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  const _0n$5 = BigInt(0);
  const _1n$6 = BigInt(1);
  function negateCt(condition, item) {
      const neg = item.negate();
      return condition ? neg : item;
  }
  /**
   * Takes a bunch of Projective Points but executes only one
   * inversion on all of them. Inversion is very slow operation,
   * so this improves performance massively.
   * Optimization: converts a list of projective points to a list of identical points with Z=1.
   */
  function normalizeZ(c, points) {
      const invertedZs = FpInvertBatch$1(c.Fp, points.map((p) => p.Z));
      return points.map((p, i) => c.fromAffine(p.toAffine(invertedZs[i])));
  }
  function validateW(W, bits) {
      if (!Number.isSafeInteger(W) || W <= 0 || W > bits)
          throw new Error('invalid window size, expected [1..' + bits + '], got W=' + W);
  }
  function calcWOpts(W, scalarBits) {
      validateW(W, scalarBits);
      const windows = Math.ceil(scalarBits / W) + 1; // W=8 33. Not 32, because we skip zero
      const windowSize = 2 ** (W - 1); // W=8 128. Not 256, because we skip zero
      const maxNumber = 2 ** W; // W=8 256
      const mask = bitMask$1(W); // W=8 255 == mask 0b11111111
      const shiftBy = BigInt(W); // W=8 8
      return { windows, windowSize, mask, maxNumber, shiftBy };
  }
  function calcOffsets(n, window, wOpts) {
      const { windowSize, mask, maxNumber, shiftBy } = wOpts;
      let wbits = Number(n & mask); // extract W bits.
      let nextN = n >> shiftBy; // shift number by W bits.
      // What actually happens here:
      // const highestBit = Number(mask ^ (mask >> 1n));
      // let wbits2 = wbits - 1; // skip zero
      // if (wbits2 & highestBit) { wbits2 ^= Number(mask); // (~);
      // split if bits > max: +224 => 256-32
      if (wbits > windowSize) {
          // we skip zero, which means instead of `>= size-1`, we do `> size`
          wbits -= maxNumber; // -32, can be maxNumber - wbits, but then we need to set isNeg here.
          nextN += _1n$6; // +256 (carry)
      }
      const offsetStart = window * windowSize;
      const offset = offsetStart + Math.abs(wbits) - 1; // -1 because we skip zero
      const isZero = wbits === 0; // is current window slice a 0?
      const isNeg = wbits < 0; // is current window slice negative?
      const isNegF = window % 2 !== 0; // fake random statement for noise
      const offsetF = offsetStart; // fake offset for noise
      return { nextN, offset, isZero, isNeg, isNegF, offsetF };
  }
  function validateMSMPoints(points, c) {
      if (!Array.isArray(points))
          throw new Error('array expected');
      points.forEach((p, i) => {
          if (!(p instanceof c))
              throw new Error('invalid point at index ' + i);
      });
  }
  function validateMSMScalars(scalars, field) {
      if (!Array.isArray(scalars))
          throw new Error('array of scalars expected');
      scalars.forEach((s, i) => {
          if (!field.isValid(s))
              throw new Error('invalid scalar at index ' + i);
      });
  }
  // Since points in different groups cannot be equal (different object constructor),
  // we can have single place to store precomputes.
  // Allows to make points frozen / immutable.
  const pointPrecomputes = new WeakMap();
  const pointWindowSizes = new WeakMap();
  function getW(P) {
      // To disable precomputes:
      // return 1;
      return pointWindowSizes.get(P) || 1;
  }
  function assert0(n) {
      if (n !== _0n$5)
          throw new Error('invalid wNAF');
  }
  /**
   * Elliptic curve multiplication of Point by scalar. Fragile.
   * Table generation takes **30MB of ram and 10ms on high-end CPU**,
   * but may take much longer on slow devices. Actual generation will happen on
   * first call of `multiply()`. By default, `BASE` point is precomputed.
   *
   * Scalars should always be less than curve order: this should be checked inside of a curve itself.
   * Creates precomputation tables for fast multiplication:
   * - private scalar is split by fixed size windows of W bits
   * - every window point is collected from window's table & added to accumulator
   * - since windows are different, same point inside tables won't be accessed more than once per calc
   * - each multiplication is 'Math.ceil(CURVE_ORDER / 𝑊) + 1' point additions (fixed for any scalar)
   * - +1 window is neccessary for wNAF
   * - wNAF reduces table size: 2x less memory + 2x faster generation, but 10% slower multiplication
   *
   * @todo Research returning 2d JS array of windows, instead of a single window.
   * This would allow windows to be in different memory locations
   */
  let wNAF$1 = class wNAF {
      // Parametrized with a given Point class (not individual point)
      constructor(Point, bits) {
          this.BASE = Point.BASE;
          this.ZERO = Point.ZERO;
          this.Fn = Point.Fn;
          this.bits = bits;
      }
      // non-const time multiplication ladder
      _unsafeLadder(elm, n, p = this.ZERO) {
          let d = elm;
          while (n > _0n$5) {
              if (n & _1n$6)
                  p = p.add(d);
              d = d.double();
              n >>= _1n$6;
          }
          return p;
      }
      /**
       * Creates a wNAF precomputation window. Used for caching.
       * Default window size is set by `utils.precompute()` and is equal to 8.
       * Number of precomputed points depends on the curve size:
       * 2^(𝑊−1) * (Math.ceil(𝑛 / 𝑊) + 1), where:
       * - 𝑊 is the window size
       * - 𝑛 is the bitlength of the curve order.
       * For a 256-bit curve and window size 8, the number of precomputed points is 128 * 33 = 4224.
       * @param point Point instance
       * @param W window size
       * @returns precomputed point tables flattened to a single array
       */
      precomputeWindow(point, W) {
          const { windows, windowSize } = calcWOpts(W, this.bits);
          const points = [];
          let p = point;
          let base = p;
          for (let window = 0; window < windows; window++) {
              base = p;
              points.push(base);
              // i=1, bc we skip 0
              for (let i = 1; i < windowSize; i++) {
                  base = base.add(p);
                  points.push(base);
              }
              p = base.double();
          }
          return points;
      }
      /**
       * Implements ec multiplication using precomputed tables and w-ary non-adjacent form.
       * More compact implementation:
       * https://github.com/paulmillr/noble-secp256k1/blob/47cb1669b6e506ad66b35fe7d76132ae97465da2/index.ts#L502-L541
       * @returns real and fake (for const-time) points
       */
      wNAF(W, precomputes, n) {
          // Scalar should be smaller than field order
          if (!this.Fn.isValid(n))
              throw new Error('invalid scalar');
          // Accumulators
          let p = this.ZERO;
          let f = this.BASE;
          // This code was first written with assumption that 'f' and 'p' will never be infinity point:
          // since each addition is multiplied by 2 ** W, it cannot cancel each other. However,
          // there is negate now: it is possible that negated element from low value
          // would be the same as high element, which will create carry into next window.
          // It's not obvious how this can fail, but still worth investigating later.
          const wo = calcWOpts(W, this.bits);
          for (let window = 0; window < wo.windows; window++) {
              // (n === _0n) is handled and not early-exited. isEven and offsetF are used for noise
              const { nextN, offset, isZero, isNeg, isNegF, offsetF } = calcOffsets(n, window, wo);
              n = nextN;
              if (isZero) {
                  // bits are 0: add garbage to fake point
                  // Important part for const-time getPublicKey: add random "noise" point to f.
                  f = f.add(negateCt(isNegF, precomputes[offsetF]));
              }
              else {
                  // bits are 1: add to result point
                  p = p.add(negateCt(isNeg, precomputes[offset]));
              }
          }
          assert0(n);
          // Return both real and fake points: JIT won't eliminate f.
          // At this point there is a way to F be infinity-point even if p is not,
          // which makes it less const-time: around 1 bigint multiply.
          return { p, f };
      }
      /**
       * Implements ec unsafe (non const-time) multiplication using precomputed tables and w-ary non-adjacent form.
       * @param acc accumulator point to add result of multiplication
       * @returns point
       */
      wNAFUnsafe(W, precomputes, n, acc = this.ZERO) {
          const wo = calcWOpts(W, this.bits);
          for (let window = 0; window < wo.windows; window++) {
              if (n === _0n$5)
                  break; // Early-exit, skip 0 value
              const { nextN, offset, isZero, isNeg } = calcOffsets(n, window, wo);
              n = nextN;
              if (isZero) {
                  // Window bits are 0: skip processing.
                  // Move to next window.
                  continue;
              }
              else {
                  const item = precomputes[offset];
                  acc = acc.add(isNeg ? item.negate() : item); // Re-using acc allows to save adds in MSM
              }
          }
          assert0(n);
          return acc;
      }
      getPrecomputes(W, point, transform) {
          // Calculate precomputes on a first run, reuse them after
          let comp = pointPrecomputes.get(point);
          if (!comp) {
              comp = this.precomputeWindow(point, W);
              if (W !== 1) {
                  // Doing transform outside of if brings 15% perf hit
                  if (typeof transform === 'function')
                      comp = transform(comp);
                  pointPrecomputes.set(point, comp);
              }
          }
          return comp;
      }
      cached(point, scalar, transform) {
          const W = getW(point);
          return this.wNAF(W, this.getPrecomputes(W, point, transform), scalar);
      }
      unsafe(point, scalar, transform, prev) {
          const W = getW(point);
          if (W === 1)
              return this._unsafeLadder(point, scalar, prev); // For W=1 ladder is ~x2 faster
          return this.wNAFUnsafe(W, this.getPrecomputes(W, point, transform), scalar, prev);
      }
      // We calculate precomputes for elliptic curve point multiplication
      // using windowed method. This specifies window size and
      // stores precomputed values. Usually only base point would be precomputed.
      createCache(P, W) {
          validateW(W, this.bits);
          pointWindowSizes.set(P, W);
          pointPrecomputes.delete(P);
      }
      hasCache(elm) {
          return getW(elm) !== 1;
      }
  };
  /**
   * Endomorphism-specific multiplication for Koblitz curves.
   * Cost: 128 dbl, 0-256 adds.
   */
  function mulEndoUnsafe(Point, point, k1, k2) {
      let acc = point;
      let p1 = Point.ZERO;
      let p2 = Point.ZERO;
      while (k1 > _0n$5 || k2 > _0n$5) {
          if (k1 & _1n$6)
              p1 = p1.add(acc);
          if (k2 & _1n$6)
              p2 = p2.add(acc);
          acc = acc.double();
          k1 >>= _1n$6;
          k2 >>= _1n$6;
      }
      return { p1, p2 };
  }
  /**
   * Pippenger algorithm for multi-scalar multiplication (MSM, Pa + Qb + Rc + ...).
   * 30x faster vs naive addition on L=4096, 10x faster than precomputes.
   * For N=254bit, L=1, it does: 1024 ADD + 254 DBL. For L=5: 1536 ADD + 254 DBL.
   * Algorithmically constant-time (for same L), even when 1 point + scalar, or when scalar = 0.
   * @param c Curve Point constructor
   * @param fieldN field over CURVE.N - important that it's not over CURVE.P
   * @param points array of L curve points
   * @param scalars array of L scalars (aka secret keys / bigints)
   */
  function pippenger(c, fieldN, points, scalars) {
      // If we split scalars by some window (let's say 8 bits), every chunk will only
      // take 256 buckets even if there are 4096 scalars, also re-uses double.
      // TODO:
      // - https://eprint.iacr.org/2024/750.pdf
      // - https://tches.iacr.org/index.php/TCHES/article/view/10287
      // 0 is accepted in scalars
      validateMSMPoints(points, c);
      validateMSMScalars(scalars, fieldN);
      const plength = points.length;
      const slength = scalars.length;
      if (plength !== slength)
          throw new Error('arrays of points and scalars must have equal length');
      // if (plength === 0) throw new Error('array must be of length >= 2');
      const zero = c.ZERO;
      const wbits = bitLen(BigInt(plength));
      let windowSize = 1; // bits
      if (wbits > 12)
          windowSize = wbits - 3;
      else if (wbits > 4)
          windowSize = wbits - 2;
      else if (wbits > 0)
          windowSize = 2;
      const MASK = bitMask$1(windowSize);
      const buckets = new Array(Number(MASK) + 1).fill(zero); // +1 for zero array
      const lastBits = Math.floor((fieldN.BITS - 1) / windowSize) * windowSize;
      let sum = zero;
      for (let i = lastBits; i >= 0; i -= windowSize) {
          buckets.fill(zero);
          for (let j = 0; j < slength; j++) {
              const scalar = scalars[j];
              const wbits = Number((scalar >> BigInt(i)) & MASK);
              buckets[wbits] = buckets[wbits].add(points[j]);
          }
          let resI = zero; // not using this will do small speed-up, but will lose ct
          // Skip first bucket, because it is zero
          for (let j = buckets.length - 1, sumI = zero; j > 0; j--) {
              sumI = sumI.add(buckets[j]);
              resI = resI.add(sumI);
          }
          sum = sum.add(resI);
          if (i !== 0)
              for (let j = 0; j < windowSize; j++)
                  sum = sum.double();
      }
      return sum;
  }
  function createField(order, field, isLE) {
      if (field) {
          if (field.ORDER !== order)
              throw new Error('Field.ORDER must match order: Fp == p, Fn == n');
          validateField$1(field);
          return field;
      }
      else {
          return Field$1(order, { isLE });
      }
  }
  /** Validates CURVE opts and creates fields */
  function _createCurveFields(type, CURVE, curveOpts = {}, FpFnLE) {
      if (FpFnLE === undefined)
          FpFnLE = type === 'edwards';
      if (!CURVE || typeof CURVE !== 'object')
          throw new Error(`expected valid ${type} CURVE object`);
      for (const p of ['p', 'n', 'h']) {
          const val = CURVE[p];
          if (!(typeof val === 'bigint' && val > _0n$5))
              throw new Error(`CURVE.${p} must be positive bigint`);
      }
      const Fp = createField(CURVE.p, curveOpts.Fp, FpFnLE);
      const Fn = createField(CURVE.n, curveOpts.Fn, FpFnLE);
      const _b = 'b' ;
      const params = ['Gx', 'Gy', 'a', _b];
      for (const p of params) {
          // @ts-ignore
          if (!Fp.isValid(CURVE[p]))
              throw new Error(`CURVE.${p} must be valid field element of CURVE.Fp`);
      }
      CURVE = Object.freeze(Object.assign({}, CURVE));
      return { CURVE, Fp, Fn };
  }

  /**
   * Short Weierstrass curve methods. The formula is: y² = x³ + ax + b.
   *
   * ### Design rationale for types
   *
   * * Interaction between classes from different curves should fail:
   *   `k256.Point.BASE.add(p256.Point.BASE)`
   * * For this purpose we want to use `instanceof` operator, which is fast and works during runtime
   * * Different calls of `curve()` would return different classes -
   *   `curve(params) !== curve(params)`: if somebody decided to monkey-patch their curve,
   *   it won't affect others
   *
   * TypeScript can't infer types for classes created inside a function. Classes is one instance
   * of nominative types in TypeScript and interfaces only check for shape, so it's hard to create
   * unique type for every function call.
   *
   * We can use generic types via some param, like curve opts, but that would:
   *     1. Enable interaction between `curve(params)` and `curve(params)` (curves of same params)
   *     which is hard to debug.
   *     2. Params can be generic and we can't enforce them to be constant value:
   *     if somebody creates curve from non-constant params,
   *     it would be allowed to interact with other curves with non-constant params
   *
   * @todo https://www.typescriptlang.org/docs/handbook/release-notes/typescript-2-7.html#unique-symbol
   * @module
   */
  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // We construct basis in such way that den is always positive and equals n, but num sign depends on basis (not on secret value)
  const divNearest$1 = (num, den) => (num + (num >= 0 ? den : -den) / _2n$4) / den;
  /**
   * Splits scalar for GLV endomorphism.
   */
  function _splitEndoScalar(k, basis, n) {
      // Split scalar into two such that part is ~half bits: `abs(part) < sqrt(N)`
      // Since part can be negative, we need to do this on point.
      // TODO: verifyScalar function which consumes lambda
      const [[a1, b1], [a2, b2]] = basis;
      const c1 = divNearest$1(b2 * k, n);
      const c2 = divNearest$1(-b1 * k, n);
      // |k1|/|k2| is < sqrt(N), but can be negative.
      // If we do `k1 mod N`, we'll get big scalar (`> sqrt(N)`): so, we do cheaper negation instead.
      let k1 = k - c1 * a1 - c2 * a2;
      let k2 = -c1 * b1 - c2 * b2;
      const k1neg = k1 < _0n$4;
      const k2neg = k2 < _0n$4;
      if (k1neg)
          k1 = -k1;
      if (k2neg)
          k2 = -k2;
      // Double check that resulting scalar less than half bits of N: otherwise wNAF will fail.
      // This should only happen on wrong basises. Also, math inside is too complex and I don't trust it.
      const MAX_NUM = bitMask$1(Math.ceil(bitLen(n) / 2)) + _1n$5; // Half bits of N
      if (k1 < _0n$4 || k1 >= MAX_NUM || k2 < _0n$4 || k2 >= MAX_NUM) {
          throw new Error('splitScalar (endomorphism): failed, k=' + k);
      }
      return { k1neg, k1, k2neg, k2 };
  }
  function validateSigFormat(format) {
      if (!['compact', 'recovered', 'der'].includes(format))
          throw new Error('Signature format must be "compact", "recovered", or "der"');
      return format;
  }
  function validateSigOpts(opts, def) {
      const optsn = {};
      for (let optName of Object.keys(def)) {
          // @ts-ignore
          optsn[optName] = opts[optName] === undefined ? def[optName] : opts[optName];
      }
      _abool2(optsn.lowS, 'lowS');
      _abool2(optsn.prehash, 'prehash');
      if (optsn.format !== undefined)
          validateSigFormat(optsn.format);
      return optsn;
  }
  class DERErr extends Error {
      constructor(m = '') {
          super(m);
      }
  }
  /**
   * ASN.1 DER encoding utilities. ASN is very complex & fragile. Format:
   *
   *     [0x30 (SEQUENCE), bytelength, 0x02 (INTEGER), intLength, R, 0x02 (INTEGER), intLength, S]
   *
   * Docs: https://letsencrypt.org/docs/a-warm-welcome-to-asn1-and-der/, https://luca.ntop.org/Teaching/Appunti/asn1.html
   */
  const DER$1 = {
      // asn.1 DER encoding utils
      Err: DERErr,
      // Basic building block is TLV (Tag-Length-Value)
      _tlv: {
          encode: (tag, data) => {
              const { Err: E } = DER$1;
              if (tag < 0 || tag > 256)
                  throw new E('tlv.encode: wrong tag');
              if (data.length & 1)
                  throw new E('tlv.encode: unpadded data');
              const dataLen = data.length / 2;
              const len = numberToHexUnpadded(dataLen);
              if ((len.length / 2) & 128)
                  throw new E('tlv.encode: long form length too big');
              // length of length with long form flag
              const lenLen = dataLen > 127 ? numberToHexUnpadded((len.length / 2) | 128) : '';
              const t = numberToHexUnpadded(tag);
              return t + lenLen + len + data;
          },
          // v - value, l - left bytes (unparsed)
          decode(tag, data) {
              const { Err: E } = DER$1;
              let pos = 0;
              if (tag < 0 || tag > 256)
                  throw new E('tlv.encode: wrong tag');
              if (data.length < 2 || data[pos++] !== tag)
                  throw new E('tlv.decode: wrong tlv');
              const first = data[pos++];
              const isLong = !!(first & 128); // First bit of first length byte is flag for short/long form
              let length = 0;
              if (!isLong)
                  length = first;
              else {
                  // Long form: [longFlag(1bit), lengthLength(7bit), length (BE)]
                  const lenLen = first & 127;
                  if (!lenLen)
                      throw new E('tlv.decode(long): indefinite length not supported');
                  if (lenLen > 4)
                      throw new E('tlv.decode(long): byte length is too big'); // this will overflow u32 in js
                  const lengthBytes = data.subarray(pos, pos + lenLen);
                  if (lengthBytes.length !== lenLen)
                      throw new E('tlv.decode: length bytes not complete');
                  if (lengthBytes[0] === 0)
                      throw new E('tlv.decode(long): zero leftmost byte');
                  for (const b of lengthBytes)
                      length = (length << 8) | b;
                  pos += lenLen;
                  if (length < 128)
                      throw new E('tlv.decode(long): not minimal encoding');
              }
              const v = data.subarray(pos, pos + length);
              if (v.length !== length)
                  throw new E('tlv.decode: wrong value length');
              return { v, l: data.subarray(pos + length) };
          },
      },
      // https://crypto.stackexchange.com/a/57734 Leftmost bit of first byte is 'negative' flag,
      // since we always use positive integers here. It must always be empty:
      // - add zero byte if exists
      // - if next byte doesn't have a flag, leading zero is not allowed (minimal encoding)
      _int: {
          encode(num) {
              const { Err: E } = DER$1;
              if (num < _0n$4)
                  throw new E('integer: negative integers are not allowed');
              let hex = numberToHexUnpadded(num);
              // Pad with zero byte if negative flag is present
              if (Number.parseInt(hex[0], 16) & 0b1000)
                  hex = '00' + hex;
              if (hex.length & 1)
                  throw new E('unexpected DER parsing assertion: unpadded hex');
              return hex;
          },
          decode(data) {
              const { Err: E } = DER$1;
              if (data[0] & 128)
                  throw new E('invalid signature integer: negative');
              if (data[0] === 0x00 && !(data[1] & 128))
                  throw new E('invalid signature integer: unnecessary leading zero');
              return bytesToNumberBE$2(data);
          },
      },
      toSig(hex) {
          // parse DER signature
          const { Err: E, _int: int, _tlv: tlv } = DER$1;
          const data = ensureBytes$1('signature', hex);
          const { v: seqBytes, l: seqLeftBytes } = tlv.decode(0x30, data);
          if (seqLeftBytes.length)
              throw new E('invalid signature: left bytes after parsing');
          const { v: rBytes, l: rLeftBytes } = tlv.decode(0x02, seqBytes);
          const { v: sBytes, l: sLeftBytes } = tlv.decode(0x02, rLeftBytes);
          if (sLeftBytes.length)
              throw new E('invalid signature: left bytes after parsing');
          return { r: int.decode(rBytes), s: int.decode(sBytes) };
      },
      hexFromSig(sig) {
          const { _tlv: tlv, _int: int } = DER$1;
          const rs = tlv.encode(0x02, int.encode(sig.r));
          const ss = tlv.encode(0x02, int.encode(sig.s));
          const seq = rs + ss;
          return tlv.encode(0x30, seq);
      },
  };
  // Be friendly to bad ECMAScript parsers by not using bigint literals
  // prettier-ignore
  const _0n$4 = BigInt(0), _1n$5 = BigInt(1), _2n$4 = BigInt(2), _3n$2 = BigInt(3), _4n$1 = BigInt(4);
  function _normFnElement(Fn, key) {
      const { BYTES: expected } = Fn;
      let num;
      if (typeof key === 'bigint') {
          num = key;
      }
      else {
          let bytes = ensureBytes$1('private key', key);
          try {
              num = Fn.fromBytes(bytes);
          }
          catch (error) {
              throw new Error(`invalid private key: expected ui8a of size ${expected}, got ${typeof key}`);
          }
      }
      if (!Fn.isValidNot0(num))
          throw new Error('invalid private key: out of range [1..N-1]');
      return num;
  }
  /**
   * Creates weierstrass Point constructor, based on specified curve options.
   *
   * @example
  ```js
  const opts = {
    p: BigInt('0xffffffff00000001000000000000000000000000ffffffffffffffffffffffff'),
    n: BigInt('0xffffffff00000000ffffffffffffffffbce6faada7179e84f3b9cac2fc632551'),
    h: BigInt(1),
    a: BigInt('0xffffffff00000001000000000000000000000000fffffffffffffffffffffffc'),
    b: BigInt('0x5ac635d8aa3a93e7b3ebbd55769886bc651d06b0cc53b0f63bce3c3e27d2604b'),
    Gx: BigInt('0x6b17d1f2e12c4247f8bce6e563a440f277037d812deb33a0f4a13945d898c296'),
    Gy: BigInt('0x4fe342e2fe1a7f9b8ee7eb4a7c0f9e162bce33576b315ececbb6406837bf51f5'),
  };
  const p256_Point = weierstrass(opts);
  ```
   */
  function weierstrassN(params, extraOpts = {}) {
      const validated = _createCurveFields('weierstrass', params, extraOpts);
      const { Fp, Fn } = validated;
      let CURVE = validated.CURVE;
      const { h: cofactor, n: CURVE_ORDER } = CURVE;
      _validateObject(extraOpts, {}, {
          allowInfinityPoint: 'boolean',
          clearCofactor: 'function',
          isTorsionFree: 'function',
          fromBytes: 'function',
          toBytes: 'function',
          endo: 'object',
          wrapPrivateKey: 'boolean',
      });
      const { endo } = extraOpts;
      if (endo) {
          // validateObject(endo, { beta: 'bigint', splitScalar: 'function' });
          if (!Fp.is0(CURVE.a) || typeof endo.beta !== 'bigint' || !Array.isArray(endo.basises)) {
              throw new Error('invalid endo: expected "beta": bigint and "basises": array');
          }
      }
      const lengths = getWLengths(Fp, Fn);
      function assertCompressionIsSupported() {
          if (!Fp.isOdd)
              throw new Error('compression is not supported: Field does not have .isOdd()');
      }
      // Implements IEEE P1363 point encoding
      function pointToBytes(_c, point, isCompressed) {
          const { x, y } = point.toAffine();
          const bx = Fp.toBytes(x);
          _abool2(isCompressed, 'isCompressed');
          if (isCompressed) {
              assertCompressionIsSupported();
              const hasEvenY = !Fp.isOdd(y);
              return concatBytes$2(pprefix(hasEvenY), bx);
          }
          else {
              return concatBytes$2(Uint8Array.of(0x04), bx, Fp.toBytes(y));
          }
      }
      function pointFromBytes(bytes) {
          _abytes2(bytes, undefined, 'Point');
          const { publicKey: comp, publicKeyUncompressed: uncomp } = lengths; // e.g. for 32-byte: 33, 65
          const length = bytes.length;
          const head = bytes[0];
          const tail = bytes.subarray(1);
          // No actual validation is done here: use .assertValidity()
          if (length === comp && (head === 0x02 || head === 0x03)) {
              const x = Fp.fromBytes(tail);
              if (!Fp.isValid(x))
                  throw new Error('bad point: is not on curve, wrong x');
              const y2 = weierstrassEquation(x); // y² = x³ + ax + b
              let y;
              try {
                  y = Fp.sqrt(y2); // y = y² ^ (p+1)/4
              }
              catch (sqrtError) {
                  const err = sqrtError instanceof Error ? ': ' + sqrtError.message : '';
                  throw new Error('bad point: is not on curve, sqrt error' + err);
              }
              assertCompressionIsSupported();
              const isYOdd = Fp.isOdd(y); // (y & _1n) === _1n;
              const isHeadOdd = (head & 1) === 1; // ECDSA-specific
              if (isHeadOdd !== isYOdd)
                  y = Fp.neg(y);
              return { x, y };
          }
          else if (length === uncomp && head === 0x04) {
              // TODO: more checks
              const L = Fp.BYTES;
              const x = Fp.fromBytes(tail.subarray(0, L));
              const y = Fp.fromBytes(tail.subarray(L, L * 2));
              if (!isValidXY(x, y))
                  throw new Error('bad point: is not on curve');
              return { x, y };
          }
          else {
              throw new Error(`bad point: got length ${length}, expected compressed=${comp} or uncompressed=${uncomp}`);
          }
      }
      const encodePoint = extraOpts.toBytes || pointToBytes;
      const decodePoint = extraOpts.fromBytes || pointFromBytes;
      function weierstrassEquation(x) {
          const x2 = Fp.sqr(x); // x * x
          const x3 = Fp.mul(x2, x); // x² * x
          return Fp.add(Fp.add(x3, Fp.mul(x, CURVE.a)), CURVE.b); // x³ + a * x + b
      }
      // TODO: move top-level
      /** Checks whether equation holds for given x, y: y² == x³ + ax + b */
      function isValidXY(x, y) {
          const left = Fp.sqr(y); // y²
          const right = weierstrassEquation(x); // x³ + ax + b
          return Fp.eql(left, right);
      }
      // Validate whether the passed curve params are valid.
      // Test 1: equation y² = x³ + ax + b should work for generator point.
      if (!isValidXY(CURVE.Gx, CURVE.Gy))
          throw new Error('bad curve params: generator point');
      // Test 2: discriminant Δ part should be non-zero: 4a³ + 27b² != 0.
      // Guarantees curve is genus-1, smooth (non-singular).
      const _4a3 = Fp.mul(Fp.pow(CURVE.a, _3n$2), _4n$1);
      const _27b2 = Fp.mul(Fp.sqr(CURVE.b), BigInt(27));
      if (Fp.is0(Fp.add(_4a3, _27b2)))
          throw new Error('bad curve params: a or b');
      /** Asserts coordinate is valid: 0 <= n < Fp.ORDER. */
      function acoord(title, n, banZero = false) {
          if (!Fp.isValid(n) || (banZero && Fp.is0(n)))
              throw new Error(`bad point coordinate ${title}`);
          return n;
      }
      function aprjpoint(other) {
          if (!(other instanceof Point))
              throw new Error('ProjectivePoint expected');
      }
      function splitEndoScalarN(k) {
          if (!endo || !endo.basises)
              throw new Error('no endo');
          return _splitEndoScalar(k, endo.basises, Fn.ORDER);
      }
      // Memoized toAffine / validity check. They are heavy. Points are immutable.
      // Converts Projective point to affine (x, y) coordinates.
      // Can accept precomputed Z^-1 - for example, from invertBatch.
      // (X, Y, Z) ∋ (x=X/Z, y=Y/Z)
      const toAffineMemo = memoized((p, iz) => {
          const { X, Y, Z } = p;
          // Fast-path for normalized points
          if (Fp.eql(Z, Fp.ONE))
              return { x: X, y: Y };
          const is0 = p.is0();
          // If invZ was 0, we return zero point. However we still want to execute
          // all operations, so we replace invZ with a random number, 1.
          if (iz == null)
              iz = is0 ? Fp.ONE : Fp.inv(Z);
          const x = Fp.mul(X, iz);
          const y = Fp.mul(Y, iz);
          const zz = Fp.mul(Z, iz);
          if (is0)
              return { x: Fp.ZERO, y: Fp.ZERO };
          if (!Fp.eql(zz, Fp.ONE))
              throw new Error('invZ was invalid');
          return { x, y };
      });
      // NOTE: on exception this will crash 'cached' and no value will be set.
      // Otherwise true will be return
      const assertValidMemo = memoized((p) => {
          if (p.is0()) {
              // (0, 1, 0) aka ZERO is invalid in most contexts.
              // In BLS, ZERO can be serialized, so we allow it.
              // (0, 0, 0) is invalid representation of ZERO.
              if (extraOpts.allowInfinityPoint && !Fp.is0(p.Y))
                  return;
              throw new Error('bad point: ZERO');
          }
          // Some 3rd-party test vectors require different wording between here & `fromCompressedHex`
          const { x, y } = p.toAffine();
          if (!Fp.isValid(x) || !Fp.isValid(y))
              throw new Error('bad point: x or y not field elements');
          if (!isValidXY(x, y))
              throw new Error('bad point: equation left != right');
          if (!p.isTorsionFree())
              throw new Error('bad point: not in prime-order subgroup');
          return true;
      });
      function finishEndo(endoBeta, k1p, k2p, k1neg, k2neg) {
          k2p = new Point(Fp.mul(k2p.X, endoBeta), k2p.Y, k2p.Z);
          k1p = negateCt(k1neg, k1p);
          k2p = negateCt(k2neg, k2p);
          return k1p.add(k2p);
      }
      /**
       * Projective Point works in 3d / projective (homogeneous) coordinates:(X, Y, Z) ∋ (x=X/Z, y=Y/Z).
       * Default Point works in 2d / affine coordinates: (x, y).
       * We're doing calculations in projective, because its operations don't require costly inversion.
       */
      class Point {
          /** Does NOT validate if the point is valid. Use `.assertValidity()`. */
          constructor(X, Y, Z) {
              this.X = acoord('x', X);
              this.Y = acoord('y', Y, true);
              this.Z = acoord('z', Z);
              Object.freeze(this);
          }
          static CURVE() {
              return CURVE;
          }
          /** Does NOT validate if the point is valid. Use `.assertValidity()`. */
          static fromAffine(p) {
              const { x, y } = p || {};
              if (!p || !Fp.isValid(x) || !Fp.isValid(y))
                  throw new Error('invalid affine point');
              if (p instanceof Point)
                  throw new Error('projective point not allowed');
              // (0, 0) would've produced (0, 0, 1) - instead, we need (0, 1, 0)
              if (Fp.is0(x) && Fp.is0(y))
                  return Point.ZERO;
              return new Point(x, y, Fp.ONE);
          }
          static fromBytes(bytes) {
              const P = Point.fromAffine(decodePoint(_abytes2(bytes, undefined, 'point')));
              P.assertValidity();
              return P;
          }
          static fromHex(hex) {
              return Point.fromBytes(ensureBytes$1('pointHex', hex));
          }
          get x() {
              return this.toAffine().x;
          }
          get y() {
              return this.toAffine().y;
          }
          /**
           *
           * @param windowSize
           * @param isLazy true will defer table computation until the first multiplication
           * @returns
           */
          precompute(windowSize = 8, isLazy = true) {
              wnaf.createCache(this, windowSize);
              if (!isLazy)
                  this.multiply(_3n$2); // random number
              return this;
          }
          // TODO: return `this`
          /** A point on curve is valid if it conforms to equation. */
          assertValidity() {
              assertValidMemo(this);
          }
          hasEvenY() {
              const { y } = this.toAffine();
              if (!Fp.isOdd)
                  throw new Error("Field doesn't support isOdd");
              return !Fp.isOdd(y);
          }
          /** Compare one point to another. */
          equals(other) {
              aprjpoint(other);
              const { X: X1, Y: Y1, Z: Z1 } = this;
              const { X: X2, Y: Y2, Z: Z2 } = other;
              const U1 = Fp.eql(Fp.mul(X1, Z2), Fp.mul(X2, Z1));
              const U2 = Fp.eql(Fp.mul(Y1, Z2), Fp.mul(Y2, Z1));
              return U1 && U2;
          }
          /** Flips point to one corresponding to (x, -y) in Affine coordinates. */
          negate() {
              return new Point(this.X, Fp.neg(this.Y), this.Z);
          }
          // Renes-Costello-Batina exception-free doubling formula.
          // There is 30% faster Jacobian formula, but it is not complete.
          // https://eprint.iacr.org/2015/1060, algorithm 3
          // Cost: 8M + 3S + 3*a + 2*b3 + 15add.
          double() {
              const { a, b } = CURVE;
              const b3 = Fp.mul(b, _3n$2);
              const { X: X1, Y: Y1, Z: Z1 } = this;
              let X3 = Fp.ZERO, Y3 = Fp.ZERO, Z3 = Fp.ZERO; // prettier-ignore
              let t0 = Fp.mul(X1, X1); // step 1
              let t1 = Fp.mul(Y1, Y1);
              let t2 = Fp.mul(Z1, Z1);
              let t3 = Fp.mul(X1, Y1);
              t3 = Fp.add(t3, t3); // step 5
              Z3 = Fp.mul(X1, Z1);
              Z3 = Fp.add(Z3, Z3);
              X3 = Fp.mul(a, Z3);
              Y3 = Fp.mul(b3, t2);
              Y3 = Fp.add(X3, Y3); // step 10
              X3 = Fp.sub(t1, Y3);
              Y3 = Fp.add(t1, Y3);
              Y3 = Fp.mul(X3, Y3);
              X3 = Fp.mul(t3, X3);
              Z3 = Fp.mul(b3, Z3); // step 15
              t2 = Fp.mul(a, t2);
              t3 = Fp.sub(t0, t2);
              t3 = Fp.mul(a, t3);
              t3 = Fp.add(t3, Z3);
              Z3 = Fp.add(t0, t0); // step 20
              t0 = Fp.add(Z3, t0);
              t0 = Fp.add(t0, t2);
              t0 = Fp.mul(t0, t3);
              Y3 = Fp.add(Y3, t0);
              t2 = Fp.mul(Y1, Z1); // step 25
              t2 = Fp.add(t2, t2);
              t0 = Fp.mul(t2, t3);
              X3 = Fp.sub(X3, t0);
              Z3 = Fp.mul(t2, t1);
              Z3 = Fp.add(Z3, Z3); // step 30
              Z3 = Fp.add(Z3, Z3);
              return new Point(X3, Y3, Z3);
          }
          // Renes-Costello-Batina exception-free addition formula.
          // There is 30% faster Jacobian formula, but it is not complete.
          // https://eprint.iacr.org/2015/1060, algorithm 1
          // Cost: 12M + 0S + 3*a + 3*b3 + 23add.
          add(other) {
              aprjpoint(other);
              const { X: X1, Y: Y1, Z: Z1 } = this;
              const { X: X2, Y: Y2, Z: Z2 } = other;
              let X3 = Fp.ZERO, Y3 = Fp.ZERO, Z3 = Fp.ZERO; // prettier-ignore
              const a = CURVE.a;
              const b3 = Fp.mul(CURVE.b, _3n$2);
              let t0 = Fp.mul(X1, X2); // step 1
              let t1 = Fp.mul(Y1, Y2);
              let t2 = Fp.mul(Z1, Z2);
              let t3 = Fp.add(X1, Y1);
              let t4 = Fp.add(X2, Y2); // step 5
              t3 = Fp.mul(t3, t4);
              t4 = Fp.add(t0, t1);
              t3 = Fp.sub(t3, t4);
              t4 = Fp.add(X1, Z1);
              let t5 = Fp.add(X2, Z2); // step 10
              t4 = Fp.mul(t4, t5);
              t5 = Fp.add(t0, t2);
              t4 = Fp.sub(t4, t5);
              t5 = Fp.add(Y1, Z1);
              X3 = Fp.add(Y2, Z2); // step 15
              t5 = Fp.mul(t5, X3);
              X3 = Fp.add(t1, t2);
              t5 = Fp.sub(t5, X3);
              Z3 = Fp.mul(a, t4);
              X3 = Fp.mul(b3, t2); // step 20
              Z3 = Fp.add(X3, Z3);
              X3 = Fp.sub(t1, Z3);
              Z3 = Fp.add(t1, Z3);
              Y3 = Fp.mul(X3, Z3);
              t1 = Fp.add(t0, t0); // step 25
              t1 = Fp.add(t1, t0);
              t2 = Fp.mul(a, t2);
              t4 = Fp.mul(b3, t4);
              t1 = Fp.add(t1, t2);
              t2 = Fp.sub(t0, t2); // step 30
              t2 = Fp.mul(a, t2);
              t4 = Fp.add(t4, t2);
              t0 = Fp.mul(t1, t4);
              Y3 = Fp.add(Y3, t0);
              t0 = Fp.mul(t5, t4); // step 35
              X3 = Fp.mul(t3, X3);
              X3 = Fp.sub(X3, t0);
              t0 = Fp.mul(t3, t1);
              Z3 = Fp.mul(t5, Z3);
              Z3 = Fp.add(Z3, t0); // step 40
              return new Point(X3, Y3, Z3);
          }
          subtract(other) {
              return this.add(other.negate());
          }
          is0() {
              return this.equals(Point.ZERO);
          }
          /**
           * Constant time multiplication.
           * Uses wNAF method. Windowed method may be 10% faster,
           * but takes 2x longer to generate and consumes 2x memory.
           * Uses precomputes when available.
           * Uses endomorphism for Koblitz curves.
           * @param scalar by which the point would be multiplied
           * @returns New point
           */
          multiply(scalar) {
              const { endo } = extraOpts;
              if (!Fn.isValidNot0(scalar))
                  throw new Error('invalid scalar: out of range'); // 0 is invalid
              let point, fake; // Fake point is used to const-time mult
              const mul = (n) => wnaf.cached(this, n, (p) => normalizeZ(Point, p));
              /** See docs for {@link EndomorphismOpts} */
              if (endo) {
                  const { k1neg, k1, k2neg, k2 } = splitEndoScalarN(scalar);
                  const { p: k1p, f: k1f } = mul(k1);
                  const { p: k2p, f: k2f } = mul(k2);
                  fake = k1f.add(k2f);
                  point = finishEndo(endo.beta, k1p, k2p, k1neg, k2neg);
              }
              else {
                  const { p, f } = mul(scalar);
                  point = p;
                  fake = f;
              }
              // Normalize `z` for both points, but return only real one
              return normalizeZ(Point, [point, fake])[0];
          }
          /**
           * Non-constant-time multiplication. Uses double-and-add algorithm.
           * It's faster, but should only be used when you don't care about
           * an exposed secret key e.g. sig verification, which works over *public* keys.
           */
          multiplyUnsafe(sc) {
              const { endo } = extraOpts;
              const p = this;
              if (!Fn.isValid(sc))
                  throw new Error('invalid scalar: out of range'); // 0 is valid
              if (sc === _0n$4 || p.is0())
                  return Point.ZERO;
              if (sc === _1n$5)
                  return p; // fast-path
              if (wnaf.hasCache(this))
                  return this.multiply(sc);
              if (endo) {
                  const { k1neg, k1, k2neg, k2 } = splitEndoScalarN(sc);
                  const { p1, p2 } = mulEndoUnsafe(Point, p, k1, k2); // 30% faster vs wnaf.unsafe
                  return finishEndo(endo.beta, p1, p2, k1neg, k2neg);
              }
              else {
                  return wnaf.unsafe(p, sc);
              }
          }
          multiplyAndAddUnsafe(Q, a, b) {
              const sum = this.multiplyUnsafe(a).add(Q.multiplyUnsafe(b));
              return sum.is0() ? undefined : sum;
          }
          /**
           * Converts Projective point to affine (x, y) coordinates.
           * @param invertedZ Z^-1 (inverted zero) - optional, precomputation is useful for invertBatch
           */
          toAffine(invertedZ) {
              return toAffineMemo(this, invertedZ);
          }
          /**
           * Checks whether Point is free of torsion elements (is in prime subgroup).
           * Always torsion-free for cofactor=1 curves.
           */
          isTorsionFree() {
              const { isTorsionFree } = extraOpts;
              if (cofactor === _1n$5)
                  return true;
              if (isTorsionFree)
                  return isTorsionFree(Point, this);
              return wnaf.unsafe(this, CURVE_ORDER).is0();
          }
          clearCofactor() {
              const { clearCofactor } = extraOpts;
              if (cofactor === _1n$5)
                  return this; // Fast-path
              if (clearCofactor)
                  return clearCofactor(Point, this);
              return this.multiplyUnsafe(cofactor);
          }
          isSmallOrder() {
              // can we use this.clearCofactor()?
              return this.multiplyUnsafe(cofactor).is0();
          }
          toBytes(isCompressed = true) {
              _abool2(isCompressed, 'isCompressed');
              this.assertValidity();
              return encodePoint(Point, this, isCompressed);
          }
          toHex(isCompressed = true) {
              return bytesToHex$3(this.toBytes(isCompressed));
          }
          toString() {
              return `<Point ${this.is0() ? 'ZERO' : this.toHex()}>`;
          }
          // TODO: remove
          get px() {
              return this.X;
          }
          get py() {
              return this.X;
          }
          get pz() {
              return this.Z;
          }
          toRawBytes(isCompressed = true) {
              return this.toBytes(isCompressed);
          }
          _setWindowSize(windowSize) {
              this.precompute(windowSize);
          }
          static normalizeZ(points) {
              return normalizeZ(Point, points);
          }
          static msm(points, scalars) {
              return pippenger(Point, Fn, points, scalars);
          }
          static fromPrivateKey(privateKey) {
              return Point.BASE.multiply(_normFnElement(Fn, privateKey));
          }
      }
      // base / generator point
      Point.BASE = new Point(CURVE.Gx, CURVE.Gy, Fp.ONE);
      // zero / infinity / identity point
      Point.ZERO = new Point(Fp.ZERO, Fp.ONE, Fp.ZERO); // 0, 1, 0
      // math field
      Point.Fp = Fp;
      // scalar field
      Point.Fn = Fn;
      const bits = Fn.BITS;
      const wnaf = new wNAF$1(Point, extraOpts.endo ? Math.ceil(bits / 2) : bits);
      Point.BASE.precompute(8); // Enable precomputes. Slows down first publicKey computation by 20ms.
      return Point;
  }
  // Points start with byte 0x02 when y is even; otherwise 0x03
  function pprefix(hasEvenY) {
      return Uint8Array.of(hasEvenY ? 0x02 : 0x03);
  }
  function getWLengths(Fp, Fn) {
      return {
          secretKey: Fn.BYTES,
          publicKey: 1 + Fp.BYTES,
          publicKeyUncompressed: 1 + 2 * Fp.BYTES,
          publicKeyHasPrefix: true,
          signature: 2 * Fn.BYTES,
      };
  }
  /**
   * Sometimes users only need getPublicKey, getSharedSecret, and secret key handling.
   * This helper ensures no signature functionality is present. Less code, smaller bundle size.
   */
  function ecdh(Point, ecdhOpts = {}) {
      const { Fn } = Point;
      const randomBytes_ = ecdhOpts.randomBytes || randomBytes$1;
      const lengths = Object.assign(getWLengths(Point.Fp, Fn), { seed: getMinHashLength$1(Fn.ORDER) });
      function isValidSecretKey(secretKey) {
          try {
              return !!_normFnElement(Fn, secretKey);
          }
          catch (error) {
              return false;
          }
      }
      function isValidPublicKey(publicKey, isCompressed) {
          const { publicKey: comp, publicKeyUncompressed } = lengths;
          try {
              const l = publicKey.length;
              if (isCompressed === true && l !== comp)
                  return false;
              if (isCompressed === false && l !== publicKeyUncompressed)
                  return false;
              return !!Point.fromBytes(publicKey);
          }
          catch (error) {
              return false;
          }
      }
      /**
       * Produces cryptographically secure secret key from random of size
       * (groupLen + ceil(groupLen / 2)) with modulo bias being negligible.
       */
      function randomSecretKey(seed = randomBytes_(lengths.seed)) {
          return mapHashToField$1(_abytes2(seed, lengths.seed, 'seed'), Fn.ORDER);
      }
      /**
       * Computes public key for a secret key. Checks for validity of the secret key.
       * @param isCompressed whether to return compact (default), or full key
       * @returns Public key, full when isCompressed=false; short when isCompressed=true
       */
      function getPublicKey(secretKey, isCompressed = true) {
          return Point.BASE.multiply(_normFnElement(Fn, secretKey)).toBytes(isCompressed);
      }
      function keygen(seed) {
          const secretKey = randomSecretKey(seed);
          return { secretKey, publicKey: getPublicKey(secretKey) };
      }
      /**
       * Quick and dirty check for item being public key. Does not validate hex, or being on-curve.
       */
      function isProbPub(item) {
          if (typeof item === 'bigint')
              return false;
          if (item instanceof Point)
              return true;
          const { secretKey, publicKey, publicKeyUncompressed } = lengths;
          if (Fn.allowedLengths || secretKey === publicKey)
              return undefined;
          const l = ensureBytes$1('key', item).length;
          return l === publicKey || l === publicKeyUncompressed;
      }
      /**
       * ECDH (Elliptic Curve Diffie Hellman).
       * Computes shared public key from secret key A and public key B.
       * Checks: 1) secret key validity 2) shared key is on-curve.
       * Does NOT hash the result.
       * @param isCompressed whether to return compact (default), or full key
       * @returns shared public key
       */
      function getSharedSecret(secretKeyA, publicKeyB, isCompressed = true) {
          if (isProbPub(secretKeyA) === true)
              throw new Error('first arg must be private key');
          if (isProbPub(publicKeyB) === false)
              throw new Error('second arg must be public key');
          const s = _normFnElement(Fn, secretKeyA);
          const b = Point.fromHex(publicKeyB); // checks for being on-curve
          return b.multiply(s).toBytes(isCompressed);
      }
      const utils = {
          isValidSecretKey,
          isValidPublicKey,
          randomSecretKey,
          // TODO: remove
          isValidPrivateKey: isValidSecretKey,
          randomPrivateKey: randomSecretKey,
          normPrivateKeyToScalar: (key) => _normFnElement(Fn, key),
          precompute(windowSize = 8, point = Point.BASE) {
              return point.precompute(windowSize, false);
          },
      };
      return Object.freeze({ getPublicKey, getSharedSecret, keygen, Point, utils, lengths });
  }
  /**
   * Creates ECDSA signing interface for given elliptic curve `Point` and `hash` function.
   * We need `hash` for 2 features:
   * 1. Message prehash-ing. NOT used if `sign` / `verify` are called with `prehash: false`
   * 2. k generation in `sign`, using HMAC-drbg(hash)
   *
   * ECDSAOpts are only rarely needed.
   *
   * @example
   * ```js
   * const p256_Point = weierstrass(...);
   * const p256_sha256 = ecdsa(p256_Point, sha256);
   * const p256_sha224 = ecdsa(p256_Point, sha224);
   * const p256_sha224_r = ecdsa(p256_Point, sha224, { randomBytes: (length) => { ... } });
   * ```
   */
  function ecdsa(Point, hash, ecdsaOpts = {}) {
      ahash(hash);
      _validateObject(ecdsaOpts, {}, {
          hmac: 'function',
          lowS: 'boolean',
          randomBytes: 'function',
          bits2int: 'function',
          bits2int_modN: 'function',
      });
      const randomBytes = ecdsaOpts.randomBytes || randomBytes$1;
      const hmac = ecdsaOpts.hmac ||
          ((key, ...msgs) => hmac$1(hash, key, concatBytes$2(...msgs)));
      const { Fp, Fn } = Point;
      const { ORDER: CURVE_ORDER, BITS: fnBits } = Fn;
      const { keygen, getPublicKey, getSharedSecret, utils, lengths } = ecdh(Point, ecdsaOpts);
      const defaultSigOpts = {
          prehash: false,
          lowS: typeof ecdsaOpts.lowS === 'boolean' ? ecdsaOpts.lowS : false,
          format: undefined, //'compact' as ECDSASigFormat,
          extraEntropy: false,
      };
      const defaultSigOpts_format = 'compact';
      function isBiggerThanHalfOrder(number) {
          const HALF = CURVE_ORDER >> _1n$5;
          return number > HALF;
      }
      function validateRS(title, num) {
          if (!Fn.isValidNot0(num))
              throw new Error(`invalid signature ${title}: out of range 1..Point.Fn.ORDER`);
          return num;
      }
      function validateSigLength(bytes, format) {
          validateSigFormat(format);
          const size = lengths.signature;
          const sizer = format === 'compact' ? size : format === 'recovered' ? size + 1 : undefined;
          return _abytes2(bytes, sizer, `${format} signature`);
      }
      /**
       * ECDSA signature with its (r, s) properties. Supports compact, recovered & DER representations.
       */
      class Signature {
          constructor(r, s, recovery) {
              this.r = validateRS('r', r); // r in [1..N-1];
              this.s = validateRS('s', s); // s in [1..N-1];
              if (recovery != null)
                  this.recovery = recovery;
              Object.freeze(this);
          }
          static fromBytes(bytes, format = defaultSigOpts_format) {
              validateSigLength(bytes, format);
              let recid;
              if (format === 'der') {
                  const { r, s } = DER$1.toSig(_abytes2(bytes));
                  return new Signature(r, s);
              }
              if (format === 'recovered') {
                  recid = bytes[0];
                  format = 'compact';
                  bytes = bytes.subarray(1);
              }
              const L = Fn.BYTES;
              const r = bytes.subarray(0, L);
              const s = bytes.subarray(L, L * 2);
              return new Signature(Fn.fromBytes(r), Fn.fromBytes(s), recid);
          }
          static fromHex(hex, format) {
              return this.fromBytes(hexToBytes$1(hex), format);
          }
          addRecoveryBit(recovery) {
              return new Signature(this.r, this.s, recovery);
          }
          recoverPublicKey(messageHash) {
              const FIELD_ORDER = Fp.ORDER;
              const { r, s, recovery: rec } = this;
              if (rec == null || ![0, 1, 2, 3].includes(rec))
                  throw new Error('recovery id invalid');
              // ECDSA recovery is hard for cofactor > 1 curves.
              // In sign, `r = q.x mod n`, and here we recover q.x from r.
              // While recovering q.x >= n, we need to add r+n for cofactor=1 curves.
              // However, for cofactor>1, r+n may not get q.x:
              // r+n*i would need to be done instead where i is unknown.
              // To easily get i, we either need to:
              // a. increase amount of valid recid values (4, 5...); OR
              // b. prohibit non-prime-order signatures (recid > 1).
              const hasCofactor = CURVE_ORDER * _2n$4 < FIELD_ORDER;
              if (hasCofactor && rec > 1)
                  throw new Error('recovery id is ambiguous for h>1 curve');
              const radj = rec === 2 || rec === 3 ? r + CURVE_ORDER : r;
              if (!Fp.isValid(radj))
                  throw new Error('recovery id 2 or 3 invalid');
              const x = Fp.toBytes(radj);
              const R = Point.fromBytes(concatBytes$2(pprefix((rec & 1) === 0), x));
              const ir = Fn.inv(radj); // r^-1
              const h = bits2int_modN(ensureBytes$1('msgHash', messageHash)); // Truncate hash
              const u1 = Fn.create(-h * ir); // -hr^-1
              const u2 = Fn.create(s * ir); // sr^-1
              // (sr^-1)R-(hr^-1)G = -(hr^-1)G + (sr^-1). unsafe is fine: there is no private data.
              const Q = Point.BASE.multiplyUnsafe(u1).add(R.multiplyUnsafe(u2));
              if (Q.is0())
                  throw new Error('point at infinify');
              Q.assertValidity();
              return Q;
          }
          // Signatures should be low-s, to prevent malleability.
          hasHighS() {
              return isBiggerThanHalfOrder(this.s);
          }
          toBytes(format = defaultSigOpts_format) {
              validateSigFormat(format);
              if (format === 'der')
                  return hexToBytes$1(DER$1.hexFromSig(this));
              const r = Fn.toBytes(this.r);
              const s = Fn.toBytes(this.s);
              if (format === 'recovered') {
                  if (this.recovery == null)
                      throw new Error('recovery bit must be present');
                  return concatBytes$2(Uint8Array.of(this.recovery), r, s);
              }
              return concatBytes$2(r, s);
          }
          toHex(format) {
              return bytesToHex$3(this.toBytes(format));
          }
          // TODO: remove
          assertValidity() { }
          static fromCompact(hex) {
              return Signature.fromBytes(ensureBytes$1('sig', hex), 'compact');
          }
          static fromDER(hex) {
              return Signature.fromBytes(ensureBytes$1('sig', hex), 'der');
          }
          normalizeS() {
              return this.hasHighS() ? new Signature(this.r, Fn.neg(this.s), this.recovery) : this;
          }
          toDERRawBytes() {
              return this.toBytes('der');
          }
          toDERHex() {
              return bytesToHex$3(this.toBytes('der'));
          }
          toCompactRawBytes() {
              return this.toBytes('compact');
          }
          toCompactHex() {
              return bytesToHex$3(this.toBytes('compact'));
          }
      }
      // RFC6979: ensure ECDSA msg is X bytes and < N. RFC suggests optional truncating via bits2octets.
      // FIPS 186-4 4.6 suggests the leftmost min(nBitLen, outLen) bits, which matches bits2int.
      // bits2int can produce res>N, we can do mod(res, N) since the bitLen is the same.
      // int2octets can't be used; pads small msgs with 0: unacceptatble for trunc as per RFC vectors
      const bits2int = ecdsaOpts.bits2int ||
          function bits2int_def(bytes) {
              // Our custom check "just in case", for protection against DoS
              if (bytes.length > 8192)
                  throw new Error('input is too large');
              // For curves with nBitLength % 8 !== 0: bits2octets(bits2octets(m)) !== bits2octets(m)
              // for some cases, since bytes.length * 8 is not actual bitLength.
              const num = bytesToNumberBE$2(bytes); // check for == u8 done here
              const delta = bytes.length * 8 - fnBits; // truncate to nBitLength leftmost bits
              return delta > 0 ? num >> BigInt(delta) : num;
          };
      const bits2int_modN = ecdsaOpts.bits2int_modN ||
          function bits2int_modN_def(bytes) {
              return Fn.create(bits2int(bytes)); // can't use bytesToNumberBE here
          };
      // Pads output with zero as per spec
      const ORDER_MASK = bitMask$1(fnBits);
      /** Converts to bytes. Checks if num in `[0..ORDER_MASK-1]` e.g.: `[0..2^256-1]`. */
      function int2octets(num) {
          // IMPORTANT: the check ensures working for case `Fn.BYTES != Fn.BITS * 8`
          aInRange('num < 2^' + fnBits, num, _0n$4, ORDER_MASK);
          return Fn.toBytes(num);
      }
      function validateMsgAndHash(message, prehash) {
          _abytes2(message, undefined, 'message');
          return prehash ? _abytes2(hash(message), undefined, 'prehashed message') : message;
      }
      /**
       * Steps A, D of RFC6979 3.2.
       * Creates RFC6979 seed; converts msg/privKey to numbers.
       * Used only in sign, not in verify.
       *
       * Warning: we cannot assume here that message has same amount of bytes as curve order,
       * this will be invalid at least for P521. Also it can be bigger for P224 + SHA256.
       */
      function prepSig(message, privateKey, opts) {
          if (['recovered', 'canonical'].some((k) => k in opts))
              throw new Error('sign() legacy options not supported');
          const { lowS, prehash, extraEntropy } = validateSigOpts(opts, defaultSigOpts);
          message = validateMsgAndHash(message, prehash); // RFC6979 3.2 A: h1 = H(m)
          // We can't later call bits2octets, since nested bits2int is broken for curves
          // with fnBits % 8 !== 0. Because of that, we unwrap it here as int2octets call.
          // const bits2octets = (bits) => int2octets(bits2int_modN(bits))
          const h1int = bits2int_modN(message);
          const d = _normFnElement(Fn, privateKey); // validate secret key, convert to bigint
          const seedArgs = [int2octets(d), int2octets(h1int)];
          // extraEntropy. RFC6979 3.6: additional k' (optional).
          if (extraEntropy != null && extraEntropy !== false) {
              // K = HMAC_K(V || 0x00 || int2octets(x) || bits2octets(h1) || k')
              // gen random bytes OR pass as-is
              const e = extraEntropy === true ? randomBytes(lengths.secretKey) : extraEntropy;
              seedArgs.push(ensureBytes$1('extraEntropy', e)); // check for being bytes
          }
          const seed = concatBytes$2(...seedArgs); // Step D of RFC6979 3.2
          const m = h1int; // NOTE: no need to call bits2int second time here, it is inside truncateHash!
          // Converts signature params into point w r/s, checks result for validity.
          // To transform k => Signature:
          // q = k⋅G
          // r = q.x mod n
          // s = k^-1(m + rd) mod n
          // Can use scalar blinding b^-1(bm + bdr) where b ∈ [1,q−1] according to
          // https://tches.iacr.org/index.php/TCHES/article/view/7337/6509. We've decided against it:
          // a) dependency on CSPRNG b) 15% slowdown c) doesn't really help since bigints are not CT
          function k2sig(kBytes) {
              // RFC 6979 Section 3.2, step 3: k = bits2int(T)
              // Important: all mod() calls here must be done over N
              const k = bits2int(kBytes); // mod n, not mod p
              if (!Fn.isValidNot0(k))
                  return; // Valid scalars (including k) must be in 1..N-1
              const ik = Fn.inv(k); // k^-1 mod n
              const q = Point.BASE.multiply(k).toAffine(); // q = k⋅G
              const r = Fn.create(q.x); // r = q.x mod n
              if (r === _0n$4)
                  return;
              const s = Fn.create(ik * Fn.create(m + r * d)); // Not using blinding here, see comment above
              if (s === _0n$4)
                  return;
              let recovery = (q.x === r ? 0 : 2) | Number(q.y & _1n$5); // recovery bit (2 or 3, when q.x > n)
              let normS = s;
              if (lowS && isBiggerThanHalfOrder(s)) {
                  normS = Fn.neg(s); // if lowS was passed, ensure s is always
                  recovery ^= 1; // // in the bottom half of N
              }
              return new Signature(r, normS, recovery); // use normS, not s
          }
          return { seed, k2sig };
      }
      /**
       * Signs message hash with a secret key.
       *
       * ```
       * sign(m, d) where
       *   k = rfc6979_hmac_drbg(m, d)
       *   (x, y) = G × k
       *   r = x mod n
       *   s = (m + dr) / k mod n
       * ```
       */
      function sign(message, secretKey, opts = {}) {
          message = ensureBytes$1('message', message);
          const { seed, k2sig } = prepSig(message, secretKey, opts); // Steps A, D of RFC6979 3.2.
          const drbg = createHmacDrbg$1(hash.outputLen, Fn.BYTES, hmac);
          const sig = drbg(seed, k2sig); // Steps B, C, D, E, F, G
          return sig;
      }
      function tryParsingSig(sg) {
          // Try to deduce format
          let sig = undefined;
          const isHex = typeof sg === 'string' || isBytes(sg);
          const isObj = !isHex &&
              sg !== null &&
              typeof sg === 'object' &&
              typeof sg.r === 'bigint' &&
              typeof sg.s === 'bigint';
          if (!isHex && !isObj)
              throw new Error('invalid signature, expected Uint8Array, hex string or Signature instance');
          if (isObj) {
              sig = new Signature(sg.r, sg.s);
          }
          else if (isHex) {
              try {
                  sig = Signature.fromBytes(ensureBytes$1('sig', sg), 'der');
              }
              catch (derError) {
                  if (!(derError instanceof DER$1.Err))
                      throw derError;
              }
              if (!sig) {
                  try {
                      sig = Signature.fromBytes(ensureBytes$1('sig', sg), 'compact');
                  }
                  catch (error) {
                      return false;
                  }
              }
          }
          if (!sig)
              return false;
          return sig;
      }
      /**
       * Verifies a signature against message and public key.
       * Rejects lowS signatures by default: see {@link ECDSAVerifyOpts}.
       * Implements section 4.1.4 from https://www.secg.org/sec1-v2.pdf:
       *
       * ```
       * verify(r, s, h, P) where
       *   u1 = hs^-1 mod n
       *   u2 = rs^-1 mod n
       *   R = u1⋅G + u2⋅P
       *   mod(R.x, n) == r
       * ```
       */
      function verify(signature, message, publicKey, opts = {}) {
          const { lowS, prehash, format } = validateSigOpts(opts, defaultSigOpts);
          publicKey = ensureBytes$1('publicKey', publicKey);
          message = validateMsgAndHash(ensureBytes$1('message', message), prehash);
          if ('strict' in opts)
              throw new Error('options.strict was renamed to lowS');
          const sig = format === undefined
              ? tryParsingSig(signature)
              : Signature.fromBytes(ensureBytes$1('sig', signature), format);
          if (sig === false)
              return false;
          try {
              const P = Point.fromBytes(publicKey);
              if (lowS && sig.hasHighS())
                  return false;
              const { r, s } = sig;
              const h = bits2int_modN(message); // mod n, not mod p
              const is = Fn.inv(s); // s^-1 mod n
              const u1 = Fn.create(h * is); // u1 = hs^-1 mod n
              const u2 = Fn.create(r * is); // u2 = rs^-1 mod n
              const R = Point.BASE.multiplyUnsafe(u1).add(P.multiplyUnsafe(u2)); // u1⋅G + u2⋅P
              if (R.is0())
                  return false;
              const v = Fn.create(R.x); // v = r.x mod n
              return v === r;
          }
          catch (e) {
              return false;
          }
      }
      function recoverPublicKey(signature, message, opts = {}) {
          const { prehash } = validateSigOpts(opts, defaultSigOpts);
          message = validateMsgAndHash(message, prehash);
          return Signature.fromBytes(signature, 'recovered').recoverPublicKey(message).toBytes();
      }
      return Object.freeze({
          keygen,
          getPublicKey,
          getSharedSecret,
          utils,
          lengths,
          Point,
          sign,
          verify,
          recoverPublicKey,
          Signature,
          hash,
      });
  }
  function _weierstrass_legacy_opts_to_new(c) {
      const CURVE = {
          a: c.a,
          b: c.b,
          p: c.Fp.ORDER,
          n: c.n,
          h: c.h,
          Gx: c.Gx,
          Gy: c.Gy,
      };
      const Fp = c.Fp;
      let allowedLengths = c.allowedPrivateKeyLengths
          ? Array.from(new Set(c.allowedPrivateKeyLengths.map((l) => Math.ceil(l / 2))))
          : undefined;
      const Fn = Field$1(CURVE.n, {
          BITS: c.nBitLength,
          allowedLengths: allowedLengths,
          modFromBytes: c.wrapPrivateKey,
      });
      const curveOpts = {
          Fp,
          Fn,
          allowInfinityPoint: c.allowInfinityPoint,
          endo: c.endo,
          isTorsionFree: c.isTorsionFree,
          clearCofactor: c.clearCofactor,
          fromBytes: c.fromBytes,
          toBytes: c.toBytes,
      };
      return { CURVE, curveOpts };
  }
  function _ecdsa_legacy_opts_to_new(c) {
      const { CURVE, curveOpts } = _weierstrass_legacy_opts_to_new(c);
      const ecdsaOpts = {
          hmac: c.hmac,
          randomBytes: c.randomBytes,
          lowS: c.lowS,
          bits2int: c.bits2int,
          bits2int_modN: c.bits2int_modN,
      };
      return { CURVE, curveOpts, hash: c.hash, ecdsaOpts };
  }
  function _ecdsa_new_output_to_legacy(c, _ecdsa) {
      const Point = _ecdsa.Point;
      return Object.assign({}, _ecdsa, {
          ProjectivePoint: Point,
          CURVE: Object.assign({}, c, nLength$1(Point.Fn.ORDER, Point.Fn.BITS)),
      });
  }
  // _ecdsa_legacy
  function weierstrass$1(c) {
      const { CURVE, curveOpts, hash, ecdsaOpts } = _ecdsa_legacy_opts_to_new(c);
      const Point = weierstrassN(CURVE, curveOpts);
      const signs = ecdsa(Point, hash, ecdsaOpts);
      return _ecdsa_new_output_to_legacy(c, signs);
  }

  /**
   * Utilities for short weierstrass curves, combined with noble-hashes.
   * @module
   */
  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  /** @deprecated use new `weierstrass()` and `ecdsa()` methods */
  function createCurve$1(curveDef, defHash) {
      const create = (hash) => weierstrass$1({ ...curveDef, hash: hash });
      return { ...create(defHash), create };
  }

  /**
   * SECG secp256k1. See [pdf](https://www.secg.org/sec2-v2.pdf).
   *
   * Belongs to Koblitz curves: it has efficiently-computable GLV endomorphism ψ,
   * check out {@link EndomorphismOpts}. Seems to be rigid (not backdoored).
   * @module
   */
  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // Seems like generator was produced from some seed:
  // `Point.BASE.multiply(Point.Fn.inv(2n, N)).toAffine().x`
  // // gives short x 0x3b78ce563f89a0ed9414f5aa28ad0d96d6795f9c63n
  const secp256k1_CURVE = {
      p: BigInt('0xfffffffffffffffffffffffffffffffffffffffffffffffffffffffefffffc2f'),
      n: BigInt('0xfffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141'),
      h: BigInt(1),
      a: BigInt(0),
      b: BigInt(7),
      Gx: BigInt('0x79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798'),
      Gy: BigInt('0x483ada7726a3c4655da4fbfc0e1108a8fd17b448a68554199c47d08ffb10d4b8'),
  };
  const secp256k1_ENDO = {
      beta: BigInt('0x7ae96a2b657c07106e64479eac3434e99cf0497512f58995c1396c28719501ee'),
      basises: [
          [BigInt('0x3086d221a7d46bcde86c90e49284eb15'), -BigInt('0xe4437ed6010e88286f547fa90abfe4c3')],
          [BigInt('0x114ca50f7a8e2f3f657c1108d9d44cfd8'), BigInt('0x3086d221a7d46bcde86c90e49284eb15')],
      ],
  };
  const _2n$3 = /* @__PURE__ */ BigInt(2);
  /**
   * √n = n^((p+1)/4) for fields p = 3 mod 4. We unwrap the loop and multiply bit-by-bit.
   * (P+1n/4n).toString(2) would produce bits [223x 1, 0, 22x 1, 4x 0, 11, 00]
   */
  function sqrtMod$1(y) {
      const P = secp256k1_CURVE.p;
      // prettier-ignore
      const _3n = BigInt(3), _6n = BigInt(6), _11n = BigInt(11), _22n = BigInt(22);
      // prettier-ignore
      const _23n = BigInt(23), _44n = BigInt(44), _88n = BigInt(88);
      const b2 = (y * y * y) % P; // x^3, 11
      const b3 = (b2 * b2 * y) % P; // x^7
      const b6 = (pow2$1(b3, _3n, P) * b3) % P;
      const b9 = (pow2$1(b6, _3n, P) * b3) % P;
      const b11 = (pow2$1(b9, _2n$3, P) * b2) % P;
      const b22 = (pow2$1(b11, _11n, P) * b11) % P;
      const b44 = (pow2$1(b22, _22n, P) * b22) % P;
      const b88 = (pow2$1(b44, _44n, P) * b44) % P;
      const b176 = (pow2$1(b88, _88n, P) * b88) % P;
      const b220 = (pow2$1(b176, _44n, P) * b44) % P;
      const b223 = (pow2$1(b220, _3n, P) * b3) % P;
      const t1 = (pow2$1(b223, _23n, P) * b22) % P;
      const t2 = (pow2$1(t1, _6n, P) * b2) % P;
      const root = pow2$1(t2, _2n$3, P);
      if (!Fpk1.eql(Fpk1.sqr(root), y))
          throw new Error('Cannot find square root');
      return root;
  }
  const Fpk1 = Field$1(secp256k1_CURVE.p, { sqrt: sqrtMod$1 });
  /**
   * secp256k1 curve, ECDSA and ECDH methods.
   *
   * Field: `2n**256n - 2n**32n - 2n**9n - 2n**8n - 2n**7n - 2n**6n - 2n**4n - 1n`
   *
   * @example
   * ```js
   * import { secp256k1 } from '@noble/curves/secp256k1';
   * const { secretKey, publicKey } = secp256k1.keygen();
   * const msg = new TextEncoder().encode('hello');
   * const sig = secp256k1.sign(msg, secretKey);
   * const isValid = secp256k1.verify(sig, msg, publicKey) === true;
   * ```
   */
  const secp256k1$1 = createCurve$1({ ...secp256k1_CURVE, Fp: Fpk1, lowS: true, endo: secp256k1_ENDO }, sha256$2);

  function decodeShard(data) {
    const { pubShard, nextOffset } = readPubShard(data);
    const expectedLength = nextOffset + 32 + 33;
    if (data.length !== expectedLength) {
      throw new Error(`invalid shard length: got ${data.length}, expected ${expectedLength}`);
    }
    const secret = bytesToNumberBE$1(data.subarray(nextOffset, nextOffset + 32));
    const pubkey = readPoint(data, nextOffset + 32);
    return {
      secret,
      pubkey,
      pubShard
    };
  }
  function readPubShard(data) {
    if (data.length < 39) {
      throw new Error("invalid shard length");
    }
    const dv = new DataView(data.buffer, data.byteOffset, data.byteLength);
    const id = dv.getUint16(0, true);
    const commitCount = dv.getUint32(2, true);
    const expectedLength = 6 + 33 + 33 * commitCount;
    if (data.length < expectedLength) {
      throw new Error(`invalid shard length: got ${data.length}, need at least ${expectedLength}`);
    }
    const pubkey = readPoint(data, 6);
    const vssCommit = new Array(commitCount);
    for(let i = 0; i < commitCount; i++){
      vssCommit[i] = readPoint(data, 6 + 33 + i * 33);
    }
    return {
      pubShard: {
        id,
        pubkey,
        vssCommit
      },
      nextOffset: expectedLength
    };
  }
  function readPoint(data, offset) {
    const pointBytes = data.subarray(offset, offset + 33);
    if (pointBytes.length !== 33) {
      throw new Error("invalid point length");
    }
    return secp256k1$1.ProjectivePoint.fromHex(bytesToHex$2(pointBytes)).toAffine();
  }

  function hexShard(shard) {
    return bytesToHex$2(encodeShard(shard));
  }
  function encodeShard(shard) {
    const out = new Uint8Array(6 + 33 + 33 * shard.pubShard.vssCommit.length + 32 + 33);
    writePubShardTo(out, shard.pubShard);
    out.set(numberToBytesBE$1(shard.secret, 32), 6 + 33 + shard.pubShard.vssCommit.length * 33);
    writePointTo(out, 6 + 33 + shard.pubShard.vssCommit.length * 33 + 32, shard.pubkey);
    return out;
  }
  function hexPubShard(pubShard) {
    return bytesToHex$2(encodePubShard(pubShard));
  }
  function encodePubShard(pubShard) {
    const out = new Uint8Array(6 + 33 + 33 * pubShard.vssCommit.length);
    writePubShardTo(out, pubShard);
    return out;
  }
  function writePubShardTo(out, pubShard) {
    const dv = new DataView(out.buffer);
    dv.setUint16(0, pubShard.id, true);
    dv.setUint32(2, pubShard.vssCommit.length, true);
    writePointTo(out, 6, pubShard.pubkey);
    for(let i = 0; i < pubShard.vssCommit.length; i++){
      const c = pubShard.vssCommit[i];
      writePointTo(out, 6 + 33 + i * 33, c);
    }
  }
  function writePointTo(out, offset, pt) {
    if ((pt.y & 1n) === 1n) {
      // odd
      out[offset] = 3;
    } else {
      // event
      out[offset] = 2;
    }
    const xBytes = numberToBytesBE$1(pt.x, 32);
    out.set(xBytes, offset + 1);
  }

  const N = secp256k1$1.CURVE.n;
  function aggregateSecretKeyShards(shards) {
    if (shards.length === 0) {
      throw new Error("no secret key shards provided");
    }
    const participants = new Array(shards.length);
    for(let i = 0; i < shards.length; i++){
      const shard = shards[i];
      if (shard.pubShard.id === 0) {
        throw new Error(`secret key shard ${i} has zero identifier`);
      }
      if (shard.secret === 0n) {
        throw new Error(`secret key shard ${i} is nil or zero`);
      }
      participants[i] = shard.pubShard.id;
    }
    let res = 0n;
    for (const shard of shards){
      res = mod$1(res + mod$1(computeLambda(shard.pubShard.id, participants) * shard.secret));
    }
    if (res === 0n) {
      throw new Error("recovered secret key is zero");
    }
    return res;
  }
  function computeLambda(id, participants) {
    let numerator = 1n;
    let denominator = 1n;
    for (const part of participants){
      if (part === id) continue;
      numerator = mod$1(numerator * BigInt(part));
      denominator = mod$1(denominator * mod$1(BigInt(part) - BigInt(id)));
    }
    return mod$1(numerator * invert$1(denominator));
  }
  function mod$1(n) {
    const v = n % N;
    return v >= 0n ? v : v + N;
  }
  function invert$1(n) {
    let a = mod$1(n);
    let b = N;
    let x = 1n;
    let y = 0n;
    while(b !== 0n){
      const q = a / b;
      [a, b] = [
        b,
        a % b
      ];
      [x, y] = [
        y,
        x - q * y
      ];
    }
    if (a !== 1n) {
      throw new Error("value has no modular inverse");
    }
    return mod$1(x);
  }

  const G = secp256k1$1.ProjectivePoint.BASE;
  function trustedKeyDeal(secret, threshold, maxSigners) {
    let pubkey = G.multiplyUnsafe(secret).toAffine();
    if ((pubkey.y & 1n) === 1n) {
      secret = secp256k1$1.CURVE.n - secret;
      pubkey = G.multiplyUnsafe(secret).toAffine();
    }
    if (threshold > maxSigners || threshold <= 0) {
      throw new Error("invalid number of signers or threshold");
    }
    const polynomial = makePolynomial(secret, threshold);
    // evaluate the polynomial for each point x=1,...,n
    const shards = [];
    for(let i = 0; i < maxSigners; i++){
      const id = i + 1;
      const yi = evaluatePolynomial(polynomial, BigInt(id));
      const pksh = G.multiplyUnsafe(yi).toAffine();
      shards.push({
        secret: yi,
        pubkey: pubkey,
        pubShard: {
          pubkey: pksh,
          vssCommit: [],
          id: id
        }
      });
    }
    const commits = vssCommit(polynomial);
    return {
      shards,
      pubkey,
      commits
    };
  }
  function makePolynomial(secret, threshold) {
    const polynomial = [];
    let i = 0;
    polynomial[0] = secret;
    i++;
    for(; i < threshold; i++){
      const b = secp256k1$1.utils.randomPrivateKey();
      polynomial[i] = secp256k1$1.utils.normPrivateKeyToScalar(b);
    }
    return polynomial;
  }
  function vssCommit(polynomial) {
    const commits = [];
    for(let p = 0; p < polynomial.length; p++){
      const coeff = polynomial[p];
      const pt = G.multiplyUnsafe(coeff);
      commits.push(pt.toAffine());
    }
    return commits;
  }
  function evaluatePolynomial(polynomial, x) {
    // since value is an accumulator and starts with 0, we can skip multiplying by x, and start from the end
    let value = polynomial[polynomial.length - 1];
    for(let i = polynomial.length - 2; i >= 0; i--){
      value = (value * x % secp256k1$1.CURVE.n + polynomial[i]) % secp256k1$1.CURVE.n;
    }
    return value;
  }

  function number$1(n) {
      if (!Number.isSafeInteger(n) || n < 0)
          throw new Error(`Wrong positive integer: ${n}`);
  }
  function bytes$1(b, ...lengths) {
      if (!(b instanceof Uint8Array))
          throw new Error('Expected Uint8Array');
      if (lengths.length > 0 && !lengths.includes(b.length))
          throw new Error(`Expected Uint8Array of length ${lengths}, not of length=${b.length}`);
  }
  function hash$1(hash) {
      if (typeof hash !== 'function' || typeof hash.create !== 'function')
          throw new Error('Hash should be wrapped by utils.wrapConstructor');
      number$1(hash.outputLen);
      number$1(hash.blockLen);
  }
  function exists$1(instance, checkFinished = true) {
      if (instance.destroyed)
          throw new Error('Hash instance has been destroyed');
      if (checkFinished && instance.finished)
          throw new Error('Hash#digest() has already been called');
  }
  function output$1(out, instance) {
      bytes$1(out);
      const min = instance.outputLen;
      if (out.length < min) {
          throw new Error(`digestInto() expects output buffer of length at least ${min}`);
      }
  }

  const crypto$1 = typeof globalThis === 'object' && 'crypto' in globalThis ? globalThis.crypto : undefined;

  /*! noble-hashes - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // We use WebCrypto aka globalThis.crypto, which exists in browsers and node.js 16+.
  // node.js versions earlier than v19 don't declare it in global scope.
  // For node.js, package.json#exports field mapping rewrites import
  // from `crypto` to `cryptoNode`, which imports native module.
  // Makes the utils un-importable in browsers without a bundler.
  // Once node.js 18 is deprecated, we can just drop the import.
  const u8a$2 = (a) => a instanceof Uint8Array;
  // Cast array to view
  const createView$1 = (arr) => new DataView(arr.buffer, arr.byteOffset, arr.byteLength);
  // The rotate right (circular right shift) operation for uint32
  const rotr$1 = (word, shift) => (word << (32 - shift)) | (word >>> shift);
  // big-endian hardware is rare. Just in case someone still decides to run hashes:
  // early-throw an error because we don't support BE yet.
  const isLE$1 = new Uint8Array(new Uint32Array([0x11223344]).buffer)[0] === 0x44;
  if (!isLE$1)
      throw new Error('Non little-endian hardware is not supported');
  /**
   * @example utf8ToBytes('abc') // new Uint8Array([97, 98, 99])
   */
  function utf8ToBytes$1(str) {
      if (typeof str !== 'string')
          throw new Error(`utf8ToBytes expected string, got ${typeof str}`);
      return new Uint8Array(new TextEncoder().encode(str)); // https://bugzil.la/1681809
  }
  /**
   * Normalizes (non-hex) string or Uint8Array to Uint8Array.
   * Warning: when Uint8Array is passed, it would NOT get copied.
   * Keep in mind for future mutable operations.
   */
  function toBytes$1(data) {
      if (typeof data === 'string')
          data = utf8ToBytes$1(data);
      if (!u8a$2(data))
          throw new Error(`expected Uint8Array, got ${typeof data}`);
      return data;
  }
  /**
   * Copies several Uint8Arrays into one.
   */
  function concatBytes$1(...arrays) {
      const r = new Uint8Array(arrays.reduce((sum, a) => sum + a.length, 0));
      let pad = 0; // walk through each item, ensure they have proper type
      arrays.forEach((a) => {
          if (!u8a$2(a))
              throw new Error('Uint8Array expected');
          r.set(a, pad);
          pad += a.length;
      });
      return r;
  }
  // For runtime check if class implements interface
  let Hash$1 = class Hash {
      // Safe version that clones internal state
      clone() {
          return this._cloneInto();
      }
  };
  function wrapConstructor$1(hashCons) {
      const hashC = (msg) => hashCons().update(toBytes$1(msg)).digest();
      const tmp = hashCons();
      hashC.outputLen = tmp.outputLen;
      hashC.blockLen = tmp.blockLen;
      hashC.create = () => hashCons();
      return hashC;
  }
  /**
   * Secure PRNG. Uses `crypto.getRandomValues`, which defers to OS.
   */
  function randomBytes(bytesLength = 32) {
      if (crypto$1 && typeof crypto$1.getRandomValues === 'function') {
          return crypto$1.getRandomValues(new Uint8Array(bytesLength));
      }
      throw new Error('crypto.getRandomValues must be defined');
  }

  // Polyfill for Safari 14
  function setBigUint64$1(view, byteOffset, value, isLE) {
      if (typeof view.setBigUint64 === 'function')
          return view.setBigUint64(byteOffset, value, isLE);
      const _32n = BigInt(32);
      const _u32_max = BigInt(0xffffffff);
      const wh = Number((value >> _32n) & _u32_max);
      const wl = Number(value & _u32_max);
      const h = isLE ? 4 : 0;
      const l = isLE ? 0 : 4;
      view.setUint32(byteOffset + h, wh, isLE);
      view.setUint32(byteOffset + l, wl, isLE);
  }
  // Base SHA2 class (RFC 6234)
  let SHA2$1 = class SHA2 extends Hash$1 {
      constructor(blockLen, outputLen, padOffset, isLE) {
          super();
          this.blockLen = blockLen;
          this.outputLen = outputLen;
          this.padOffset = padOffset;
          this.isLE = isLE;
          this.finished = false;
          this.length = 0;
          this.pos = 0;
          this.destroyed = false;
          this.buffer = new Uint8Array(blockLen);
          this.view = createView$1(this.buffer);
      }
      update(data) {
          exists$1(this);
          const { view, buffer, blockLen } = this;
          data = toBytes$1(data);
          const len = data.length;
          for (let pos = 0; pos < len;) {
              const take = Math.min(blockLen - this.pos, len - pos);
              // Fast path: we have at least one block in input, cast it to view and process
              if (take === blockLen) {
                  const dataView = createView$1(data);
                  for (; blockLen <= len - pos; pos += blockLen)
                      this.process(dataView, pos);
                  continue;
              }
              buffer.set(data.subarray(pos, pos + take), this.pos);
              this.pos += take;
              pos += take;
              if (this.pos === blockLen) {
                  this.process(view, 0);
                  this.pos = 0;
              }
          }
          this.length += data.length;
          this.roundClean();
          return this;
      }
      digestInto(out) {
          exists$1(this);
          output$1(out, this);
          this.finished = true;
          // Padding
          // We can avoid allocation of buffer for padding completely if it
          // was previously not allocated here. But it won't change performance.
          const { buffer, view, blockLen, isLE } = this;
          let { pos } = this;
          // append the bit '1' to the message
          buffer[pos++] = 0b10000000;
          this.buffer.subarray(pos).fill(0);
          // we have less than padOffset left in buffer, so we cannot put length in current block, need process it and pad again
          if (this.padOffset > blockLen - pos) {
              this.process(view, 0);
              pos = 0;
          }
          // Pad until full block byte with zeros
          for (let i = pos; i < blockLen; i++)
              buffer[i] = 0;
          // Note: sha512 requires length to be 128bit integer, but length in JS will overflow before that
          // You need to write around 2 exabytes (u64_max / 8 / (1024**6)) for this to happen.
          // So we just write lowest 64 bits of that value.
          setBigUint64$1(view, blockLen - 8, BigInt(this.length * 8), isLE);
          this.process(view, 0);
          const oview = createView$1(out);
          const len = this.outputLen;
          // NOTE: we do division by 4 later, which should be fused in single op with modulo by JIT
          if (len % 4)
              throw new Error('_sha2: outputLen should be aligned to 32bit');
          const outLen = len / 4;
          const state = this.get();
          if (outLen > state.length)
              throw new Error('_sha2: outputLen bigger than state');
          for (let i = 0; i < outLen; i++)
              oview.setUint32(4 * i, state[i], isLE);
      }
      digest() {
          const { buffer, outputLen } = this;
          this.digestInto(buffer);
          const res = buffer.slice(0, outputLen);
          this.destroy();
          return res;
      }
      _cloneInto(to) {
          to || (to = new this.constructor());
          to.set(...this.get());
          const { blockLen, buffer, length, finished, destroyed, pos } = this;
          to.length = length;
          to.pos = pos;
          to.finished = finished;
          to.destroyed = destroyed;
          if (length % blockLen)
              to.buffer.set(buffer);
          return to;
      }
  };

  // SHA2-256 need to try 2^128 hashes to execute birthday attack.
  // BTC network is doing 2^67 hashes/sec as per early 2023.
  // Choice: a ? b : c
  const Chi$1 = (a, b, c) => (a & b) ^ (~a & c);
  // Majority function, true if any two inpust is true
  const Maj$1 = (a, b, c) => (a & b) ^ (a & c) ^ (b & c);
  // Round constants:
  // first 32 bits of the fractional parts of the cube roots of the first 64 primes 2..311)
  // prettier-ignore
  const SHA256_K$1 = /* @__PURE__ */ new Uint32Array([
      0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
      0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
      0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
      0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
      0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
      0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
      0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
      0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
  ]);
  // Initial state (first 32 bits of the fractional parts of the square roots of the first 8 primes 2..19):
  // prettier-ignore
  const IV$1 = /* @__PURE__ */ new Uint32Array([
      0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19
  ]);
  // Temporary buffer, not used to store anything between runs
  // Named this way because it matches specification.
  const SHA256_W$1 = /* @__PURE__ */ new Uint32Array(64);
  let SHA256$1 = class SHA256 extends SHA2$1 {
      constructor() {
          super(64, 32, 8, false);
          // We cannot use array here since array allows indexing by variable
          // which means optimizer/compiler cannot use registers.
          this.A = IV$1[0] | 0;
          this.B = IV$1[1] | 0;
          this.C = IV$1[2] | 0;
          this.D = IV$1[3] | 0;
          this.E = IV$1[4] | 0;
          this.F = IV$1[5] | 0;
          this.G = IV$1[6] | 0;
          this.H = IV$1[7] | 0;
      }
      get() {
          const { A, B, C, D, E, F, G, H } = this;
          return [A, B, C, D, E, F, G, H];
      }
      // prettier-ignore
      set(A, B, C, D, E, F, G, H) {
          this.A = A | 0;
          this.B = B | 0;
          this.C = C | 0;
          this.D = D | 0;
          this.E = E | 0;
          this.F = F | 0;
          this.G = G | 0;
          this.H = H | 0;
      }
      process(view, offset) {
          // Extend the first 16 words into the remaining 48 words w[16..63] of the message schedule array
          for (let i = 0; i < 16; i++, offset += 4)
              SHA256_W$1[i] = view.getUint32(offset, false);
          for (let i = 16; i < 64; i++) {
              const W15 = SHA256_W$1[i - 15];
              const W2 = SHA256_W$1[i - 2];
              const s0 = rotr$1(W15, 7) ^ rotr$1(W15, 18) ^ (W15 >>> 3);
              const s1 = rotr$1(W2, 17) ^ rotr$1(W2, 19) ^ (W2 >>> 10);
              SHA256_W$1[i] = (s1 + SHA256_W$1[i - 7] + s0 + SHA256_W$1[i - 16]) | 0;
          }
          // Compression function main loop, 64 rounds
          let { A, B, C, D, E, F, G, H } = this;
          for (let i = 0; i < 64; i++) {
              const sigma1 = rotr$1(E, 6) ^ rotr$1(E, 11) ^ rotr$1(E, 25);
              const T1 = (H + sigma1 + Chi$1(E, F, G) + SHA256_K$1[i] + SHA256_W$1[i]) | 0;
              const sigma0 = rotr$1(A, 2) ^ rotr$1(A, 13) ^ rotr$1(A, 22);
              const T2 = (sigma0 + Maj$1(A, B, C)) | 0;
              H = G;
              G = F;
              F = E;
              E = (D + T1) | 0;
              D = C;
              C = B;
              B = A;
              A = (T1 + T2) | 0;
          }
          // Add the compressed chunk to the current hash value
          A = (A + this.A) | 0;
          B = (B + this.B) | 0;
          C = (C + this.C) | 0;
          D = (D + this.D) | 0;
          E = (E + this.E) | 0;
          F = (F + this.F) | 0;
          G = (G + this.G) | 0;
          H = (H + this.H) | 0;
          this.set(A, B, C, D, E, F, G, H);
      }
      roundClean() {
          SHA256_W$1.fill(0);
      }
      destroy() {
          this.set(0, 0, 0, 0, 0, 0, 0, 0);
          this.buffer.fill(0);
      }
  };
  /**
   * SHA2-256 hash function
   * @param message - data that would be hashed
   */
  const sha256$1 = /* @__PURE__ */ wrapConstructor$1(() => new SHA256$1());

  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // 100 lines of code in the file are duplicated from noble-hashes (utils).
  // This is OK: `abstract` directory does not use noble-hashes.
  // User may opt-in into using different hashing library. This way, noble-hashes
  // won't be included into their bundle.
  BigInt(0);
  const _1n$4 = BigInt(1);
  const _2n$2 = BigInt(2);
  const u8a$1 = (a) => a instanceof Uint8Array;
  const hexes$1 = /* @__PURE__ */ Array.from({ length: 256 }, (_, i) => i.toString(16).padStart(2, '0'));
  /**
   * @example bytesToHex(Uint8Array.from([0xca, 0xfe, 0x01, 0x23])) // 'cafe0123'
   */
  function bytesToHex$1(bytes) {
      if (!u8a$1(bytes))
          throw new Error('Uint8Array expected');
      // pre-caching improves the speed 6x
      let hex = '';
      for (let i = 0; i < bytes.length; i++) {
          hex += hexes$1[bytes[i]];
      }
      return hex;
  }
  function hexToNumber(hex) {
      if (typeof hex !== 'string')
          throw new Error('hex string expected, got ' + typeof hex);
      // Big Endian
      return BigInt(hex === '' ? '0' : `0x${hex}`);
  }
  /**
   * @example hexToBytes('cafe0123') // Uint8Array.from([0xca, 0xfe, 0x01, 0x23])
   */
  function hexToBytes(hex) {
      if (typeof hex !== 'string')
          throw new Error('hex string expected, got ' + typeof hex);
      const len = hex.length;
      if (len % 2)
          throw new Error('padded hex string expected, got unpadded hex of length ' + len);
      const array = new Uint8Array(len / 2);
      for (let i = 0; i < array.length; i++) {
          const j = i * 2;
          const hexByte = hex.slice(j, j + 2);
          const byte = Number.parseInt(hexByte, 16);
          if (Number.isNaN(byte) || byte < 0)
              throw new Error('Invalid byte sequence');
          array[i] = byte;
      }
      return array;
  }
  // BE: Big Endian, LE: Little Endian
  function bytesToNumberBE(bytes) {
      return hexToNumber(bytesToHex$1(bytes));
  }
  function bytesToNumberLE(bytes) {
      if (!u8a$1(bytes))
          throw new Error('Uint8Array expected');
      return hexToNumber(bytesToHex$1(Uint8Array.from(bytes).reverse()));
  }
  function numberToBytesBE(n, len) {
      return hexToBytes(n.toString(16).padStart(len * 2, '0'));
  }
  function numberToBytesLE(n, len) {
      return numberToBytesBE(n, len).reverse();
  }
  /**
   * Takes hex string or Uint8Array, converts to Uint8Array.
   * Validates output length.
   * Will throw error for other types.
   * @param title descriptive title for an error e.g. 'private key'
   * @param hex hex string or Uint8Array
   * @param expectedLength optional, will compare to result array's length
   * @returns
   */
  function ensureBytes(title, hex, expectedLength) {
      let res;
      if (typeof hex === 'string') {
          try {
              res = hexToBytes(hex);
          }
          catch (e) {
              throw new Error(`${title} must be valid hex string, got "${hex}". Cause: ${e}`);
          }
      }
      else if (u8a$1(hex)) {
          // Uint8Array.from() instead of hash.slice() because node.js Buffer
          // is instance of Uint8Array, and its slice() creates **mutable** copy
          res = Uint8Array.from(hex);
      }
      else {
          throw new Error(`${title} must be hex string or Uint8Array`);
      }
      const len = res.length;
      if (typeof expectedLength === 'number' && len !== expectedLength)
          throw new Error(`${title} expected ${expectedLength} bytes, got ${len}`);
      return res;
  }
  /**
   * Copies several Uint8Arrays into one.
   */
  function concatBytes(...arrays) {
      const r = new Uint8Array(arrays.reduce((sum, a) => sum + a.length, 0));
      let pad = 0; // walk through each item, ensure they have proper type
      arrays.forEach((a) => {
          if (!u8a$1(a))
              throw new Error('Uint8Array expected');
          r.set(a, pad);
          pad += a.length;
      });
      return r;
  }
  /**
   * Calculate mask for N bits. Not using ** operator with bigints because of old engines.
   * Same as BigInt(`0b${Array(i).fill('1').join('')}`)
   */
  const bitMask = (n) => (_2n$2 << BigInt(n - 1)) - _1n$4;
  // DRBG
  const u8n = (data) => new Uint8Array(data); // creates Uint8Array
  const u8fr = (arr) => Uint8Array.from(arr); // another shortcut
  /**
   * Minimal HMAC-DRBG from NIST 800-90 for RFC6979 sigs.
   * @returns function that will call DRBG until 2nd arg returns something meaningful
   * @example
   *   const drbg = createHmacDRBG<Key>(32, 32, hmac);
   *   drbg(seed, bytesToKey); // bytesToKey must return Key or undefined
   */
  function createHmacDrbg(hashLen, qByteLen, hmacFn) {
      if (typeof hashLen !== 'number' || hashLen < 2)
          throw new Error('hashLen must be a number');
      if (typeof qByteLen !== 'number' || qByteLen < 2)
          throw new Error('qByteLen must be a number');
      if (typeof hmacFn !== 'function')
          throw new Error('hmacFn must be a function');
      // Step B, Step C: set hashLen to 8*ceil(hlen/8)
      let v = u8n(hashLen); // Minimal non-full-spec HMAC-DRBG from NIST 800-90 for RFC6979 sigs.
      let k = u8n(hashLen); // Steps B and C of RFC6979 3.2: set hashLen, in our case always same
      let i = 0; // Iterations counter, will throw when over 1000
      const reset = () => {
          v.fill(1);
          k.fill(0);
          i = 0;
      };
      const h = (...b) => hmacFn(k, v, ...b); // hmac(k)(v, ...values)
      const reseed = (seed = u8n()) => {
          // HMAC-DRBG reseed() function. Steps D-G
          k = h(u8fr([0x00]), seed); // k = hmac(k || v || 0x00 || seed)
          v = h(); // v = hmac(k || v)
          if (seed.length === 0)
              return;
          k = h(u8fr([0x01]), seed); // k = hmac(k || v || 0x01 || seed)
          v = h(); // v = hmac(k || v)
      };
      const gen = () => {
          // HMAC-DRBG generate() function
          if (i++ >= 1000)
              throw new Error('drbg: tried 1000 values');
          let len = 0;
          const out = [];
          while (len < qByteLen) {
              v = h();
              const sl = v.slice();
              out.push(sl);
              len += v.length;
          }
          return concatBytes(...out);
      };
      const genUntil = (seed, pred) => {
          reset();
          reseed(seed); // Steps D-G
          let res = undefined; // Step H: grind until k is in [1..n-1]
          while (!(res = pred(gen())))
              reseed();
          reset();
          return res;
      };
      return genUntil;
  }
  // Validating curves and fields
  const validatorFns = {
      bigint: (val) => typeof val === 'bigint',
      function: (val) => typeof val === 'function',
      boolean: (val) => typeof val === 'boolean',
      string: (val) => typeof val === 'string',
      stringOrUint8Array: (val) => typeof val === 'string' || val instanceof Uint8Array,
      isSafeInteger: (val) => Number.isSafeInteger(val),
      array: (val) => Array.isArray(val),
      field: (val, object) => object.Fp.isValid(val),
      hash: (val) => typeof val === 'function' && Number.isSafeInteger(val.outputLen),
  };
  // type Record<K extends string | number | symbol, T> = { [P in K]: T; }
  function validateObject(object, validators, optValidators = {}) {
      const checkField = (fieldName, type, isOptional) => {
          const checkVal = validatorFns[type];
          if (typeof checkVal !== 'function')
              throw new Error(`Invalid validator "${type}", expected function`);
          const val = object[fieldName];
          if (isOptional && val === undefined)
              return;
          if (!checkVal(val, object)) {
              throw new Error(`Invalid param ${String(fieldName)}=${val} (${typeof val}), expected ${type}`);
          }
      };
      for (const [fieldName, type] of Object.entries(validators))
          checkField(fieldName, type, false);
      for (const [fieldName, type] of Object.entries(optValidators))
          checkField(fieldName, type, true);
      return object;
  }
  // validate type tests
  // const o: { a: number; b: number; c: number } = { a: 1, b: 5, c: 6 };
  // const z0 = validateObject(o, { a: 'isSafeInteger' }, { c: 'bigint' }); // Ok!
  // // Should fail type-check
  // const z1 = validateObject(o, { a: 'tmp' }, { c: 'zz' });
  // const z2 = validateObject(o, { a: 'isSafeInteger' }, { c: 'zz' });
  // const z3 = validateObject(o, { test: 'boolean', z: 'bug' });
  // const z4 = validateObject(o, { a: 'boolean', z: 'bug' });

  var ut = /*#__PURE__*/Object.freeze({
    __proto__: null,
    bitMask: bitMask,
    bytesToHex: bytesToHex$1,
    bytesToNumberBE: bytesToNumberBE,
    bytesToNumberLE: bytesToNumberLE,
    concatBytes: concatBytes,
    createHmacDrbg: createHmacDrbg,
    ensureBytes: ensureBytes,
    hexToBytes: hexToBytes,
    hexToNumber: hexToNumber,
    numberToBytesBE: numberToBytesBE,
    numberToBytesLE: numberToBytesLE,
    validateObject: validateObject
  });

  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // Utilities for modular arithmetics and finite fields
  // prettier-ignore
  const _0n$3 = BigInt(0), _1n$3 = BigInt(1), _2n$1 = BigInt(2), _3n$1 = BigInt(3);
  // prettier-ignore
  const _4n = BigInt(4), _5n = BigInt(5), _8n = BigInt(8);
  // prettier-ignore
  BigInt(9); BigInt(16);
  // Calculates a modulo b
  function mod(a, b) {
      const result = a % b;
      return result >= _0n$3 ? result : b + result;
  }
  /**
   * Efficiently raise num to power and do modular division.
   * Unsafe in some contexts: uses ladder, so can expose bigint bits.
   * @example
   * pow(2n, 6n, 11n) // 64n % 11n == 9n
   */
  // TODO: use field version && remove
  function pow(num, power, modulo) {
      if (modulo <= _0n$3 || power < _0n$3)
          throw new Error('Expected power/modulo > 0');
      if (modulo === _1n$3)
          return _0n$3;
      let res = _1n$3;
      while (power > _0n$3) {
          if (power & _1n$3)
              res = (res * num) % modulo;
          num = (num * num) % modulo;
          power >>= _1n$3;
      }
      return res;
  }
  // Does x ^ (2 ^ power) mod p. pow2(30, 4) == 30 ^ (2 ^ 4)
  function pow2(x, power, modulo) {
      let res = x;
      while (power-- > _0n$3) {
          res *= res;
          res %= modulo;
      }
      return res;
  }
  // Inverses number over modulo
  function invert(number, modulo) {
      if (number === _0n$3 || modulo <= _0n$3) {
          throw new Error(`invert: expected positive integers, got n=${number} mod=${modulo}`);
      }
      // Euclidean GCD https://brilliant.org/wiki/extended-euclidean-algorithm/
      // Fermat's little theorem "CT-like" version inv(n) = n^(m-2) mod m is 30x slower.
      let a = mod(number, modulo);
      let b = modulo;
      // prettier-ignore
      let x = _0n$3, u = _1n$3;
      while (a !== _0n$3) {
          // JIT applies optimization if those two lines follow each other
          const q = b / a;
          const r = b % a;
          const m = x - u * q;
          // prettier-ignore
          b = a, a = r, x = u, u = m;
      }
      const gcd = b;
      if (gcd !== _1n$3)
          throw new Error('invert: does not exist');
      return mod(x, modulo);
  }
  /**
   * Tonelli-Shanks square root search algorithm.
   * 1. https://eprint.iacr.org/2012/685.pdf (page 12)
   * 2. Square Roots from 1; 24, 51, 10 to Dan Shanks
   * Will start an infinite loop if field order P is not prime.
   * @param P field order
   * @returns function that takes field Fp (created from P) and number n
   */
  function tonelliShanks(P) {
      // Legendre constant: used to calculate Legendre symbol (a | p),
      // which denotes the value of a^((p-1)/2) (mod p).
      // (a | p) ≡ 1    if a is a square (mod p)
      // (a | p) ≡ -1   if a is not a square (mod p)
      // (a | p) ≡ 0    if a ≡ 0 (mod p)
      const legendreC = (P - _1n$3) / _2n$1;
      let Q, S, Z;
      // Step 1: By factoring out powers of 2 from p - 1,
      // find q and s such that p - 1 = q*(2^s) with q odd
      for (Q = P - _1n$3, S = 0; Q % _2n$1 === _0n$3; Q /= _2n$1, S++)
          ;
      // Step 2: Select a non-square z such that (z | p) ≡ -1 and set c ≡ zq
      for (Z = _2n$1; Z < P && pow(Z, legendreC, P) !== P - _1n$3; Z++)
          ;
      // Fast-path
      if (S === 1) {
          const p1div4 = (P + _1n$3) / _4n;
          return function tonelliFast(Fp, n) {
              const root = Fp.pow(n, p1div4);
              if (!Fp.eql(Fp.sqr(root), n))
                  throw new Error('Cannot find square root');
              return root;
          };
      }
      // Slow-path
      const Q1div2 = (Q + _1n$3) / _2n$1;
      return function tonelliSlow(Fp, n) {
          // Step 0: Check that n is indeed a square: (n | p) should not be ≡ -1
          if (Fp.pow(n, legendreC) === Fp.neg(Fp.ONE))
              throw new Error('Cannot find square root');
          let r = S;
          // TODO: will fail at Fp2/etc
          let g = Fp.pow(Fp.mul(Fp.ONE, Z), Q); // will update both x and b
          let x = Fp.pow(n, Q1div2); // first guess at the square root
          let b = Fp.pow(n, Q); // first guess at the fudge factor
          while (!Fp.eql(b, Fp.ONE)) {
              if (Fp.eql(b, Fp.ZERO))
                  return Fp.ZERO; // https://en.wikipedia.org/wiki/Tonelli%E2%80%93Shanks_algorithm (4. If t = 0, return r = 0)
              // Find m such b^(2^m)==1
              let m = 1;
              for (let t2 = Fp.sqr(b); m < r; m++) {
                  if (Fp.eql(t2, Fp.ONE))
                      break;
                  t2 = Fp.sqr(t2); // t2 *= t2
              }
              // NOTE: r-m-1 can be bigger than 32, need to convert to bigint before shift, otherwise there will be overflow
              const ge = Fp.pow(g, _1n$3 << BigInt(r - m - 1)); // ge = 2^(r-m-1)
              g = Fp.sqr(ge); // g = ge * ge
              x = Fp.mul(x, ge); // x *= ge
              b = Fp.mul(b, g); // b *= g
              r = m;
          }
          return x;
      };
  }
  function FpSqrt(P) {
      // NOTE: different algorithms can give different roots, it is up to user to decide which one they want.
      // For example there is FpSqrtOdd/FpSqrtEven to choice root based on oddness (used for hash-to-curve).
      // P ≡ 3 (mod 4)
      // √n = n^((P+1)/4)
      if (P % _4n === _3n$1) {
          // Not all roots possible!
          // const ORDER =
          //   0x1a0111ea397fe69a4b1ba7b6434bacd764774b84f38512bf6730d2a0f6b0f6241eabfffeb153ffffb9feffffffffaaabn;
          // const NUM = 72057594037927816n;
          const p1div4 = (P + _1n$3) / _4n;
          return function sqrt3mod4(Fp, n) {
              const root = Fp.pow(n, p1div4);
              // Throw if root**2 != n
              if (!Fp.eql(Fp.sqr(root), n))
                  throw new Error('Cannot find square root');
              return root;
          };
      }
      // Atkin algorithm for q ≡ 5 (mod 8), https://eprint.iacr.org/2012/685.pdf (page 10)
      if (P % _8n === _5n) {
          const c1 = (P - _5n) / _8n;
          return function sqrt5mod8(Fp, n) {
              const n2 = Fp.mul(n, _2n$1);
              const v = Fp.pow(n2, c1);
              const nv = Fp.mul(n, v);
              const i = Fp.mul(Fp.mul(nv, _2n$1), v);
              const root = Fp.mul(nv, Fp.sub(i, Fp.ONE));
              if (!Fp.eql(Fp.sqr(root), n))
                  throw new Error('Cannot find square root');
              return root;
          };
      }
      // Other cases: Tonelli-Shanks algorithm
      return tonelliShanks(P);
  }
  // prettier-ignore
  const FIELD_FIELDS = [
      'create', 'isValid', 'is0', 'neg', 'inv', 'sqrt', 'sqr',
      'eql', 'add', 'sub', 'mul', 'pow', 'div',
      'addN', 'subN', 'mulN', 'sqrN'
  ];
  function validateField(field) {
      const initial = {
          ORDER: 'bigint',
          MASK: 'bigint',
          BYTES: 'isSafeInteger',
          BITS: 'isSafeInteger',
      };
      const opts = FIELD_FIELDS.reduce((map, val) => {
          map[val] = 'function';
          return map;
      }, initial);
      return validateObject(field, opts);
  }
  // Generic field functions
  /**
   * Same as `pow` but for Fp: non-constant-time.
   * Unsafe in some contexts: uses ladder, so can expose bigint bits.
   */
  function FpPow(f, num, power) {
      // Should have same speed as pow for bigints
      // TODO: benchmark!
      if (power < _0n$3)
          throw new Error('Expected power > 0');
      if (power === _0n$3)
          return f.ONE;
      if (power === _1n$3)
          return num;
      let p = f.ONE;
      let d = num;
      while (power > _0n$3) {
          if (power & _1n$3)
              p = f.mul(p, d);
          d = f.sqr(d);
          power >>= _1n$3;
      }
      return p;
  }
  /**
   * Efficiently invert an array of Field elements.
   * `inv(0)` will return `undefined` here: make sure to throw an error.
   */
  function FpInvertBatch(f, nums) {
      const tmp = new Array(nums.length);
      // Walk from first to last, multiply them by each other MOD p
      const lastMultiplied = nums.reduce((acc, num, i) => {
          if (f.is0(num))
              return acc;
          tmp[i] = acc;
          return f.mul(acc, num);
      }, f.ONE);
      // Invert last element
      const inverted = f.inv(lastMultiplied);
      // Walk from last to first, multiply them by inverted each other MOD p
      nums.reduceRight((acc, num, i) => {
          if (f.is0(num))
              return acc;
          tmp[i] = f.mul(acc, tmp[i]);
          return f.mul(acc, num);
      }, inverted);
      return tmp;
  }
  // CURVE.n lengths
  function nLength(n, nBitLength) {
      // Bit size, byte size of CURVE.n
      const _nBitLength = nBitLength !== undefined ? nBitLength : n.toString(2).length;
      const nByteLength = Math.ceil(_nBitLength / 8);
      return { nBitLength: _nBitLength, nByteLength };
  }
  /**
   * Initializes a finite field over prime. **Non-primes are not supported.**
   * Do not init in loop: slow. Very fragile: always run a benchmark on a change.
   * Major performance optimizations:
   * * a) denormalized operations like mulN instead of mul
   * * b) same object shape: never add or remove keys
   * * c) Object.freeze
   * @param ORDER prime positive bigint
   * @param bitLen how many bits the field consumes
   * @param isLE (def: false) if encoding / decoding should be in little-endian
   * @param redef optional faster redefinitions of sqrt and other methods
   */
  function Field(ORDER, bitLen, isLE = false, redef = {}) {
      if (ORDER <= _0n$3)
          throw new Error(`Expected Field ORDER > 0, got ${ORDER}`);
      const { nBitLength: BITS, nByteLength: BYTES } = nLength(ORDER, bitLen);
      if (BYTES > 2048)
          throw new Error('Field lengths over 2048 bytes are not supported');
      const sqrtP = FpSqrt(ORDER);
      const f = Object.freeze({
          ORDER,
          BITS,
          BYTES,
          MASK: bitMask(BITS),
          ZERO: _0n$3,
          ONE: _1n$3,
          create: (num) => mod(num, ORDER),
          isValid: (num) => {
              if (typeof num !== 'bigint')
                  throw new Error(`Invalid field element: expected bigint, got ${typeof num}`);
              return _0n$3 <= num && num < ORDER; // 0 is valid element, but it's not invertible
          },
          is0: (num) => num === _0n$3,
          isOdd: (num) => (num & _1n$3) === _1n$3,
          neg: (num) => mod(-num, ORDER),
          eql: (lhs, rhs) => lhs === rhs,
          sqr: (num) => mod(num * num, ORDER),
          add: (lhs, rhs) => mod(lhs + rhs, ORDER),
          sub: (lhs, rhs) => mod(lhs - rhs, ORDER),
          mul: (lhs, rhs) => mod(lhs * rhs, ORDER),
          pow: (num, power) => FpPow(f, num, power),
          div: (lhs, rhs) => mod(lhs * invert(rhs, ORDER), ORDER),
          // Same as above, but doesn't normalize
          sqrN: (num) => num * num,
          addN: (lhs, rhs) => lhs + rhs,
          subN: (lhs, rhs) => lhs - rhs,
          mulN: (lhs, rhs) => lhs * rhs,
          inv: (num) => invert(num, ORDER),
          sqrt: redef.sqrt || ((n) => sqrtP(f, n)),
          invertBatch: (lst) => FpInvertBatch(f, lst),
          // TODO: do we really need constant cmov?
          // We don't have const-time bigints anyway, so probably will be not very useful
          cmov: (a, b, c) => (c ? b : a),
          toBytes: (num) => (isLE ? numberToBytesLE(num, BYTES) : numberToBytesBE(num, BYTES)),
          fromBytes: (bytes) => {
              if (bytes.length !== BYTES)
                  throw new Error(`Fp.fromBytes: expected ${BYTES}, got ${bytes.length}`);
              return isLE ? bytesToNumberLE(bytes) : bytesToNumberBE(bytes);
          },
      });
      return Object.freeze(f);
  }
  /**
   * Returns total number of bytes consumed by the field element.
   * For example, 32 bytes for usual 256-bit weierstrass curve.
   * @param fieldOrder number of field elements, usually CURVE.n
   * @returns byte length of field
   */
  function getFieldBytesLength(fieldOrder) {
      if (typeof fieldOrder !== 'bigint')
          throw new Error('field order must be bigint');
      const bitLength = fieldOrder.toString(2).length;
      return Math.ceil(bitLength / 8);
  }
  /**
   * Returns minimal amount of bytes that can be safely reduced
   * by field order.
   * Should be 2^-128 for 128-bit curve such as P256.
   * @param fieldOrder number of field elements, usually CURVE.n
   * @returns byte length of target hash
   */
  function getMinHashLength(fieldOrder) {
      const length = getFieldBytesLength(fieldOrder);
      return length + Math.ceil(length / 2);
  }
  /**
   * "Constant-time" private key generation utility.
   * Can take (n + n/2) or more bytes of uniform input e.g. from CSPRNG or KDF
   * and convert them into private scalar, with the modulo bias being negligible.
   * Needs at least 48 bytes of input for 32-byte private key.
   * https://research.kudelskisecurity.com/2020/07/28/the-definitive-guide-to-modulo-bias-and-how-to-avoid-it/
   * FIPS 186-5, A.2 https://csrc.nist.gov/publications/detail/fips/186/5/final
   * RFC 9380, https://www.rfc-editor.org/rfc/rfc9380#section-5
   * @param hash hash output from SHA3 or a similar function
   * @param groupOrder size of subgroup - (e.g. secp256k1.CURVE.n)
   * @param isLE interpret hash bytes as LE num
   * @returns valid private scalar
   */
  function mapHashToField(key, fieldOrder, isLE = false) {
      const len = key.length;
      const fieldLen = getFieldBytesLength(fieldOrder);
      const minLen = getMinHashLength(fieldOrder);
      // No small numbers: need to understand bias story. No huge numbers: easier to detect JS timings.
      if (len < 16 || len < minLen || len > 1024)
          throw new Error(`expected ${minLen}-1024 bytes of input, got ${len}`);
      const num = isLE ? bytesToNumberBE(key) : bytesToNumberLE(key);
      // `mod(x, 11)` can sometimes produce 0. `mod(x, 10) + 1` is the same, but no 0
      const reduced = mod(num, fieldOrder - _1n$3) + _1n$3;
      return isLE ? numberToBytesLE(reduced, fieldLen) : numberToBytesBE(reduced, fieldLen);
  }

  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // Abelian group utilities
  const _0n$2 = BigInt(0);
  const _1n$2 = BigInt(1);
  // Elliptic curve multiplication of Point by scalar. Fragile.
  // Scalars should always be less than curve order: this should be checked inside of a curve itself.
  // Creates precomputation tables for fast multiplication:
  // - private scalar is split by fixed size windows of W bits
  // - every window point is collected from window's table & added to accumulator
  // - since windows are different, same point inside tables won't be accessed more than once per calc
  // - each multiplication is 'Math.ceil(CURVE_ORDER / 𝑊) + 1' point additions (fixed for any scalar)
  // - +1 window is neccessary for wNAF
  // - wNAF reduces table size: 2x less memory + 2x faster generation, but 10% slower multiplication
  // TODO: Research returning 2d JS array of windows, instead of a single window. This would allow
  // windows to be in different memory locations
  function wNAF(c, bits) {
      const constTimeNegate = (condition, item) => {
          const neg = item.negate();
          return condition ? neg : item;
      };
      const opts = (W) => {
          const windows = Math.ceil(bits / W) + 1; // +1, because
          const windowSize = 2 ** (W - 1); // -1 because we skip zero
          return { windows, windowSize };
      };
      return {
          constTimeNegate,
          // non-const time multiplication ladder
          unsafeLadder(elm, n) {
              let p = c.ZERO;
              let d = elm;
              while (n > _0n$2) {
                  if (n & _1n$2)
                      p = p.add(d);
                  d = d.double();
                  n >>= _1n$2;
              }
              return p;
          },
          /**
           * Creates a wNAF precomputation window. Used for caching.
           * Default window size is set by `utils.precompute()` and is equal to 8.
           * Number of precomputed points depends on the curve size:
           * 2^(𝑊−1) * (Math.ceil(𝑛 / 𝑊) + 1), where:
           * - 𝑊 is the window size
           * - 𝑛 is the bitlength of the curve order.
           * For a 256-bit curve and window size 8, the number of precomputed points is 128 * 33 = 4224.
           * @returns precomputed point tables flattened to a single array
           */
          precomputeWindow(elm, W) {
              const { windows, windowSize } = opts(W);
              const points = [];
              let p = elm;
              let base = p;
              for (let window = 0; window < windows; window++) {
                  base = p;
                  points.push(base);
                  // =1, because we skip zero
                  for (let i = 1; i < windowSize; i++) {
                      base = base.add(p);
                      points.push(base);
                  }
                  p = base.double();
              }
              return points;
          },
          /**
           * Implements ec multiplication using precomputed tables and w-ary non-adjacent form.
           * @param W window size
           * @param precomputes precomputed tables
           * @param n scalar (we don't check here, but should be less than curve order)
           * @returns real and fake (for const-time) points
           */
          wNAF(W, precomputes, n) {
              // TODO: maybe check that scalar is less than group order? wNAF behavious is undefined otherwise
              // But need to carefully remove other checks before wNAF. ORDER == bits here
              const { windows, windowSize } = opts(W);
              let p = c.ZERO;
              let f = c.BASE;
              const mask = BigInt(2 ** W - 1); // Create mask with W ones: 0b1111 for W=4 etc.
              const maxNumber = 2 ** W;
              const shiftBy = BigInt(W);
              for (let window = 0; window < windows; window++) {
                  const offset = window * windowSize;
                  // Extract W bits.
                  let wbits = Number(n & mask);
                  // Shift number by W bits.
                  n >>= shiftBy;
                  // If the bits are bigger than max size, we'll split those.
                  // +224 => 256 - 32
                  if (wbits > windowSize) {
                      wbits -= maxNumber;
                      n += _1n$2;
                  }
                  // This code was first written with assumption that 'f' and 'p' will never be infinity point:
                  // since each addition is multiplied by 2 ** W, it cannot cancel each other. However,
                  // there is negate now: it is possible that negated element from low value
                  // would be the same as high element, which will create carry into next window.
                  // It's not obvious how this can fail, but still worth investigating later.
                  // Check if we're onto Zero point.
                  // Add random point inside current window to f.
                  const offset1 = offset;
                  const offset2 = offset + Math.abs(wbits) - 1; // -1 because we skip zero
                  const cond1 = window % 2 !== 0;
                  const cond2 = wbits < 0;
                  if (wbits === 0) {
                      // The most important part for const-time getPublicKey
                      f = f.add(constTimeNegate(cond1, precomputes[offset1]));
                  }
                  else {
                      p = p.add(constTimeNegate(cond2, precomputes[offset2]));
                  }
              }
              // JIT-compiler should not eliminate f here, since it will later be used in normalizeZ()
              // Even if the variable is still unused, there are some checks which will
              // throw an exception, so compiler needs to prove they won't happen, which is hard.
              // At this point there is a way to F be infinity-point even if p is not,
              // which makes it less const-time: around 1 bigint multiply.
              return { p, f };
          },
          wNAFCached(P, precomputesMap, n, transform) {
              // @ts-ignore
              const W = P._WINDOW_SIZE || 1;
              // Calculate precomputes on a first run, reuse them after
              let comp = precomputesMap.get(P);
              if (!comp) {
                  comp = this.precomputeWindow(P, W);
                  if (W !== 1) {
                      precomputesMap.set(P, transform(comp));
                  }
              }
              return this.wNAF(W, comp, n);
          },
      };
  }
  function validateBasic(curve) {
      validateField(curve.Fp);
      validateObject(curve, {
          n: 'bigint',
          h: 'bigint',
          Gx: 'field',
          Gy: 'field',
      }, {
          nBitLength: 'isSafeInteger',
          nByteLength: 'isSafeInteger',
      });
      // Set defaults
      return Object.freeze({
          ...nLength(curve.n, curve.nBitLength),
          ...curve,
          ...{ p: curve.Fp.ORDER },
      });
  }

  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // Short Weierstrass curve. The formula is: y² = x³ + ax + b
  function validatePointOpts(curve) {
      const opts = validateBasic(curve);
      validateObject(opts, {
          a: 'field',
          b: 'field',
      }, {
          allowedPrivateKeyLengths: 'array',
          wrapPrivateKey: 'boolean',
          isTorsionFree: 'function',
          clearCofactor: 'function',
          allowInfinityPoint: 'boolean',
          fromBytes: 'function',
          toBytes: 'function',
      });
      const { endo, Fp, a } = opts;
      if (endo) {
          if (!Fp.eql(a, Fp.ZERO)) {
              throw new Error('Endomorphism can only be defined for Koblitz curves that have a=0');
          }
          if (typeof endo !== 'object' ||
              typeof endo.beta !== 'bigint' ||
              typeof endo.splitScalar !== 'function') {
              throw new Error('Expected endomorphism with beta: bigint and splitScalar: function');
          }
      }
      return Object.freeze({ ...opts });
  }
  // ASN.1 DER encoding utilities
  const { bytesToNumberBE: b2n, hexToBytes: h2b } = ut;
  const DER = {
      // asn.1 DER encoding utils
      Err: class DERErr extends Error {
          constructor(m = '') {
              super(m);
          }
      },
      _parseInt(data) {
          const { Err: E } = DER;
          if (data.length < 2 || data[0] !== 0x02)
              throw new E('Invalid signature integer tag');
          const len = data[1];
          const res = data.subarray(2, len + 2);
          if (!len || res.length !== len)
              throw new E('Invalid signature integer: wrong length');
          // https://crypto.stackexchange.com/a/57734 Leftmost bit of first byte is 'negative' flag,
          // since we always use positive integers here. It must always be empty:
          // - add zero byte if exists
          // - if next byte doesn't have a flag, leading zero is not allowed (minimal encoding)
          if (res[0] & 0b10000000)
              throw new E('Invalid signature integer: negative');
          if (res[0] === 0x00 && !(res[1] & 0b10000000))
              throw new E('Invalid signature integer: unnecessary leading zero');
          return { d: b2n(res), l: data.subarray(len + 2) }; // d is data, l is left
      },
      toSig(hex) {
          // parse DER signature
          const { Err: E } = DER;
          const data = typeof hex === 'string' ? h2b(hex) : hex;
          if (!(data instanceof Uint8Array))
              throw new Error('ui8a expected');
          let l = data.length;
          if (l < 2 || data[0] != 0x30)
              throw new E('Invalid signature tag');
          if (data[1] !== l - 2)
              throw new E('Invalid signature: incorrect length');
          const { d: r, l: sBytes } = DER._parseInt(data.subarray(2));
          const { d: s, l: rBytesLeft } = DER._parseInt(sBytes);
          if (rBytesLeft.length)
              throw new E('Invalid signature: left bytes after parsing');
          return { r, s };
      },
      hexFromSig(sig) {
          // Add leading zero if first byte has negative bit enabled. More details in '_parseInt'
          const slice = (s) => (Number.parseInt(s[0], 16) & 0b1000 ? '00' + s : s);
          const h = (num) => {
              const hex = num.toString(16);
              return hex.length & 1 ? `0${hex}` : hex;
          };
          const s = slice(h(sig.s));
          const r = slice(h(sig.r));
          const shl = s.length / 2;
          const rhl = r.length / 2;
          const sl = h(shl);
          const rl = h(rhl);
          return `30${h(rhl + shl + 4)}02${rl}${r}02${sl}${s}`;
      },
  };
  // Be friendly to bad ECMAScript parsers by not using bigint literals
  // prettier-ignore
  const _0n$1 = BigInt(0), _1n$1 = BigInt(1); BigInt(2); const _3n = BigInt(3); BigInt(4);
  function weierstrassPoints(opts) {
      const CURVE = validatePointOpts(opts);
      const { Fp } = CURVE; // All curves has same field / group length as for now, but they can differ
      const toBytes = CURVE.toBytes ||
          ((_c, point, _isCompressed) => {
              const a = point.toAffine();
              return concatBytes(Uint8Array.from([0x04]), Fp.toBytes(a.x), Fp.toBytes(a.y));
          });
      const fromBytes = CURVE.fromBytes ||
          ((bytes) => {
              // const head = bytes[0];
              const tail = bytes.subarray(1);
              // if (head !== 0x04) throw new Error('Only non-compressed encoding is supported');
              const x = Fp.fromBytes(tail.subarray(0, Fp.BYTES));
              const y = Fp.fromBytes(tail.subarray(Fp.BYTES, 2 * Fp.BYTES));
              return { x, y };
          });
      /**
       * y² = x³ + ax + b: Short weierstrass curve formula
       * @returns y²
       */
      function weierstrassEquation(x) {
          const { a, b } = CURVE;
          const x2 = Fp.sqr(x); // x * x
          const x3 = Fp.mul(x2, x); // x2 * x
          return Fp.add(Fp.add(x3, Fp.mul(x, a)), b); // x3 + a * x + b
      }
      // Validate whether the passed curve params are valid.
      // We check if curve equation works for generator point.
      // `assertValidity()` won't work: `isTorsionFree()` is not available at this point in bls12-381.
      // ProjectivePoint class has not been initialized yet.
      if (!Fp.eql(Fp.sqr(CURVE.Gy), weierstrassEquation(CURVE.Gx)))
          throw new Error('bad generator point: equation left != right');
      // Valid group elements reside in range 1..n-1
      function isWithinCurveOrder(num) {
          return typeof num === 'bigint' && _0n$1 < num && num < CURVE.n;
      }
      function assertGE(num) {
          if (!isWithinCurveOrder(num))
              throw new Error('Expected valid bigint: 0 < bigint < curve.n');
      }
      // Validates if priv key is valid and converts it to bigint.
      // Supports options allowedPrivateKeyLengths and wrapPrivateKey.
      function normPrivateKeyToScalar(key) {
          const { allowedPrivateKeyLengths: lengths, nByteLength, wrapPrivateKey, n } = CURVE;
          if (lengths && typeof key !== 'bigint') {
              if (key instanceof Uint8Array)
                  key = bytesToHex$1(key);
              // Normalize to hex string, pad. E.g. P521 would norm 130-132 char hex to 132-char bytes
              if (typeof key !== 'string' || !lengths.includes(key.length))
                  throw new Error('Invalid key');
              key = key.padStart(nByteLength * 2, '0');
          }
          let num;
          try {
              num =
                  typeof key === 'bigint'
                      ? key
                      : bytesToNumberBE(ensureBytes('private key', key, nByteLength));
          }
          catch (error) {
              throw new Error(`private key must be ${nByteLength} bytes, hex or bigint, not ${typeof key}`);
          }
          if (wrapPrivateKey)
              num = mod(num, n); // disabled by default, enabled for BLS
          assertGE(num); // num in range [1..N-1]
          return num;
      }
      const pointPrecomputes = new Map();
      function assertPrjPoint(other) {
          if (!(other instanceof Point))
              throw new Error('ProjectivePoint expected');
      }
      /**
       * Projective Point works in 3d / projective (homogeneous) coordinates: (x, y, z) ∋ (x=x/z, y=y/z)
       * Default Point works in 2d / affine coordinates: (x, y)
       * We're doing calculations in projective, because its operations don't require costly inversion.
       */
      class Point {
          constructor(px, py, pz) {
              this.px = px;
              this.py = py;
              this.pz = pz;
              if (px == null || !Fp.isValid(px))
                  throw new Error('x required');
              if (py == null || !Fp.isValid(py))
                  throw new Error('y required');
              if (pz == null || !Fp.isValid(pz))
                  throw new Error('z required');
          }
          // Does not validate if the point is on-curve.
          // Use fromHex instead, or call assertValidity() later.
          static fromAffine(p) {
              const { x, y } = p || {};
              if (!p || !Fp.isValid(x) || !Fp.isValid(y))
                  throw new Error('invalid affine point');
              if (p instanceof Point)
                  throw new Error('projective point not allowed');
              const is0 = (i) => Fp.eql(i, Fp.ZERO);
              // fromAffine(x:0, y:0) would produce (x:0, y:0, z:1), but we need (x:0, y:1, z:0)
              if (is0(x) && is0(y))
                  return Point.ZERO;
              return new Point(x, y, Fp.ONE);
          }
          get x() {
              return this.toAffine().x;
          }
          get y() {
              return this.toAffine().y;
          }
          /**
           * Takes a bunch of Projective Points but executes only one
           * inversion on all of them. Inversion is very slow operation,
           * so this improves performance massively.
           * Optimization: converts a list of projective points to a list of identical points with Z=1.
           */
          static normalizeZ(points) {
              const toInv = Fp.invertBatch(points.map((p) => p.pz));
              return points.map((p, i) => p.toAffine(toInv[i])).map(Point.fromAffine);
          }
          /**
           * Converts hash string or Uint8Array to Point.
           * @param hex short/long ECDSA hex
           */
          static fromHex(hex) {
              const P = Point.fromAffine(fromBytes(ensureBytes('pointHex', hex)));
              P.assertValidity();
              return P;
          }
          // Multiplies generator point by privateKey.
          static fromPrivateKey(privateKey) {
              return Point.BASE.multiply(normPrivateKeyToScalar(privateKey));
          }
          // "Private method", don't use it directly
          _setWindowSize(windowSize) {
              this._WINDOW_SIZE = windowSize;
              pointPrecomputes.delete(this);
          }
          // A point on curve is valid if it conforms to equation.
          assertValidity() {
              if (this.is0()) {
                  // (0, 1, 0) aka ZERO is invalid in most contexts.
                  // In BLS, ZERO can be serialized, so we allow it.
                  // (0, 0, 0) is wrong representation of ZERO and is always invalid.
                  if (CURVE.allowInfinityPoint && !Fp.is0(this.py))
                      return;
                  throw new Error('bad point: ZERO');
              }
              // Some 3rd-party test vectors require different wording between here & `fromCompressedHex`
              const { x, y } = this.toAffine();
              // Check if x, y are valid field elements
              if (!Fp.isValid(x) || !Fp.isValid(y))
                  throw new Error('bad point: x or y not FE');
              const left = Fp.sqr(y); // y²
              const right = weierstrassEquation(x); // x³ + ax + b
              if (!Fp.eql(left, right))
                  throw new Error('bad point: equation left != right');
              if (!this.isTorsionFree())
                  throw new Error('bad point: not in prime-order subgroup');
          }
          hasEvenY() {
              const { y } = this.toAffine();
              if (Fp.isOdd)
                  return !Fp.isOdd(y);
              throw new Error("Field doesn't support isOdd");
          }
          /**
           * Compare one point to another.
           */
          equals(other) {
              assertPrjPoint(other);
              const { px: X1, py: Y1, pz: Z1 } = this;
              const { px: X2, py: Y2, pz: Z2 } = other;
              const U1 = Fp.eql(Fp.mul(X1, Z2), Fp.mul(X2, Z1));
              const U2 = Fp.eql(Fp.mul(Y1, Z2), Fp.mul(Y2, Z1));
              return U1 && U2;
          }
          /**
           * Flips point to one corresponding to (x, -y) in Affine coordinates.
           */
          negate() {
              return new Point(this.px, Fp.neg(this.py), this.pz);
          }
          // Renes-Costello-Batina exception-free doubling formula.
          // There is 30% faster Jacobian formula, but it is not complete.
          // https://eprint.iacr.org/2015/1060, algorithm 3
          // Cost: 8M + 3S + 3*a + 2*b3 + 15add.
          double() {
              const { a, b } = CURVE;
              const b3 = Fp.mul(b, _3n);
              const { px: X1, py: Y1, pz: Z1 } = this;
              let X3 = Fp.ZERO, Y3 = Fp.ZERO, Z3 = Fp.ZERO; // prettier-ignore
              let t0 = Fp.mul(X1, X1); // step 1
              let t1 = Fp.mul(Y1, Y1);
              let t2 = Fp.mul(Z1, Z1);
              let t3 = Fp.mul(X1, Y1);
              t3 = Fp.add(t3, t3); // step 5
              Z3 = Fp.mul(X1, Z1);
              Z3 = Fp.add(Z3, Z3);
              X3 = Fp.mul(a, Z3);
              Y3 = Fp.mul(b3, t2);
              Y3 = Fp.add(X3, Y3); // step 10
              X3 = Fp.sub(t1, Y3);
              Y3 = Fp.add(t1, Y3);
              Y3 = Fp.mul(X3, Y3);
              X3 = Fp.mul(t3, X3);
              Z3 = Fp.mul(b3, Z3); // step 15
              t2 = Fp.mul(a, t2);
              t3 = Fp.sub(t0, t2);
              t3 = Fp.mul(a, t3);
              t3 = Fp.add(t3, Z3);
              Z3 = Fp.add(t0, t0); // step 20
              t0 = Fp.add(Z3, t0);
              t0 = Fp.add(t0, t2);
              t0 = Fp.mul(t0, t3);
              Y3 = Fp.add(Y3, t0);
              t2 = Fp.mul(Y1, Z1); // step 25
              t2 = Fp.add(t2, t2);
              t0 = Fp.mul(t2, t3);
              X3 = Fp.sub(X3, t0);
              Z3 = Fp.mul(t2, t1);
              Z3 = Fp.add(Z3, Z3); // step 30
              Z3 = Fp.add(Z3, Z3);
              return new Point(X3, Y3, Z3);
          }
          // Renes-Costello-Batina exception-free addition formula.
          // There is 30% faster Jacobian formula, but it is not complete.
          // https://eprint.iacr.org/2015/1060, algorithm 1
          // Cost: 12M + 0S + 3*a + 3*b3 + 23add.
          add(other) {
              assertPrjPoint(other);
              const { px: X1, py: Y1, pz: Z1 } = this;
              const { px: X2, py: Y2, pz: Z2 } = other;
              let X3 = Fp.ZERO, Y3 = Fp.ZERO, Z3 = Fp.ZERO; // prettier-ignore
              const a = CURVE.a;
              const b3 = Fp.mul(CURVE.b, _3n);
              let t0 = Fp.mul(X1, X2); // step 1
              let t1 = Fp.mul(Y1, Y2);
              let t2 = Fp.mul(Z1, Z2);
              let t3 = Fp.add(X1, Y1);
              let t4 = Fp.add(X2, Y2); // step 5
              t3 = Fp.mul(t3, t4);
              t4 = Fp.add(t0, t1);
              t3 = Fp.sub(t3, t4);
              t4 = Fp.add(X1, Z1);
              let t5 = Fp.add(X2, Z2); // step 10
              t4 = Fp.mul(t4, t5);
              t5 = Fp.add(t0, t2);
              t4 = Fp.sub(t4, t5);
              t5 = Fp.add(Y1, Z1);
              X3 = Fp.add(Y2, Z2); // step 15
              t5 = Fp.mul(t5, X3);
              X3 = Fp.add(t1, t2);
              t5 = Fp.sub(t5, X3);
              Z3 = Fp.mul(a, t4);
              X3 = Fp.mul(b3, t2); // step 20
              Z3 = Fp.add(X3, Z3);
              X3 = Fp.sub(t1, Z3);
              Z3 = Fp.add(t1, Z3);
              Y3 = Fp.mul(X3, Z3);
              t1 = Fp.add(t0, t0); // step 25
              t1 = Fp.add(t1, t0);
              t2 = Fp.mul(a, t2);
              t4 = Fp.mul(b3, t4);
              t1 = Fp.add(t1, t2);
              t2 = Fp.sub(t0, t2); // step 30
              t2 = Fp.mul(a, t2);
              t4 = Fp.add(t4, t2);
              t0 = Fp.mul(t1, t4);
              Y3 = Fp.add(Y3, t0);
              t0 = Fp.mul(t5, t4); // step 35
              X3 = Fp.mul(t3, X3);
              X3 = Fp.sub(X3, t0);
              t0 = Fp.mul(t3, t1);
              Z3 = Fp.mul(t5, Z3);
              Z3 = Fp.add(Z3, t0); // step 40
              return new Point(X3, Y3, Z3);
          }
          subtract(other) {
              return this.add(other.negate());
          }
          is0() {
              return this.equals(Point.ZERO);
          }
          wNAF(n) {
              return wnaf.wNAFCached(this, pointPrecomputes, n, (comp) => {
                  const toInv = Fp.invertBatch(comp.map((p) => p.pz));
                  return comp.map((p, i) => p.toAffine(toInv[i])).map(Point.fromAffine);
              });
          }
          /**
           * Non-constant-time multiplication. Uses double-and-add algorithm.
           * It's faster, but should only be used when you don't care about
           * an exposed private key e.g. sig verification, which works over *public* keys.
           */
          multiplyUnsafe(n) {
              const I = Point.ZERO;
              if (n === _0n$1)
                  return I;
              assertGE(n); // Will throw on 0
              if (n === _1n$1)
                  return this;
              const { endo } = CURVE;
              if (!endo)
                  return wnaf.unsafeLadder(this, n);
              // Apply endomorphism
              let { k1neg, k1, k2neg, k2 } = endo.splitScalar(n);
              let k1p = I;
              let k2p = I;
              let d = this;
              while (k1 > _0n$1 || k2 > _0n$1) {
                  if (k1 & _1n$1)
                      k1p = k1p.add(d);
                  if (k2 & _1n$1)
                      k2p = k2p.add(d);
                  d = d.double();
                  k1 >>= _1n$1;
                  k2 >>= _1n$1;
              }
              if (k1neg)
                  k1p = k1p.negate();
              if (k2neg)
                  k2p = k2p.negate();
              k2p = new Point(Fp.mul(k2p.px, endo.beta), k2p.py, k2p.pz);
              return k1p.add(k2p);
          }
          /**
           * Constant time multiplication.
           * Uses wNAF method. Windowed method may be 10% faster,
           * but takes 2x longer to generate and consumes 2x memory.
           * Uses precomputes when available.
           * Uses endomorphism for Koblitz curves.
           * @param scalar by which the point would be multiplied
           * @returns New point
           */
          multiply(scalar) {
              assertGE(scalar);
              let n = scalar;
              let point, fake; // Fake point is used to const-time mult
              const { endo } = CURVE;
              if (endo) {
                  const { k1neg, k1, k2neg, k2 } = endo.splitScalar(n);
                  let { p: k1p, f: f1p } = this.wNAF(k1);
                  let { p: k2p, f: f2p } = this.wNAF(k2);
                  k1p = wnaf.constTimeNegate(k1neg, k1p);
                  k2p = wnaf.constTimeNegate(k2neg, k2p);
                  k2p = new Point(Fp.mul(k2p.px, endo.beta), k2p.py, k2p.pz);
                  point = k1p.add(k2p);
                  fake = f1p.add(f2p);
              }
              else {
                  const { p, f } = this.wNAF(n);
                  point = p;
                  fake = f;
              }
              // Normalize `z` for both points, but return only real one
              return Point.normalizeZ([point, fake])[0];
          }
          /**
           * Efficiently calculate `aP + bQ`. Unsafe, can expose private key, if used incorrectly.
           * Not using Strauss-Shamir trick: precomputation tables are faster.
           * The trick could be useful if both P and Q are not G (not in our case).
           * @returns non-zero affine point
           */
          multiplyAndAddUnsafe(Q, a, b) {
              const G = Point.BASE; // No Strauss-Shamir trick: we have 10% faster G precomputes
              const mul = (P, a // Select faster multiply() method
              ) => (a === _0n$1 || a === _1n$1 || !P.equals(G) ? P.multiplyUnsafe(a) : P.multiply(a));
              const sum = mul(this, a).add(mul(Q, b));
              return sum.is0() ? undefined : sum;
          }
          // Converts Projective point to affine (x, y) coordinates.
          // Can accept precomputed Z^-1 - for example, from invertBatch.
          // (x, y, z) ∋ (x=x/z, y=y/z)
          toAffine(iz) {
              const { px: x, py: y, pz: z } = this;
              const is0 = this.is0();
              // If invZ was 0, we return zero point. However we still want to execute
              // all operations, so we replace invZ with a random number, 1.
              if (iz == null)
                  iz = is0 ? Fp.ONE : Fp.inv(z);
              const ax = Fp.mul(x, iz);
              const ay = Fp.mul(y, iz);
              const zz = Fp.mul(z, iz);
              if (is0)
                  return { x: Fp.ZERO, y: Fp.ZERO };
              if (!Fp.eql(zz, Fp.ONE))
                  throw new Error('invZ was invalid');
              return { x: ax, y: ay };
          }
          isTorsionFree() {
              const { h: cofactor, isTorsionFree } = CURVE;
              if (cofactor === _1n$1)
                  return true; // No subgroups, always torsion-free
              if (isTorsionFree)
                  return isTorsionFree(Point, this);
              throw new Error('isTorsionFree() has not been declared for the elliptic curve');
          }
          clearCofactor() {
              const { h: cofactor, clearCofactor } = CURVE;
              if (cofactor === _1n$1)
                  return this; // Fast-path
              if (clearCofactor)
                  return clearCofactor(Point, this);
              return this.multiplyUnsafe(CURVE.h);
          }
          toRawBytes(isCompressed = true) {
              this.assertValidity();
              return toBytes(Point, this, isCompressed);
          }
          toHex(isCompressed = true) {
              return bytesToHex$1(this.toRawBytes(isCompressed));
          }
      }
      Point.BASE = new Point(CURVE.Gx, CURVE.Gy, Fp.ONE);
      Point.ZERO = new Point(Fp.ZERO, Fp.ONE, Fp.ZERO);
      const _bits = CURVE.nBitLength;
      const wnaf = wNAF(Point, CURVE.endo ? Math.ceil(_bits / 2) : _bits);
      // Validate if generator point is on curve
      return {
          CURVE,
          ProjectivePoint: Point,
          normPrivateKeyToScalar,
          weierstrassEquation,
          isWithinCurveOrder,
      };
  }
  function validateOpts(curve) {
      const opts = validateBasic(curve);
      validateObject(opts, {
          hash: 'hash',
          hmac: 'function',
          randomBytes: 'function',
      }, {
          bits2int: 'function',
          bits2int_modN: 'function',
          lowS: 'boolean',
      });
      return Object.freeze({ lowS: true, ...opts });
  }
  function weierstrass(curveDef) {
      const CURVE = validateOpts(curveDef);
      const { Fp, n: CURVE_ORDER } = CURVE;
      const compressedLen = Fp.BYTES + 1; // e.g. 33 for 32
      const uncompressedLen = 2 * Fp.BYTES + 1; // e.g. 65 for 32
      function isValidFieldElement(num) {
          return _0n$1 < num && num < Fp.ORDER; // 0 is banned since it's not invertible FE
      }
      function modN(a) {
          return mod(a, CURVE_ORDER);
      }
      function invN(a) {
          return invert(a, CURVE_ORDER);
      }
      const { ProjectivePoint: Point, normPrivateKeyToScalar, weierstrassEquation, isWithinCurveOrder, } = weierstrassPoints({
          ...CURVE,
          toBytes(_c, point, isCompressed) {
              const a = point.toAffine();
              const x = Fp.toBytes(a.x);
              const cat = concatBytes;
              if (isCompressed) {
                  return cat(Uint8Array.from([point.hasEvenY() ? 0x02 : 0x03]), x);
              }
              else {
                  return cat(Uint8Array.from([0x04]), x, Fp.toBytes(a.y));
              }
          },
          fromBytes(bytes) {
              const len = bytes.length;
              const head = bytes[0];
              const tail = bytes.subarray(1);
              // this.assertValidity() is done inside of fromHex
              if (len === compressedLen && (head === 0x02 || head === 0x03)) {
                  const x = bytesToNumberBE(tail);
                  if (!isValidFieldElement(x))
                      throw new Error('Point is not on curve');
                  const y2 = weierstrassEquation(x); // y² = x³ + ax + b
                  let y = Fp.sqrt(y2); // y = y² ^ (p+1)/4
                  const isYOdd = (y & _1n$1) === _1n$1;
                  // ECDSA
                  const isHeadOdd = (head & 1) === 1;
                  if (isHeadOdd !== isYOdd)
                      y = Fp.neg(y);
                  return { x, y };
              }
              else if (len === uncompressedLen && head === 0x04) {
                  const x = Fp.fromBytes(tail.subarray(0, Fp.BYTES));
                  const y = Fp.fromBytes(tail.subarray(Fp.BYTES, 2 * Fp.BYTES));
                  return { x, y };
              }
              else {
                  throw new Error(`Point of length ${len} was invalid. Expected ${compressedLen} compressed bytes or ${uncompressedLen} uncompressed bytes`);
              }
          },
      });
      const numToNByteStr = (num) => bytesToHex$1(numberToBytesBE(num, CURVE.nByteLength));
      function isBiggerThanHalfOrder(number) {
          const HALF = CURVE_ORDER >> _1n$1;
          return number > HALF;
      }
      function normalizeS(s) {
          return isBiggerThanHalfOrder(s) ? modN(-s) : s;
      }
      // slice bytes num
      const slcNum = (b, from, to) => bytesToNumberBE(b.slice(from, to));
      /**
       * ECDSA signature with its (r, s) properties. Supports DER & compact representations.
       */
      class Signature {
          constructor(r, s, recovery) {
              this.r = r;
              this.s = s;
              this.recovery = recovery;
              this.assertValidity();
          }
          // pair (bytes of r, bytes of s)
          static fromCompact(hex) {
              const l = CURVE.nByteLength;
              hex = ensureBytes('compactSignature', hex, l * 2);
              return new Signature(slcNum(hex, 0, l), slcNum(hex, l, 2 * l));
          }
          // DER encoded ECDSA signature
          // https://bitcoin.stackexchange.com/questions/57644/what-are-the-parts-of-a-bitcoin-transaction-input-script
          static fromDER(hex) {
              const { r, s } = DER.toSig(ensureBytes('DER', hex));
              return new Signature(r, s);
          }
          assertValidity() {
              // can use assertGE here
              if (!isWithinCurveOrder(this.r))
                  throw new Error('r must be 0 < r < CURVE.n');
              if (!isWithinCurveOrder(this.s))
                  throw new Error('s must be 0 < s < CURVE.n');
          }
          addRecoveryBit(recovery) {
              return new Signature(this.r, this.s, recovery);
          }
          recoverPublicKey(msgHash) {
              const { r, s, recovery: rec } = this;
              const h = bits2int_modN(ensureBytes('msgHash', msgHash)); // Truncate hash
              if (rec == null || ![0, 1, 2, 3].includes(rec))
                  throw new Error('recovery id invalid');
              const radj = rec === 2 || rec === 3 ? r + CURVE.n : r;
              if (radj >= Fp.ORDER)
                  throw new Error('recovery id 2 or 3 invalid');
              const prefix = (rec & 1) === 0 ? '02' : '03';
              const R = Point.fromHex(prefix + numToNByteStr(radj));
              const ir = invN(radj); // r^-1
              const u1 = modN(-h * ir); // -hr^-1
              const u2 = modN(s * ir); // sr^-1
              const Q = Point.BASE.multiplyAndAddUnsafe(R, u1, u2); // (sr^-1)R-(hr^-1)G = -(hr^-1)G + (sr^-1)
              if (!Q)
                  throw new Error('point at infinify'); // unsafe is fine: no priv data leaked
              Q.assertValidity();
              return Q;
          }
          // Signatures should be low-s, to prevent malleability.
          hasHighS() {
              return isBiggerThanHalfOrder(this.s);
          }
          normalizeS() {
              return this.hasHighS() ? new Signature(this.r, modN(-this.s), this.recovery) : this;
          }
          // DER-encoded
          toDERRawBytes() {
              return hexToBytes(this.toDERHex());
          }
          toDERHex() {
              return DER.hexFromSig({ r: this.r, s: this.s });
          }
          // padded bytes of r, then padded bytes of s
          toCompactRawBytes() {
              return hexToBytes(this.toCompactHex());
          }
          toCompactHex() {
              return numToNByteStr(this.r) + numToNByteStr(this.s);
          }
      }
      const utils = {
          isValidPrivateKey(privateKey) {
              try {
                  normPrivateKeyToScalar(privateKey);
                  return true;
              }
              catch (error) {
                  return false;
              }
          },
          normPrivateKeyToScalar: normPrivateKeyToScalar,
          /**
           * Produces cryptographically secure private key from random of size
           * (groupLen + ceil(groupLen / 2)) with modulo bias being negligible.
           */
          randomPrivateKey: () => {
              const length = getMinHashLength(CURVE.n);
              return mapHashToField(CURVE.randomBytes(length), CURVE.n);
          },
          /**
           * Creates precompute table for an arbitrary EC point. Makes point "cached".
           * Allows to massively speed-up `point.multiply(scalar)`.
           * @returns cached point
           * @example
           * const fast = utils.precompute(8, ProjectivePoint.fromHex(someonesPubKey));
           * fast.multiply(privKey); // much faster ECDH now
           */
          precompute(windowSize = 8, point = Point.BASE) {
              point._setWindowSize(windowSize);
              point.multiply(BigInt(3)); // 3 is arbitrary, just need any number here
              return point;
          },
      };
      /**
       * Computes public key for a private key. Checks for validity of the private key.
       * @param privateKey private key
       * @param isCompressed whether to return compact (default), or full key
       * @returns Public key, full when isCompressed=false; short when isCompressed=true
       */
      function getPublicKey(privateKey, isCompressed = true) {
          return Point.fromPrivateKey(privateKey).toRawBytes(isCompressed);
      }
      /**
       * Quick and dirty check for item being public key. Does not validate hex, or being on-curve.
       */
      function isProbPub(item) {
          const arr = item instanceof Uint8Array;
          const str = typeof item === 'string';
          const len = (arr || str) && item.length;
          if (arr)
              return len === compressedLen || len === uncompressedLen;
          if (str)
              return len === 2 * compressedLen || len === 2 * uncompressedLen;
          if (item instanceof Point)
              return true;
          return false;
      }
      /**
       * ECDH (Elliptic Curve Diffie Hellman).
       * Computes shared public key from private key and public key.
       * Checks: 1) private key validity 2) shared key is on-curve.
       * Does NOT hash the result.
       * @param privateA private key
       * @param publicB different public key
       * @param isCompressed whether to return compact (default), or full key
       * @returns shared public key
       */
      function getSharedSecret(privateA, publicB, isCompressed = true) {
          if (isProbPub(privateA))
              throw new Error('first arg must be private key');
          if (!isProbPub(publicB))
              throw new Error('second arg must be public key');
          const b = Point.fromHex(publicB); // check for being on-curve
          return b.multiply(normPrivateKeyToScalar(privateA)).toRawBytes(isCompressed);
      }
      // RFC6979: ensure ECDSA msg is X bytes and < N. RFC suggests optional truncating via bits2octets.
      // FIPS 186-4 4.6 suggests the leftmost min(nBitLen, outLen) bits, which matches bits2int.
      // bits2int can produce res>N, we can do mod(res, N) since the bitLen is the same.
      // int2octets can't be used; pads small msgs with 0: unacceptatble for trunc as per RFC vectors
      const bits2int = CURVE.bits2int ||
          function (bytes) {
              // For curves with nBitLength % 8 !== 0: bits2octets(bits2octets(m)) !== bits2octets(m)
              // for some cases, since bytes.length * 8 is not actual bitLength.
              const num = bytesToNumberBE(bytes); // check for == u8 done here
              const delta = bytes.length * 8 - CURVE.nBitLength; // truncate to nBitLength leftmost bits
              return delta > 0 ? num >> BigInt(delta) : num;
          };
      const bits2int_modN = CURVE.bits2int_modN ||
          function (bytes) {
              return modN(bits2int(bytes)); // can't use bytesToNumberBE here
          };
      // NOTE: pads output with zero as per spec
      const ORDER_MASK = bitMask(CURVE.nBitLength);
      /**
       * Converts to bytes. Checks if num in `[0..ORDER_MASK-1]` e.g.: `[0..2^256-1]`.
       */
      function int2octets(num) {
          if (typeof num !== 'bigint')
              throw new Error('bigint expected');
          if (!(_0n$1 <= num && num < ORDER_MASK))
              throw new Error(`bigint expected < 2^${CURVE.nBitLength}`);
          // works with order, can have different size than numToField!
          return numberToBytesBE(num, CURVE.nByteLength);
      }
      // Steps A, D of RFC6979 3.2
      // Creates RFC6979 seed; converts msg/privKey to numbers.
      // Used only in sign, not in verify.
      // NOTE: we cannot assume here that msgHash has same amount of bytes as curve order, this will be wrong at least for P521.
      // Also it can be bigger for P224 + SHA256
      function prepSig(msgHash, privateKey, opts = defaultSigOpts) {
          if (['recovered', 'canonical'].some((k) => k in opts))
              throw new Error('sign() legacy options not supported');
          const { hash, randomBytes } = CURVE;
          let { lowS, prehash, extraEntropy: ent } = opts; // generates low-s sigs by default
          if (lowS == null)
              lowS = true; // RFC6979 3.2: we skip step A, because we already provide hash
          msgHash = ensureBytes('msgHash', msgHash);
          if (prehash)
              msgHash = ensureBytes('prehashed msgHash', hash(msgHash));
          // We can't later call bits2octets, since nested bits2int is broken for curves
          // with nBitLength % 8 !== 0. Because of that, we unwrap it here as int2octets call.
          // const bits2octets = (bits) => int2octets(bits2int_modN(bits))
          const h1int = bits2int_modN(msgHash);
          const d = normPrivateKeyToScalar(privateKey); // validate private key, convert to bigint
          const seedArgs = [int2octets(d), int2octets(h1int)];
          // extraEntropy. RFC6979 3.6: additional k' (optional).
          if (ent != null) {
              // K = HMAC_K(V || 0x00 || int2octets(x) || bits2octets(h1) || k')
              const e = ent === true ? randomBytes(Fp.BYTES) : ent; // generate random bytes OR pass as-is
              seedArgs.push(ensureBytes('extraEntropy', e)); // check for being bytes
          }
          const seed = concatBytes(...seedArgs); // Step D of RFC6979 3.2
          const m = h1int; // NOTE: no need to call bits2int second time here, it is inside truncateHash!
          // Converts signature params into point w r/s, checks result for validity.
          function k2sig(kBytes) {
              // RFC 6979 Section 3.2, step 3: k = bits2int(T)
              const k = bits2int(kBytes); // Cannot use fields methods, since it is group element
              if (!isWithinCurveOrder(k))
                  return; // Important: all mod() calls here must be done over N
              const ik = invN(k); // k^-1 mod n
              const q = Point.BASE.multiply(k).toAffine(); // q = Gk
              const r = modN(q.x); // r = q.x mod n
              if (r === _0n$1)
                  return;
              // Can use scalar blinding b^-1(bm + bdr) where b ∈ [1,q−1] according to
              // https://tches.iacr.org/index.php/TCHES/article/view/7337/6509. We've decided against it:
              // a) dependency on CSPRNG b) 15% slowdown c) doesn't really help since bigints are not CT
              const s = modN(ik * modN(m + r * d)); // Not using blinding here
              if (s === _0n$1)
                  return;
              let recovery = (q.x === r ? 0 : 2) | Number(q.y & _1n$1); // recovery bit (2 or 3, when q.x > n)
              let normS = s;
              if (lowS && isBiggerThanHalfOrder(s)) {
                  normS = normalizeS(s); // if lowS was passed, ensure s is always
                  recovery ^= 1; // // in the bottom half of N
              }
              return new Signature(r, normS, recovery); // use normS, not s
          }
          return { seed, k2sig };
      }
      const defaultSigOpts = { lowS: CURVE.lowS, prehash: false };
      const defaultVerOpts = { lowS: CURVE.lowS, prehash: false };
      /**
       * Signs message hash with a private key.
       * ```
       * sign(m, d, k) where
       *   (x, y) = G × k
       *   r = x mod n
       *   s = (m + dr)/k mod n
       * ```
       * @param msgHash NOT message. msg needs to be hashed to `msgHash`, or use `prehash`.
       * @param privKey private key
       * @param opts lowS for non-malleable sigs. extraEntropy for mixing randomness into k. prehash will hash first arg.
       * @returns signature with recovery param
       */
      function sign(msgHash, privKey, opts = defaultSigOpts) {
          const { seed, k2sig } = prepSig(msgHash, privKey, opts); // Steps A, D of RFC6979 3.2.
          const C = CURVE;
          const drbg = createHmacDrbg(C.hash.outputLen, C.nByteLength, C.hmac);
          return drbg(seed, k2sig); // Steps B, C, D, E, F, G
      }
      // Enable precomputes. Slows down first publicKey computation by 20ms.
      Point.BASE._setWindowSize(8);
      // utils.precompute(8, ProjectivePoint.BASE)
      /**
       * Verifies a signature against message hash and public key.
       * Rejects lowS signatures by default: to override,
       * specify option `{lowS: false}`. Implements section 4.1.4 from https://www.secg.org/sec1-v2.pdf:
       *
       * ```
       * verify(r, s, h, P) where
       *   U1 = hs^-1 mod n
       *   U2 = rs^-1 mod n
       *   R = U1⋅G - U2⋅P
       *   mod(R.x, n) == r
       * ```
       */
      function verify(signature, msgHash, publicKey, opts = defaultVerOpts) {
          const sg = signature;
          msgHash = ensureBytes('msgHash', msgHash);
          publicKey = ensureBytes('publicKey', publicKey);
          if ('strict' in opts)
              throw new Error('options.strict was renamed to lowS');
          const { lowS, prehash } = opts;
          let _sig = undefined;
          let P;
          try {
              if (typeof sg === 'string' || sg instanceof Uint8Array) {
                  // Signature can be represented in 2 ways: compact (2*nByteLength) & DER (variable-length).
                  // Since DER can also be 2*nByteLength bytes, we check for it first.
                  try {
                      _sig = Signature.fromDER(sg);
                  }
                  catch (derError) {
                      if (!(derError instanceof DER.Err))
                          throw derError;
                      _sig = Signature.fromCompact(sg);
                  }
              }
              else if (typeof sg === 'object' && typeof sg.r === 'bigint' && typeof sg.s === 'bigint') {
                  const { r, s } = sg;
                  _sig = new Signature(r, s);
              }
              else {
                  throw new Error('PARSE');
              }
              P = Point.fromHex(publicKey);
          }
          catch (error) {
              if (error.message === 'PARSE')
                  throw new Error(`signature must be Signature instance, Uint8Array or hex string`);
              return false;
          }
          if (lowS && _sig.hasHighS())
              return false;
          if (prehash)
              msgHash = CURVE.hash(msgHash);
          const { r, s } = _sig;
          const h = bits2int_modN(msgHash); // Cannot use fields methods, since it is group element
          const is = invN(s); // s^-1
          const u1 = modN(h * is); // u1 = hs^-1 mod n
          const u2 = modN(r * is); // u2 = rs^-1 mod n
          const R = Point.BASE.multiplyAndAddUnsafe(P, u1, u2)?.toAffine(); // R = u1⋅G + u2⋅P
          if (!R)
              return false;
          const v = modN(R.x);
          return v === r;
      }
      return {
          CURVE,
          getPublicKey,
          getSharedSecret,
          sign,
          verify,
          ProjectivePoint: Point,
          Signature,
          utils,
      };
  }

  // HMAC (RFC 2104)
  class HMAC extends Hash$1 {
      constructor(hash, _key) {
          super();
          this.finished = false;
          this.destroyed = false;
          hash$1(hash);
          const key = toBytes$1(_key);
          this.iHash = hash.create();
          if (typeof this.iHash.update !== 'function')
              throw new Error('Expected instance of class which extends utils.Hash');
          this.blockLen = this.iHash.blockLen;
          this.outputLen = this.iHash.outputLen;
          const blockLen = this.blockLen;
          const pad = new Uint8Array(blockLen);
          // blockLen can be bigger than outputLen
          pad.set(key.length > blockLen ? hash.create().update(key).digest() : key);
          for (let i = 0; i < pad.length; i++)
              pad[i] ^= 0x36;
          this.iHash.update(pad);
          // By doing update (processing of first block) of outer hash here we can re-use it between multiple calls via clone
          this.oHash = hash.create();
          // Undo internal XOR && apply outer XOR
          for (let i = 0; i < pad.length; i++)
              pad[i] ^= 0x36 ^ 0x5c;
          this.oHash.update(pad);
          pad.fill(0);
      }
      update(buf) {
          exists$1(this);
          this.iHash.update(buf);
          return this;
      }
      digestInto(out) {
          exists$1(this);
          bytes$1(out, this.outputLen);
          this.finished = true;
          this.iHash.digestInto(out);
          this.oHash.update(out);
          this.oHash.digestInto(out);
          this.destroy();
      }
      digest() {
          const out = new Uint8Array(this.oHash.outputLen);
          this.digestInto(out);
          return out;
      }
      _cloneInto(to) {
          // Create new instance without calling constructor since key already in state and we don't know it.
          to || (to = Object.create(Object.getPrototypeOf(this), {}));
          const { oHash, iHash, finished, destroyed, blockLen, outputLen } = this;
          to = to;
          to.finished = finished;
          to.destroyed = destroyed;
          to.blockLen = blockLen;
          to.outputLen = outputLen;
          to.oHash = oHash._cloneInto(to.oHash);
          to.iHash = iHash._cloneInto(to.iHash);
          return to;
      }
      destroy() {
          this.destroyed = true;
          this.oHash.destroy();
          this.iHash.destroy();
      }
  }
  /**
   * HMAC: RFC2104 message authentication code.
   * @param hash - function that would be used e.g. sha256
   * @param key - message key
   * @param message - message data
   */
  const hmac = (hash, key, message) => new HMAC(hash, key).update(message).digest();
  hmac.create = (hash, key) => new HMAC(hash, key);

  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // connects noble-curves to noble-hashes
  function getHash(hash) {
      return {
          hash,
          hmac: (key, ...msgs) => hmac(hash, key, concatBytes$1(...msgs)),
          randomBytes,
      };
  }
  function createCurve(curveDef, defHash) {
      const create = (hash) => weierstrass({ ...curveDef, ...getHash(hash) });
      return Object.freeze({ ...create(defHash), create });
  }

  /*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  const secp256k1P = BigInt('0xfffffffffffffffffffffffffffffffffffffffffffffffffffffffefffffc2f');
  const secp256k1N = BigInt('0xfffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141');
  const _1n = BigInt(1);
  const _2n = BigInt(2);
  const divNearest = (a, b) => (a + b / _2n) / b;
  /**
   * √n = n^((p+1)/4) for fields p = 3 mod 4. We unwrap the loop and multiply bit-by-bit.
   * (P+1n/4n).toString(2) would produce bits [223x 1, 0, 22x 1, 4x 0, 11, 00]
   */
  function sqrtMod(y) {
      const P = secp256k1P;
      // prettier-ignore
      const _3n = BigInt(3), _6n = BigInt(6), _11n = BigInt(11), _22n = BigInt(22);
      // prettier-ignore
      const _23n = BigInt(23), _44n = BigInt(44), _88n = BigInt(88);
      const b2 = (y * y * y) % P; // x^3, 11
      const b3 = (b2 * b2 * y) % P; // x^7
      const b6 = (pow2(b3, _3n, P) * b3) % P;
      const b9 = (pow2(b6, _3n, P) * b3) % P;
      const b11 = (pow2(b9, _2n, P) * b2) % P;
      const b22 = (pow2(b11, _11n, P) * b11) % P;
      const b44 = (pow2(b22, _22n, P) * b22) % P;
      const b88 = (pow2(b44, _44n, P) * b44) % P;
      const b176 = (pow2(b88, _88n, P) * b88) % P;
      const b220 = (pow2(b176, _44n, P) * b44) % P;
      const b223 = (pow2(b220, _3n, P) * b3) % P;
      const t1 = (pow2(b223, _23n, P) * b22) % P;
      const t2 = (pow2(t1, _6n, P) * b2) % P;
      const root = pow2(t2, _2n, P);
      if (!Fp.eql(Fp.sqr(root), y))
          throw new Error('Cannot find square root');
      return root;
  }
  const Fp = Field(secp256k1P, undefined, undefined, { sqrt: sqrtMod });
  const secp256k1 = createCurve({
      a: BigInt(0),
      b: BigInt(7),
      Fp,
      n: secp256k1N,
      // Base point (x, y) aka generator point
      Gx: BigInt('55066263022277343669578718895168534326250603453777594175500187360389116729240'),
      Gy: BigInt('32670510020758816978083085130507043184471273380659243275938904335757337482424'),
      h: BigInt(1),
      lowS: true,
      /**
       * secp256k1 belongs to Koblitz curves: it has efficiently computable endomorphism.
       * Endomorphism uses 2x less RAM, speeds up precomputation by 2x and ECDH / key recovery by 20%.
       * For precomputed wNAF it trades off 1/2 init time & 1/3 ram for 20% perf hit.
       * Explanation: https://gist.github.com/paulmillr/eb670806793e84df628a7c434a873066
       */
      endo: {
          beta: BigInt('0x7ae96a2b657c07106e64479eac3434e99cf0497512f58995c1396c28719501ee'),
          splitScalar: (k) => {
              const n = secp256k1N;
              const a1 = BigInt('0x3086d221a7d46bcde86c90e49284eb15');
              const b1 = -_1n * BigInt('0xe4437ed6010e88286f547fa90abfe4c3');
              const a2 = BigInt('0x114ca50f7a8e2f3f657c1108d9d44cfd8');
              const b2 = a1;
              const POW_2_128 = BigInt('0x100000000000000000000000000000000'); // (2n**128n).toString(16)
              const c1 = divNearest(b2 * k, n);
              const c2 = divNearest(-b1 * k, n);
              let k1 = mod(k - c1 * a1 - c2 * a2, n);
              let k2 = mod(-c1 * b1 - c2 * b2, n);
              const k1neg = k1 > POW_2_128;
              const k2neg = k2 > POW_2_128;
              if (k1neg)
                  k1 = n - k1;
              if (k2neg)
                  k2 = n - k2;
              if (k1 > POW_2_128 || k2 > POW_2_128) {
                  throw new Error('splitScalar: Endomorphism failed, k=' + k);
              }
              return { k1neg, k1, k2neg, k2 };
          },
      },
  }, sha256$1);
  // Schnorr signatures are superior to ECDSA from above. Below is Schnorr-specific BIP0340 code.
  // https://github.com/bitcoin/bips/blob/master/bip-0340.mediawiki
  const _0n = BigInt(0);
  const fe = (x) => typeof x === 'bigint' && _0n < x && x < secp256k1P;
  const ge = (x) => typeof x === 'bigint' && _0n < x && x < secp256k1N;
  /** An object mapping tags to their tagged hash prefix of [SHA256(tag) | SHA256(tag)] */
  const TAGGED_HASH_PREFIXES = {};
  function taggedHash(tag, ...messages) {
      let tagP = TAGGED_HASH_PREFIXES[tag];
      if (tagP === undefined) {
          const tagH = sha256$1(Uint8Array.from(tag, (c) => c.charCodeAt(0)));
          tagP = concatBytes(tagH, tagH);
          TAGGED_HASH_PREFIXES[tag] = tagP;
      }
      return sha256$1(concatBytes(tagP, ...messages));
  }
  // ECDSA compact points are 33-byte. Schnorr is 32: we strip first byte 0x02 or 0x03
  const pointToBytes = (point) => point.toRawBytes(true).slice(1);
  const numTo32b = (n) => numberToBytesBE(n, 32);
  const modP = (x) => mod(x, secp256k1P);
  const modN = (x) => mod(x, secp256k1N);
  const Point = secp256k1.ProjectivePoint;
  const GmulAdd = (Q, a, b) => Point.BASE.multiplyAndAddUnsafe(Q, a, b);
  // Calculate point, scalar and bytes
  function schnorrGetExtPubKey(priv) {
      let d_ = secp256k1.utils.normPrivateKeyToScalar(priv); // same method executed in fromPrivateKey
      let p = Point.fromPrivateKey(d_); // P = d'⋅G; 0 < d' < n check is done inside
      const scalar = p.hasEvenY() ? d_ : modN(-d_);
      return { scalar: scalar, bytes: pointToBytes(p) };
  }
  /**
   * lift_x from BIP340. Convert 32-byte x coordinate to elliptic curve point.
   * @returns valid point checked for being on-curve
   */
  function lift_x(x) {
      if (!fe(x))
          throw new Error('bad x: need 0 < x < p'); // Fail if x ≥ p.
      const xx = modP(x * x);
      const c = modP(xx * x + BigInt(7)); // Let c = x³ + 7 mod p.
      let y = sqrtMod(c); // Let y = c^(p+1)/4 mod p.
      if (y % _2n !== _0n)
          y = modP(-y); // Return the unique point P such that x(P) = x and
      const p = new Point(x, y, _1n); // y(P) = y if y mod 2 = 0 or y(P) = p-y otherwise.
      p.assertValidity();
      return p;
  }
  /**
   * Create tagged hash, convert it to bigint, reduce modulo-n.
   */
  function challenge(...args) {
      return modN(bytesToNumberBE(taggedHash('BIP0340/challenge', ...args)));
  }
  /**
   * Schnorr public key is just `x` coordinate of Point as per BIP340.
   */
  function schnorrGetPublicKey(privateKey) {
      return schnorrGetExtPubKey(privateKey).bytes; // d'=int(sk). Fail if d'=0 or d'≥n. Ret bytes(d'⋅G)
  }
  /**
   * Creates Schnorr signature as per BIP340. Verifies itself before returning anything.
   * auxRand is optional and is not the sole source of k generation: bad CSPRNG won't be dangerous.
   */
  function schnorrSign(message, privateKey, auxRand = randomBytes(32)) {
      const m = ensureBytes('message', message);
      const { bytes: px, scalar: d } = schnorrGetExtPubKey(privateKey); // checks for isWithinCurveOrder
      const a = ensureBytes('auxRand', auxRand, 32); // Auxiliary random data a: a 32-byte array
      const t = numTo32b(d ^ bytesToNumberBE(taggedHash('BIP0340/aux', a))); // Let t be the byte-wise xor of bytes(d) and hash/aux(a)
      const rand = taggedHash('BIP0340/nonce', t, px, m); // Let rand = hash/nonce(t || bytes(P) || m)
      const k_ = modN(bytesToNumberBE(rand)); // Let k' = int(rand) mod n
      if (k_ === _0n)
          throw new Error('sign failed: k is zero'); // Fail if k' = 0.
      const { bytes: rx, scalar: k } = schnorrGetExtPubKey(k_); // Let R = k'⋅G.
      const e = challenge(rx, px, m); // Let e = int(hash/challenge(bytes(R) || bytes(P) || m)) mod n.
      const sig = new Uint8Array(64); // Let sig = bytes(R) || bytes((k + ed) mod n).
      sig.set(rx, 0);
      sig.set(numTo32b(modN(k + e * d)), 32);
      // If Verify(bytes(P), m, sig) (see below) returns failure, abort
      if (!schnorrVerify(sig, m, px))
          throw new Error('sign: Invalid signature produced');
      return sig;
  }
  /**
   * Verifies Schnorr signature.
   * Will swallow errors & return false except for initial type validation of arguments.
   */
  function schnorrVerify(signature, message, publicKey) {
      const sig = ensureBytes('signature', signature, 64);
      const m = ensureBytes('message', message);
      const pub = ensureBytes('publicKey', publicKey, 32);
      try {
          const P = lift_x(bytesToNumberBE(pub)); // P = lift_x(int(pk)); fail if that fails
          const r = bytesToNumberBE(sig.subarray(0, 32)); // Let r = int(sig[0:32]); fail if r ≥ p.
          if (!fe(r))
              return false;
          const s = bytesToNumberBE(sig.subarray(32, 64)); // Let s = int(sig[32:64]); fail if s ≥ n.
          if (!ge(s))
              return false;
          const e = challenge(numTo32b(r), pointToBytes(P), m); // int(challenge(bytes(r)||bytes(P)||m))%n
          const R = GmulAdd(P, s, modN(-e)); // R = s⋅G - e⋅P
          if (!R || !R.hasEvenY() || R.toAffine().x !== r)
              return false; // -eP == (n-e)P
          return true; // Fail if is_infinite(R) / not has_even_y(R) / x(R) ≠ r.
      }
      catch (error) {
          return false;
      }
  }
  const schnorr = /* @__PURE__ */ (() => ({
      getPublicKey: schnorrGetPublicKey,
      sign: schnorrSign,
      verify: schnorrVerify,
      utils: {
          randomPrivateKey: secp256k1.utils.randomPrivateKey,
          lift_x,
          pointToBytes,
          numberToBytesBE,
          bytesToNumberBE,
          taggedHash,
          mod,
      },
  }))();

  /*! noble-hashes - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  // We use WebCrypto aka globalThis.crypto, which exists in browsers and node.js 16+.
  // node.js versions earlier than v19 don't declare it in global scope.
  // For node.js, package.json#exports field mapping rewrites import
  // from `crypto` to `cryptoNode`, which imports native module.
  // Makes the utils un-importable in browsers without a bundler.
  // Once node.js 18 is deprecated, we can just drop the import.
  const u8a = (a) => a instanceof Uint8Array;
  // Cast array to view
  const createView = (arr) => new DataView(arr.buffer, arr.byteOffset, arr.byteLength);
  // The rotate right (circular right shift) operation for uint32
  const rotr = (word, shift) => (word << (32 - shift)) | (word >>> shift);
  // big-endian hardware is rare. Just in case someone still decides to run hashes:
  // early-throw an error because we don't support BE yet.
  const isLE = new Uint8Array(new Uint32Array([0x11223344]).buffer)[0] === 0x44;
  if (!isLE)
      throw new Error('Non little-endian hardware is not supported');
  const hexes = Array.from({ length: 256 }, (v, i) => i.toString(16).padStart(2, '0'));
  /**
   * @example bytesToHex(Uint8Array.from([0xca, 0xfe, 0x01, 0x23])) // 'cafe0123'
   */
  function bytesToHex(bytes) {
      if (!u8a(bytes))
          throw new Error('Uint8Array expected');
      // pre-caching improves the speed 6x
      let hex = '';
      for (let i = 0; i < bytes.length; i++) {
          hex += hexes[bytes[i]];
      }
      return hex;
  }
  /**
   * @example utf8ToBytes('abc') // new Uint8Array([97, 98, 99])
   */
  function utf8ToBytes(str) {
      if (typeof str !== 'string')
          throw new Error(`utf8ToBytes expected string, got ${typeof str}`);
      return new Uint8Array(new TextEncoder().encode(str)); // https://bugzil.la/1681809
  }
  /**
   * Normalizes (non-hex) string or Uint8Array to Uint8Array.
   * Warning: when Uint8Array is passed, it would NOT get copied.
   * Keep in mind for future mutable operations.
   */
  function toBytes(data) {
      if (typeof data === 'string')
          data = utf8ToBytes(data);
      if (!u8a(data))
          throw new Error(`expected Uint8Array, got ${typeof data}`);
      return data;
  }
  // For runtime check if class implements interface
  class Hash {
      // Safe version that clones internal state
      clone() {
          return this._cloneInto();
      }
  }
  function wrapConstructor(hashCons) {
      const hashC = (msg) => hashCons().update(toBytes(msg)).digest();
      const tmp = hashCons();
      hashC.outputLen = tmp.outputLen;
      hashC.blockLen = tmp.blockLen;
      hashC.create = () => hashCons();
      return hashC;
  }

  /** Designates a verified event signature. */ const verifiedSymbol = Symbol('verified');
  const isRecord = (obj)=>obj instanceof Object;
  function validateEvent(event) {
    if (!isRecord(event)) return false;
    if (typeof event.kind !== 'number') return false;
    if (typeof event.content !== 'string') return false;
    if (typeof event.created_at !== 'number') return false;
    if (typeof event.pubkey !== 'string') return false;
    if (!event.pubkey.match(/^[a-f0-9]{64}$/)) return false;
    if (!Array.isArray(event.tags)) return false;
    for(let i = 0; i < event.tags.length; i++){
      let tag = event.tags[i];
      if (!Array.isArray(tag)) return false;
      for(let j = 0; j < tag.length; j++){
        if (typeof tag[j] !== 'string') return false;
      }
    }
    return true;
  }

  function number(n) {
      if (!Number.isSafeInteger(n) || n < 0)
          throw new Error(`Wrong positive integer: ${n}`);
  }
  function bool(b) {
      if (typeof b !== 'boolean')
          throw new Error(`Expected boolean, not ${b}`);
  }
  function bytes(b, ...lengths) {
      if (!(b instanceof Uint8Array))
          throw new Error('Expected Uint8Array');
      if (lengths.length > 0 && !lengths.includes(b.length))
          throw new Error(`Expected Uint8Array of length ${lengths}, not of length=${b.length}`);
  }
  function hash(hash) {
      if (typeof hash !== 'function' || typeof hash.create !== 'function')
          throw new Error('Hash should be wrapped by utils.wrapConstructor');
      number(hash.outputLen);
      number(hash.blockLen);
  }
  function exists(instance, checkFinished = true) {
      if (instance.destroyed)
          throw new Error('Hash instance has been destroyed');
      if (checkFinished && instance.finished)
          throw new Error('Hash#digest() has already been called');
  }
  function output(out, instance) {
      bytes(out);
      const min = instance.outputLen;
      if (out.length < min) {
          throw new Error(`digestInto() expects output buffer of length at least ${min}`);
      }
  }
  const assert = {
      number,
      bool,
      bytes,
      hash,
      exists,
      output,
  };

  // Polyfill for Safari 14
  function setBigUint64(view, byteOffset, value, isLE) {
      if (typeof view.setBigUint64 === 'function')
          return view.setBigUint64(byteOffset, value, isLE);
      const _32n = BigInt(32);
      const _u32_max = BigInt(0xffffffff);
      const wh = Number((value >> _32n) & _u32_max);
      const wl = Number(value & _u32_max);
      const h = isLE ? 4 : 0;
      const l = isLE ? 0 : 4;
      view.setUint32(byteOffset + h, wh, isLE);
      view.setUint32(byteOffset + l, wl, isLE);
  }
  // Base SHA2 class (RFC 6234)
  class SHA2 extends Hash {
      constructor(blockLen, outputLen, padOffset, isLE) {
          super();
          this.blockLen = blockLen;
          this.outputLen = outputLen;
          this.padOffset = padOffset;
          this.isLE = isLE;
          this.finished = false;
          this.length = 0;
          this.pos = 0;
          this.destroyed = false;
          this.buffer = new Uint8Array(blockLen);
          this.view = createView(this.buffer);
      }
      update(data) {
          assert.exists(this);
          const { view, buffer, blockLen } = this;
          data = toBytes(data);
          const len = data.length;
          for (let pos = 0; pos < len;) {
              const take = Math.min(blockLen - this.pos, len - pos);
              // Fast path: we have at least one block in input, cast it to view and process
              if (take === blockLen) {
                  const dataView = createView(data);
                  for (; blockLen <= len - pos; pos += blockLen)
                      this.process(dataView, pos);
                  continue;
              }
              buffer.set(data.subarray(pos, pos + take), this.pos);
              this.pos += take;
              pos += take;
              if (this.pos === blockLen) {
                  this.process(view, 0);
                  this.pos = 0;
              }
          }
          this.length += data.length;
          this.roundClean();
          return this;
      }
      digestInto(out) {
          assert.exists(this);
          assert.output(out, this);
          this.finished = true;
          // Padding
          // We can avoid allocation of buffer for padding completely if it
          // was previously not allocated here. But it won't change performance.
          const { buffer, view, blockLen, isLE } = this;
          let { pos } = this;
          // append the bit '1' to the message
          buffer[pos++] = 0b10000000;
          this.buffer.subarray(pos).fill(0);
          // we have less than padOffset left in buffer, so we cannot put length in current block, need process it and pad again
          if (this.padOffset > blockLen - pos) {
              this.process(view, 0);
              pos = 0;
          }
          // Pad until full block byte with zeros
          for (let i = pos; i < blockLen; i++)
              buffer[i] = 0;
          // Note: sha512 requires length to be 128bit integer, but length in JS will overflow before that
          // You need to write around 2 exabytes (u64_max / 8 / (1024**6)) for this to happen.
          // So we just write lowest 64 bits of that value.
          setBigUint64(view, blockLen - 8, BigInt(this.length * 8), isLE);
          this.process(view, 0);
          const oview = createView(out);
          const len = this.outputLen;
          // NOTE: we do division by 4 later, which should be fused in single op with modulo by JIT
          if (len % 4)
              throw new Error('_sha2: outputLen should be aligned to 32bit');
          const outLen = len / 4;
          const state = this.get();
          if (outLen > state.length)
              throw new Error('_sha2: outputLen bigger than state');
          for (let i = 0; i < outLen; i++)
              oview.setUint32(4 * i, state[i], isLE);
      }
      digest() {
          const { buffer, outputLen } = this;
          this.digestInto(buffer);
          const res = buffer.slice(0, outputLen);
          this.destroy();
          return res;
      }
      _cloneInto(to) {
          to || (to = new this.constructor());
          to.set(...this.get());
          const { blockLen, buffer, length, finished, destroyed, pos } = this;
          to.length = length;
          to.pos = pos;
          to.finished = finished;
          to.destroyed = destroyed;
          if (length % blockLen)
              to.buffer.set(buffer);
          return to;
      }
  }

  // Choice: a ? b : c
  const Chi = (a, b, c) => (a & b) ^ (~a & c);
  // Majority function, true if any two inpust is true
  const Maj = (a, b, c) => (a & b) ^ (a & c) ^ (b & c);
  // Round constants:
  // first 32 bits of the fractional parts of the cube roots of the first 64 primes 2..311)
  // prettier-ignore
  const SHA256_K = new Uint32Array([
      0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
      0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
      0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
      0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
      0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
      0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
      0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
      0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
  ]);
  // Initial state (first 32 bits of the fractional parts of the square roots of the first 8 primes 2..19):
  // prettier-ignore
  const IV = new Uint32Array([
      0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19
  ]);
  // Temporary buffer, not used to store anything between runs
  // Named this way because it matches specification.
  const SHA256_W = new Uint32Array(64);
  class SHA256 extends SHA2 {
      constructor() {
          super(64, 32, 8, false);
          // We cannot use array here since array allows indexing by variable
          // which means optimizer/compiler cannot use registers.
          this.A = IV[0] | 0;
          this.B = IV[1] | 0;
          this.C = IV[2] | 0;
          this.D = IV[3] | 0;
          this.E = IV[4] | 0;
          this.F = IV[5] | 0;
          this.G = IV[6] | 0;
          this.H = IV[7] | 0;
      }
      get() {
          const { A, B, C, D, E, F, G, H } = this;
          return [A, B, C, D, E, F, G, H];
      }
      // prettier-ignore
      set(A, B, C, D, E, F, G, H) {
          this.A = A | 0;
          this.B = B | 0;
          this.C = C | 0;
          this.D = D | 0;
          this.E = E | 0;
          this.F = F | 0;
          this.G = G | 0;
          this.H = H | 0;
      }
      process(view, offset) {
          // Extend the first 16 words into the remaining 48 words w[16..63] of the message schedule array
          for (let i = 0; i < 16; i++, offset += 4)
              SHA256_W[i] = view.getUint32(offset, false);
          for (let i = 16; i < 64; i++) {
              const W15 = SHA256_W[i - 15];
              const W2 = SHA256_W[i - 2];
              const s0 = rotr(W15, 7) ^ rotr(W15, 18) ^ (W15 >>> 3);
              const s1 = rotr(W2, 17) ^ rotr(W2, 19) ^ (W2 >>> 10);
              SHA256_W[i] = (s1 + SHA256_W[i - 7] + s0 + SHA256_W[i - 16]) | 0;
          }
          // Compression function main loop, 64 rounds
          let { A, B, C, D, E, F, G, H } = this;
          for (let i = 0; i < 64; i++) {
              const sigma1 = rotr(E, 6) ^ rotr(E, 11) ^ rotr(E, 25);
              const T1 = (H + sigma1 + Chi(E, F, G) + SHA256_K[i] + SHA256_W[i]) | 0;
              const sigma0 = rotr(A, 2) ^ rotr(A, 13) ^ rotr(A, 22);
              const T2 = (sigma0 + Maj(A, B, C)) | 0;
              H = G;
              G = F;
              F = E;
              E = (D + T1) | 0;
              D = C;
              C = B;
              B = A;
              A = (T1 + T2) | 0;
          }
          // Add the compressed chunk to the current hash value
          A = (A + this.A) | 0;
          B = (B + this.B) | 0;
          C = (C + this.C) | 0;
          D = (D + this.D) | 0;
          E = (E + this.E) | 0;
          F = (F + this.F) | 0;
          G = (G + this.G) | 0;
          H = (H + this.H) | 0;
          this.set(A, B, C, D, E, F, G, H);
      }
      roundClean() {
          SHA256_W.fill(0);
      }
      destroy() {
          this.set(0, 0, 0, 0, 0, 0, 0, 0);
          this.buffer.fill(0);
      }
  }
  // Constants from https://nvlpubs.nist.gov/nistpubs/FIPS/NIST.FIPS.180-4.pdf
  class SHA224 extends SHA256 {
      constructor() {
          super();
          this.A = 0xc1059ed8 | 0;
          this.B = 0x367cd507 | 0;
          this.C = 0x3070dd17 | 0;
          this.D = 0xf70e5939 | 0;
          this.E = 0xffc00b31 | 0;
          this.F = 0x68581511 | 0;
          this.G = 0x64f98fa7 | 0;
          this.H = 0xbefa4fa4 | 0;
          this.outputLen = 28;
      }
  }
  /**
   * SHA2-256 hash function
   * @param message - data that would be hashed
   */
  const sha256 = wrapConstructor(() => new SHA256());
  wrapConstructor(() => new SHA224());

  new TextDecoder('utf-8');
  const utf8Encoder = new TextEncoder();

  class JS {
    generateSecretKey() {
      return schnorr.utils.randomPrivateKey();
    }
    getPublicKey(secretKey) {
      return bytesToHex(schnorr.getPublicKey(secretKey));
    }
    finalizeEvent(t, secretKey) {
      const event = t;
      event.pubkey = bytesToHex(schnorr.getPublicKey(secretKey));
      event.id = getEventHash(event);
      event.sig = bytesToHex(schnorr.sign(getEventHash(event), secretKey));
      event[verifiedSymbol] = true;
      return event;
    }
    verifyEvent(event) {
      if (typeof event[verifiedSymbol] === 'boolean') return event[verifiedSymbol];
      const hash = getEventHash(event);
      if (hash !== event.id) {
        event[verifiedSymbol] = false;
        return false;
      }
      try {
        const valid = schnorr.verify(event.sig, hash, event.pubkey);
        event[verifiedSymbol] = valid;
        return valid;
      } catch (err) {
        event[verifiedSymbol] = false;
        return false;
      }
    }
  }
  function serializeEvent(evt) {
    if (!validateEvent(evt)) throw new Error("can't serialize event with wrong or missing properties");
    return JSON.stringify([
      0,
      evt.pubkey,
      evt.created_at,
      evt.kind,
      evt.tags,
      evt.content
    ]);
  }
  function getEventHash(event) {
    let eventHash = sha256(utf8Encoder.encode(serializeEvent(event)));
    return bytesToHex(eventHash);
  }
  const i = new JS();
  i.generateSecretKey;
  i.getPublicKey;
  i.finalizeEvent;
  i.verifyEvent;

  /*! scure-base - MIT License (c) 2022 Paul Miller (paulmillr.com) */
  function assertNumber(n) {
      if (!Number.isSafeInteger(n))
          throw new Error(`Wrong integer: ${n}`);
  }
  function chain(...args) {
      const wrap = (a, b) => (c) => a(b(c));
      const encode = Array.from(args)
          .reverse()
          .reduce((acc, i) => (acc ? wrap(acc, i.encode) : i.encode), undefined);
      const decode = args.reduce((acc, i) => (acc ? wrap(acc, i.decode) : i.decode), undefined);
      return { encode, decode };
  }
  function alphabet(alphabet) {
      return {
          encode: (digits) => {
              if (!Array.isArray(digits) || (digits.length && typeof digits[0] !== 'number'))
                  throw new Error('alphabet.encode input should be an array of numbers');
              return digits.map((i) => {
                  assertNumber(i);
                  if (i < 0 || i >= alphabet.length)
                      throw new Error(`Digit index outside alphabet: ${i} (alphabet: ${alphabet.length})`);
                  return alphabet[i];
              });
          },
          decode: (input) => {
              if (!Array.isArray(input) || (input.length && typeof input[0] !== 'string'))
                  throw new Error('alphabet.decode input should be array of strings');
              return input.map((letter) => {
                  if (typeof letter !== 'string')
                      throw new Error(`alphabet.decode: not string element=${letter}`);
                  const index = alphabet.indexOf(letter);
                  if (index === -1)
                      throw new Error(`Unknown letter: "${letter}". Allowed: ${alphabet}`);
                  return index;
              });
          },
      };
  }
  function join(separator = '') {
      if (typeof separator !== 'string')
          throw new Error('join separator should be string');
      return {
          encode: (from) => {
              if (!Array.isArray(from) || (from.length && typeof from[0] !== 'string'))
                  throw new Error('join.encode input should be array of strings');
              for (let i of from)
                  if (typeof i !== 'string')
                      throw new Error(`join.encode: non-string input=${i}`);
              return from.join(separator);
          },
          decode: (to) => {
              if (typeof to !== 'string')
                  throw new Error('join.decode input should be string');
              return to.split(separator);
          },
      };
  }
  function padding(bits, chr = '=') {
      assertNumber(bits);
      if (typeof chr !== 'string')
          throw new Error('padding chr should be string');
      return {
          encode(data) {
              if (!Array.isArray(data) || (data.length && typeof data[0] !== 'string'))
                  throw new Error('padding.encode input should be array of strings');
              for (let i of data)
                  if (typeof i !== 'string')
                      throw new Error(`padding.encode: non-string input=${i}`);
              while ((data.length * bits) % 8)
                  data.push(chr);
              return data;
          },
          decode(input) {
              if (!Array.isArray(input) || (input.length && typeof input[0] !== 'string'))
                  throw new Error('padding.encode input should be array of strings');
              for (let i of input)
                  if (typeof i !== 'string')
                      throw new Error(`padding.decode: non-string input=${i}`);
              let end = input.length;
              if ((end * bits) % 8)
                  throw new Error('Invalid padding: string should have whole number of bytes');
              for (; end > 0 && input[end - 1] === chr; end--) {
                  if (!(((end - 1) * bits) % 8))
                      throw new Error('Invalid padding: string has too much padding');
              }
              return input.slice(0, end);
          },
      };
  }
  function normalize(fn) {
      if (typeof fn !== 'function')
          throw new Error('normalize fn should be function');
      return { encode: (from) => from, decode: (to) => fn(to) };
  }
  function convertRadix(data, from, to) {
      if (from < 2)
          throw new Error(`convertRadix: wrong from=${from}, base cannot be less than 2`);
      if (to < 2)
          throw new Error(`convertRadix: wrong to=${to}, base cannot be less than 2`);
      if (!Array.isArray(data))
          throw new Error('convertRadix: data should be array');
      if (!data.length)
          return [];
      let pos = 0;
      const res = [];
      const digits = Array.from(data);
      digits.forEach((d) => {
          assertNumber(d);
          if (d < 0 || d >= from)
              throw new Error(`Wrong integer: ${d}`);
      });
      while (true) {
          let carry = 0;
          let done = true;
          for (let i = pos; i < digits.length; i++) {
              const digit = digits[i];
              const digitBase = from * carry + digit;
              if (!Number.isSafeInteger(digitBase) ||
                  (from * carry) / from !== carry ||
                  digitBase - digit !== from * carry) {
                  throw new Error('convertRadix: carry overflow');
              }
              carry = digitBase % to;
              digits[i] = Math.floor(digitBase / to);
              if (!Number.isSafeInteger(digits[i]) || digits[i] * to + carry !== digitBase)
                  throw new Error('convertRadix: carry overflow');
              if (!done)
                  continue;
              else if (!digits[i])
                  pos = i;
              else
                  done = false;
          }
          res.push(carry);
          if (done)
              break;
      }
      for (let i = 0; i < data.length - 1 && data[i] === 0; i++)
          res.push(0);
      return res.reverse();
  }
  const gcd = (a, b) => (!b ? a : gcd(b, a % b));
  const radix2carry = (from, to) => from + (to - gcd(from, to));
  function convertRadix2(data, from, to, padding) {
      if (!Array.isArray(data))
          throw new Error('convertRadix2: data should be array');
      if (from <= 0 || from > 32)
          throw new Error(`convertRadix2: wrong from=${from}`);
      if (to <= 0 || to > 32)
          throw new Error(`convertRadix2: wrong to=${to}`);
      if (radix2carry(from, to) > 32) {
          throw new Error(`convertRadix2: carry overflow from=${from} to=${to} carryBits=${radix2carry(from, to)}`);
      }
      let carry = 0;
      let pos = 0;
      const mask = 2 ** to - 1;
      const res = [];
      for (const n of data) {
          assertNumber(n);
          if (n >= 2 ** from)
              throw new Error(`convertRadix2: invalid data word=${n} from=${from}`);
          carry = (carry << from) | n;
          if (pos + from > 32)
              throw new Error(`convertRadix2: carry overflow pos=${pos} from=${from}`);
          pos += from;
          for (; pos >= to; pos -= to)
              res.push(((carry >> (pos - to)) & mask) >>> 0);
          carry &= 2 ** pos - 1;
      }
      carry = (carry << (to - pos)) & mask;
      if (!padding && pos >= from)
          throw new Error('Excess padding');
      if (!padding && carry)
          throw new Error(`Non-zero padding: ${carry}`);
      if (padding && pos > 0)
          res.push(carry >>> 0);
      return res;
  }
  function radix(num) {
      assertNumber(num);
      return {
          encode: (bytes) => {
              if (!(bytes instanceof Uint8Array))
                  throw new Error('radix.encode input should be Uint8Array');
              return convertRadix(Array.from(bytes), 2 ** 8, num);
          },
          decode: (digits) => {
              if (!Array.isArray(digits) || (digits.length && typeof digits[0] !== 'number'))
                  throw new Error('radix.decode input should be array of strings');
              return Uint8Array.from(convertRadix(digits, num, 2 ** 8));
          },
      };
  }
  function radix2(bits, revPadding = false) {
      assertNumber(bits);
      if (bits <= 0 || bits > 32)
          throw new Error('radix2: bits should be in (0..32]');
      if (radix2carry(8, bits) > 32 || radix2carry(bits, 8) > 32)
          throw new Error('radix2: carry overflow');
      return {
          encode: (bytes) => {
              if (!(bytes instanceof Uint8Array))
                  throw new Error('radix2.encode input should be Uint8Array');
              return convertRadix2(Array.from(bytes), 8, bits, !revPadding);
          },
          decode: (digits) => {
              if (!Array.isArray(digits) || (digits.length && typeof digits[0] !== 'number'))
                  throw new Error('radix2.decode input should be array of strings');
              return Uint8Array.from(convertRadix2(digits, bits, 8, revPadding));
          },
      };
  }
  function unsafeWrapper(fn) {
      if (typeof fn !== 'function')
          throw new Error('unsafeWrapper fn should be function');
      return function (...args) {
          try {
              return fn.apply(null, args);
          }
          catch (e) { }
      };
  }
  const base16 = chain(radix2(4), alphabet('0123456789ABCDEF'), join(''));
  const base32 = chain(radix2(5), alphabet('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'), padding(5), join(''));
  chain(radix2(5), alphabet('0123456789ABCDEFGHIJKLMNOPQRSTUV'), padding(5), join(''));
  chain(radix2(5), alphabet('0123456789ABCDEFGHJKMNPQRSTVWXYZ'), join(''), normalize((s) => s.toUpperCase().replace(/O/g, '0').replace(/[IL]/g, '1')));
  const base64 = chain(radix2(6), alphabet('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'), padding(6), join(''));
  const base64url = chain(radix2(6), alphabet('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'), padding(6), join(''));
  const genBase58 = (abc) => chain(radix(58), alphabet(abc), join(''));
  const base58 = genBase58('123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
  genBase58('123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ');
  genBase58('rpshnaf39wBUDNEGHJKLM4PQRST7VWXYZ2bcdeCg65jkm8oFqi1tuvAxyz');
  const XMR_BLOCK_LEN = [0, 2, 3, 5, 6, 7, 9, 10, 11];
  const base58xmr = {
      encode(data) {
          let res = '';
          for (let i = 0; i < data.length; i += 8) {
              const block = data.subarray(i, i + 8);
              res += base58.encode(block).padStart(XMR_BLOCK_LEN[block.length], '1');
          }
          return res;
      },
      decode(str) {
          let res = [];
          for (let i = 0; i < str.length; i += 11) {
              const slice = str.slice(i, i + 11);
              const blockLen = XMR_BLOCK_LEN.indexOf(slice.length);
              const block = base58.decode(slice);
              for (let j = 0; j < block.length - blockLen; j++) {
                  if (block[j] !== 0)
                      throw new Error('base58xmr: wrong padding');
              }
              res = res.concat(Array.from(block.slice(block.length - blockLen)));
          }
          return Uint8Array.from(res);
      },
  };
  const BECH_ALPHABET = chain(alphabet('qpzry9x8gf2tvdw0s3jn54khce6mua7l'), join(''));
  const POLYMOD_GENERATORS = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
  function bech32Polymod(pre) {
      const b = pre >> 25;
      let chk = (pre & 0x1ffffff) << 5;
      for (let i = 0; i < POLYMOD_GENERATORS.length; i++) {
          if (((b >> i) & 1) === 1)
              chk ^= POLYMOD_GENERATORS[i];
      }
      return chk;
  }
  function bechChecksum(prefix, words, encodingConst = 1) {
      const len = prefix.length;
      let chk = 1;
      for (let i = 0; i < len; i++) {
          const c = prefix.charCodeAt(i);
          if (c < 33 || c > 126)
              throw new Error(`Invalid prefix (${prefix})`);
          chk = bech32Polymod(chk) ^ (c >> 5);
      }
      chk = bech32Polymod(chk);
      for (let i = 0; i < len; i++)
          chk = bech32Polymod(chk) ^ (prefix.charCodeAt(i) & 0x1f);
      for (let v of words)
          chk = bech32Polymod(chk) ^ v;
      for (let i = 0; i < 6; i++)
          chk = bech32Polymod(chk);
      chk ^= encodingConst;
      return BECH_ALPHABET.encode(convertRadix2([chk % 2 ** 30], 30, 5, false));
  }
  function genBech32(encoding) {
      const ENCODING_CONST = encoding === 'bech32' ? 1 : 0x2bc830a3;
      const _words = radix2(5);
      const fromWords = _words.decode;
      const toWords = _words.encode;
      const fromWordsUnsafe = unsafeWrapper(fromWords);
      function encode(prefix, words, limit = 90) {
          if (typeof prefix !== 'string')
              throw new Error(`bech32.encode prefix should be string, not ${typeof prefix}`);
          if (!Array.isArray(words) || (words.length && typeof words[0] !== 'number'))
              throw new Error(`bech32.encode words should be array of numbers, not ${typeof words}`);
          const actualLength = prefix.length + 7 + words.length;
          if (limit !== false && actualLength > limit)
              throw new TypeError(`Length ${actualLength} exceeds limit ${limit}`);
          prefix = prefix.toLowerCase();
          return `${prefix}1${BECH_ALPHABET.encode(words)}${bechChecksum(prefix, words, ENCODING_CONST)}`;
      }
      function decode(str, limit = 90) {
          if (typeof str !== 'string')
              throw new Error(`bech32.decode input should be string, not ${typeof str}`);
          if (str.length < 8 || (limit !== false && str.length > limit))
              throw new TypeError(`Wrong string length: ${str.length} (${str}). Expected (8..${limit})`);
          const lowered = str.toLowerCase();
          if (str !== lowered && str !== str.toUpperCase())
              throw new Error(`String must be lowercase or uppercase`);
          str = lowered;
          const sepIndex = str.lastIndexOf('1');
          if (sepIndex === 0 || sepIndex === -1)
              throw new Error(`Letter "1" must be present between prefix and data only`);
          const prefix = str.slice(0, sepIndex);
          const _words = str.slice(sepIndex + 1);
          if (_words.length < 6)
              throw new Error('Data must be at least 6 characters long');
          const words = BECH_ALPHABET.decode(_words).slice(0, -6);
          const sum = bechChecksum(prefix, words, ENCODING_CONST);
          if (!_words.endsWith(sum))
              throw new Error(`Invalid checksum in ${str}: expected "${sum}"`);
          return { prefix, words };
      }
      const decodeUnsafe = unsafeWrapper(decode);
      function decodeToBytes(str) {
          const { prefix, words } = decode(str, false);
          return { prefix, words, bytes: fromWords(words) };
      }
      return { encode, decode, decodeToBytes, decodeUnsafe, fromWords, fromWordsUnsafe, toWords };
  }
  genBech32('bech32');
  genBech32('bech32m');
  const utf8 = {
      encode: (data) => new TextDecoder().decode(data),
      decode: (str) => new TextEncoder().encode(str),
  };
  const hex = chain(radix2(4), alphabet('0123456789abcdef'), join(''), normalize((s) => {
      if (typeof s !== 'string' || s.length % 2)
          throw new TypeError(`hex.decode: expected string, got ${typeof s} with length ${s.length}`);
      return s.toLowerCase();
  }));
  const CODERS = {
      utf8, hex, base16, base32, base64, base64url, base58, base58xmr
  };
`Invalid encoding type. Available types: ${Object.keys(CODERS).join(', ')}`;

  /**
   * MILL — pomegranate.js
   * Client for fiatjaf's **pomegranate** — FROST threshold signing with "Login
   * with Google". Unlike mill's Drive+PIN or the (retired) relay-NIP, no full key
   * is ever stored: the key is generated in the browser, split into FROST shards
   * across independent operator servers, and erased. Google authenticates the
   * user to those operators; signing happens over NIP-46 through a `central`
   * coordinator. A pomegranate account is, to any client, a normal NIP-46 bunker.
   *
   * Implemented against the authoritative spec at fiatjaf.com/pomegranate
   * (README + admin reference client), pinned to the protocol as of 2026-09.
   * EXPERIMENTAL: no NIP yet; kinds/endpoints are provisional and may change.
   *
   * Requires a running `central` + `operator` servers (the host configures which).
   */


  const POM_KINDS = { ANNOUNCE: 16440, TOKEN: 20443, OP_REG: 20444, CENTRAL_REG: 20445 };

  // Where setup-announcement (kind 16440) discovery events are published/queried.
  // Must match across clients or cross-client discovery fails; mirrors the
  // reference client's list. Hosts may override.
  const DEFAULT_DISCOVERY_RELAYS = [
    'wss://relay.damus.io', 'wss://relay.primal.net', 'wss://nos.lol',
    'wss://nostr.mom', 'wss://offchain.pub',
  ];

  const ARGON = { t: 1, m: 65536, p: 4 };   // MUST match the spec exactly
  const enc = new TextEncoder();

  /** Normalise a server URL to its origin (http→https unless localhost). */
  function massageURL(input) {
    let url = String(input || '').trim();
    if (!url.startsWith('http')) url = 'http' + (url.startsWith('localhost') ? '' : 's') + '://' + url;
    return new URL(url).origin;
  }

  /**
   * The `#m` discovery tag: argon2id(email, "pomegranate", {t:1,m:65536,p:4}) hex.
   * This is how any client finds a user's setup from their Google email alone.
   */
  function discoveryTag(email) {
    return bytesToHex$4(argon2id(enc.encode(String(email)), 'pomegranate', ARGON));
  }

  // ── Token ─────────────────────────────────────────────────────────────────────
  // `central`'s Google login returns a base64 kind:20443 event; the email lives in
  // its `email` tag, and created_at bounds its freshness.
  function tokenEmail(token) {
    try {
      const evt = JSON.parse(atob(token));
      return evt.tags?.find(t => t[0] === 'email')?.[1] || '';
    } catch { return ''; }
  }

  // ── OAuth popup ────────────────────────────────────────────────────────────────
  // `central` (and each operator, for recovery) IS the Google OAuth handler — mill
  // just opens it and receives the result by postMessage. No mill-hosted shim.
  function oauthPopup(url, expectOrigin, { timeoutMs = 120_000 } = {}) {
    return new Promise((resolve, reject) => {
      let origin;
      try { origin = new URL(expectOrigin).origin; } catch { reject(new Error('Invalid server URL')); return; }
      const w = window.open(url, 'pomegranate', 'width=600,height=680');
      if (!w) { reject(new Error('Popup blocked. Allow popups for this site and try again.')); return; }
      let done = false;
      const finish = (fn, v) => { if (done) return; done = true; cleanup(); fn(v); };
      const onMsg = e => {
        if (e.origin !== origin || e.source !== w) return;
        if (e.data && typeof e.data === 'object') finish(resolve, e.data);
      };
      const closed = setInterval(() => { if (w.closed) finish(reject, new Error('Sign-in was cancelled.')); }, 500);
      const timer = setTimeout(() => { if (!w.closed) try { w.close(); } catch {} finish(reject, new Error('Sign-in timed out.')); }, timeoutMs);
      // Only close a still-open popup: calling close() on one that closed itself
      // trips a report-only COOP console warning for nothing.
      function cleanup() { window.removeEventListener('message', onMsg); clearInterval(closed); clearTimeout(timer); if (!w.closed) try { w.close(); } catch {} }
      window.addEventListener('message', onMsg);
    });
  }

  /** Google login against a central; resolves the auth token. */
  async function authenticate(centralURL) {
    const c = massageURL(centralURL);
    const data = await oauthPopup(`${c}/login/google`, c);
    if (!data.token) throw new Error('Sign-in did not return a token.');
    return data.token;
  }

  // ── central REST ────────────────────────────────────────────────────────────────
  async function centralGET(centralURL, path, token) {
    const r = await fetch(massageURL(centralURL) + path, { headers: { Authorization: 'Token ' + token } });
    return r;
  }
  async function getAccount(centralURL, token) {
    const r = await centralGET(centralURL, '/account', token);
    if (!r.ok) return null;   // 401/404 → no account yet
    return r.json();          // { operators, threshold, pubkey }
  }
  async function getProfiles(centralURL, token) {
    const r = await centralGET(centralURL, '/profiles', token);
    if (!r.ok) throw new Error('Could not load profiles.');
    return r.json();          // [{ name, handler_pubkey }]
  }
  async function createProfile(centralURL, token, name = 'default', restrictions) {
    const r = await fetch(massageURL(centralURL) + '/profiles', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: 'Token ' + token },
      body: JSON.stringify({ name, restrictions }),
    });
    if (!r.ok) throw new Error('Could not create profile.');
  }
  async function ensureDefaultProfile(centralURL, token) {
    let profiles = await getProfiles(centralURL, token);
    if (!profiles.find(p => p.name === 'default')) {
      await createProfile(centralURL, token, 'default');
      profiles = await getProfiles(centralURL, token);
    }
    const def = profiles.find(p => p.name === 'default') || profiles[0];
    if (!def) throw new Error('No signing profile available.');
    return def.handler_pubkey;
  }

  /** bunker://<handler_pubkey>?relay=<central-as-ws> — a standard NIP-46 URI. */
  function bunkerURI(handlerPubkey, centralURL) {
    const ws = massageURL(centralURL).replace(/^http/, 'ws');
    return `bunker://${handlerPubkey}?relay=${encodeURIComponent(ws)}`;
  }

  // ── Discovery ────────────────────────────────────────────────────────────────
  /** Find where (if anywhere) this email already set up. Returns { centralURL } or null. */
  async function discover(email, relays = DEFAULT_DISCOVERY_RELAYS) {
    if (!email) return null;
    const pool = new SimplePool();
    try {
      // Collect all matching announcements and prefer the newest: replacing the key
      // publishes a fresh 16440 while the old one lingers on relays, so a single
      // `get` could hand back the stale pointer.
      const events = await pool.querySync(relays, { kinds: [POM_KINDS.ANNOUNCE], '#m': [discoveryTag(email)] }, { maxWait: 5000 });
      if (!events || !events.length) return null;
      const evt = events.reduce((a, b) => (b.created_at > a.created_at ? b : a));
      const centralURL = evt.tags.find(t => t[0] === 'central')?.[1];
      return centralURL ? { centralURL: massageURL(centralURL), createdAt: evt.created_at } : null;
    } catch { return null; }
    finally { try { pool.close(relays); } catch {} }
  }

  async function publishAnnouncement(account, centralURL, secretKey, relays = DEFAULT_DISCOVERY_RELAYS) {
    const event = finalizeEvent$1({
      kind: POM_KINDS.ANNOUNCE,
      created_at: Math.floor(Date.now() / 1000),
      tags: [
        ['m', discoveryTag(account.email)],
        ['central', massageURL(centralURL)],
        ...account.operators.map(op => ['operator', massageURL(typeof op === 'string' ? op : op.url)]),
        ['threshold', String(account.threshold)],
      ],
      content: '',
    }, secretKey);
    const pool = new SimplePool();
    try { await Promise.allSettled(pool.publish(relays, event)); }
    finally { try { pool.close(relays); } catch {} }
  }

  // ── Login (returning / cross-client) ───────────────────────────────────────────
  /**
   * Resolve an existing pomegranate account to a NIP-46 bunker URI. Returns
   * { pubkey, bunkerURI } or null if there's no account at this central.
   */
  async function loginExisting(centralURL, token) {
    const account = await getAccount(centralURL, token);
    if (!account) return null;
    const handler = await ensureDefaultProfile(centralURL, token);
    return { pubkey: account.pubkey, bunkerURI: bunkerURI(handler, centralURL) };
  }

  // ── Signup (create account: FROST-shard + register) ────────────────────────────
  /**
   * Create a new pomegranate account. Generates a key (or uses `secretKey`),
   * FROST-shards it across `operators` with `threshold`, registers with central
   * and each operator, publishes the discovery announcement, and returns a bunker.
   * The raw nsec is returned ONCE so the caller can offer a backup, then callers
   * MUST drop it — the key is not stored anywhere after this.
   */
  async function signup({ centralURL, token, email, operators, threshold, secretKey, relays }) {
    const c = massageURL(centralURL);
    const ops = operators.map(massageURL);
    if (!(threshold >= 1 && threshold <= ops.length)) throw new Error('Invalid threshold for operator count.');
    const sk = secretKey || generateSecretKey$1();
    const pubkey = getPublicKey$1(sk);
    const session = crypto.randomUUID();

    // FROST-shard the key.
    const skBig = Array.from(sk).reduce((a, b) => (a << 8n) + BigInt(b), 0n);
    const { shards } = trustedKeyDeal(skBig, threshold, ops.length);

    // Register with central (kind 20445: threshold + public shards).
    const regEvent = finalizeEvent$1({
      kind: POM_KINDS.CENTRAL_REG,
      created_at: Math.floor(Date.now() / 1000),
      tags: [['threshold', String(threshold)], ...ops.map((op, i) => ['operator', op, hexPubShard(shards[i].pubShard)])],
      content: '',
    }, sk);
    const regResp = await fetch(c + '/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: 'Token ' + token, 'X-Pomegranate-Session': session },
      body: JSON.stringify(regEvent),
    });
    if (!regResp.ok) {
      const e = new Error('Central registration failed.');
      e.status = regResp.status;
      try { e.body = await regResp.text(); } catch {}
      throw e;
    }

    // Register the secret shard with each operator (kind 20444).
    for (let i = 0; i < ops.length; i++) {
      const opEvent = finalizeEvent$1({
        kind: POM_KINDS.OP_REG,
        created_at: Math.floor(Date.now() / 1000),
        tags: [['central', c], ['email', email]],
        content: hexShard(shards[i]),
      }, sk);
      const opToken = bytesToHex$4(await sha256Utf8(session + ':' + ops[i]));
      const opResp = await fetch(ops[i] + '/po/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Pomegranate-Operator-Token': opToken },
        body: JSON.stringify(opEvent),
      });
      if (!opResp.ok) {
        const e = new Error(`Operator registration failed for ${ops[i]}.`);
        e.operator = ops[i];
        e.status = opResp.status;
        try { e.body = await opResp.text(); } catch {}
        throw e;
      }
    }

    // Wait for central to see the account come online.
    let account = null;
    for (let i = 0; i < 15 && !account; i++) {
      account = await getAccount(c, token);
      if (account?.pubkey === pubkey) break;
      account = null;
      await new Promise(r => setTimeout(r, 1000));
    }
    if (!account) throw new Error('Account did not come online — check the operators.');

    await publishAnnouncement({ ...account, email }, c, sk, relays);
    const handler = await ensureDefaultProfile(c, token);
    return { pubkey, bunkerURI: bunkerURI(handler, c), nsec: nsecEncode$1(sk) };
  }

  // ── Server helpers (probe + URL validation) ─────────────────────────────────────
  /** Liveness probe: GET / with CORS and a short timeout. 2xx → 'up', else 'down'. */
  async function probeServer(url, { timeoutMs = 3000 } = {}) {
    let u;
    try { u = massageURL(url); } catch { return 'down'; }
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
      const r = await fetch(u + '/', { mode: 'cors', signal: ctrl.signal });
      return r.ok ? 'up' : 'down';
    } catch { return 'down'; }
    finally { clearTimeout(timer); }
  }

  /** Accept an https:// server URL (or http://localhost), host only — no path/query. */
  function isValidServerURL(input) {
    const s = String(input || '').trim();
    if (!s) return false;
    let url;
    try { url = new URL(s.includes('://') ? s : 'https://' + s); } catch { return false; }
    const isLocal = url.hostname === 'localhost' || url.hostname === '127.0.0.1';
    if (url.protocol !== 'https:' && !(url.protocol === 'http:' && isLocal)) return false;
    if (url.pathname !== '/' && url.pathname !== '') return false;
    if (url.search || url.hash) return false;
    return !!url.hostname;
  }

  // ── Replace the key behind an account ───────────────────────────────────────────
  /**
   * DELETE the pomegranate account at `central` (clears the account, its profiles
   * and handler keys, and any pending registration; existing bunker URIs stop
   * working). Idempotent — a `204` or any 2xx counts as done. Errors carry
   * `status`/`body` so a `401` can trigger one re-auth + retry by the caller.
   */
  async function deleteAccount(centralURL, token) {
    const c = massageURL(centralURL);
    const r = await fetch(c + '/account', { method: 'DELETE', headers: { Authorization: 'Token ' + token } });
    if (r.ok || r.status === 204) return true;
    const e = new Error('Could not erase the existing account.');
    e.status = r.status;
    try { e.body = await r.text(); } catch {}
    throw e;
  }

  /**
   * Open one operator's erase page (`/po/erase/google`). That page runs its own
   * Google OAuth and closes itself on BOTH "erase" and "cancel", sending no
   * message — so all we can observe is the window closing. Resolves then; the real
   * proof an erase happened is a later `/po/register` succeeding (a 403 means it
   * did not). Rejects only if the popup was blocked.
   */
  function erasePopup(operatorURL, { timeoutMs = 300_000 } = {}) {
    return new Promise((resolve, reject) => {
      const o = massageURL(operatorURL);
      const w = window.open(`${o}/po/erase/google`, 'pomegranate', 'width=600,height=680');
      if (!w) { reject(new Error('Popup blocked. Allow popups for this site and try again.')); return; }
      let done = false;
      const finish = () => { if (done) return; done = true; clearInterval(closed); clearTimeout(timer); resolve(); };
      const closed = setInterval(() => { if (w.closed) finish(); }, 500);
      const timer = setTimeout(() => { if (!w.closed) try { w.close(); } catch {} finish(); }, timeoutMs);
    });
  }

  /** True when an operator refused re-registration because its old shard wasn't erased. */
  function isShardConflict(err) {
    return !!(err && err.status === 403 && /different pubkey/i.test(err.body || ''));
  }

  // ── Recovery ────────────────────────────────────────────────────────────────
  /** OAuth against one operator to retrieve that operator's stored shard (hex). */
  async function requestOperatorShard(operatorURL) {
    const o = massageURL(operatorURL);
    const data = await oauthPopup(`${o}/po/recover/google`, o);
    const shard = data.shard || data.token;
    if (!shard) throw new Error('Operator did not return a shard.');
    return shard;
  }
  /** Reconstruct the secret key from >= threshold recovered shard hexes. */
  function reconstructFromShards(shardHexes) {
    const sk = aggregateSecretKeyShards(shardHexes.map(hexToBytes$2).map(decodeShard));
    const skBytes = sk instanceof Uint8Array ? sk : hexToBytes$2(sk.toString(16).padStart(64, '0'));
    return { privHex: bytesToHex$4(skBytes), nsec: nsecEncode$1(skBytes), npub: npubEncode$1(getPublicKey$1(skBytes)) };
  }

  // sha256 of a utf-8 string → bytes (WebCrypto; avoids importing another hash).
  async function sha256Utf8(s) {
    return new Uint8Array(await crypto.subtle.digest('SHA-256', enc.encode(s)));
  }

  /**
   * MILL — mill-core.js
   * Vanilla JS Web Component. Zero React. Zero framework deps.
   * Registers <nostr-signer> custom element + exposes MILL global API.
   *
   * Usage (script tag / CDN):
   *   <script src="mill-core.js"></script>
   *   <nostr-signer theme="dark"></nostr-signer>
   *   document.querySelector('nostr-signer').addEventListener('mill:connected', e => console.log(e.detail));
   *
   * Usage (ESM):
   *   import MILL from 'mill';
   *   MILL.open({ theme: 'dark', onConnected: signer => ... });
   */


  // njump ecosystem defaults, used when a host enables pomegranate without naming
  // its own servers (MILL.open({ pomegranate: true })). Mirrors fiatjaf's admin
  // client: central auth.njump.me, four independently-run operators (3-of-4).
  const POM_DEFAULT_CENTRAL = 'auth.njump.me';
  const POM_DEFAULT_OPERATORS = ['po.f7z.io', 'po.coracle.social', 'po.njump.me', 'po.jumble.social'];

  // ── Signing permission categories ─────────────────────────────────────────────
  const SIGN_CATS = [
    { id: 'notes',    label: 'Text Notes & Reactions', desc: 'kind 1, 6, 7, 16', icon: '📝', def: 'session' },
    { id: 'profile',  label: 'Profile Updates',         desc: 'kind 0',            icon: '👤', def: 'prompt'  },
    { id: 'contacts', label: 'Follow List Changes',     desc: 'kind 3',            icon: '👥', def: 'prompt'  },
    { id: 'dms',      label: 'Encrypted Messages',      desc: 'kind 4, 13, 14, 1059', icon: '💬', def: 'prompt'  },
    { id: 'zaps',     label: 'Zap Requests',            desc: 'kind 9734, 9735',   icon: '⚡', def: 'prompt'  },
    { id: 'other',    label: 'All Other Event Kinds',   desc: 'everything else',   icon: '📋', def: 'prompt'  },
  ];

  const defaultPerms = () => Object.fromEntries(SIGN_CATS.map(c => [c.id, c.def]));

  // Map common host-side method aliases (e.g. grain's SigningMethod enum) onto
  // mill's internal method ids so MILL.restore() accepts either spelling.
  const RESTORE_METHOD_ALIASES = {
    browser_extension: 'nip07',
    bunker:            'nip46',
    amber:             'nip55',
    encrypted_key:     'privatekey',
    newkey:            'privatekey',
    none:              'readonly',
    // Google login builds a private-key signer from the cloud-recovered key;
    // after a reload the sessionStorage blob restores it exactly like privatekey.
    google:            'privatekey',
    // Pomegranate connects a NIP-46 bunker; restore rebuilds it from stored
    // bunker state exactly like a remote signer.
    pomegranate:       'nip46',
  };

  // These choose whether a category is PRE-APPROVED, not when a password is
  // typed. The password is a separate, session-level unlock — see
  // createPrivateKeySigner's two-gate split. Wire values stay 'session'/'prompt'
  // because they're part of the public `perms` shape and persisted state.
  const PERM_OPTS = [
    { id: 'session', label: 'Auto-approve', sublabel: 'this session', color: 'var(--mill-success)', icon: '✅',
      desc: 'Signs without asking, until you close this tab.' },
    { id: 'prompt',  label: 'Review',       sublabel: 'each time',    color: 'var(--mill-warning)', icon: '👀',
      desc: 'Shows you what is being signed, and you approve or reject it.' },
  ];

  const METHOD_META = {
    readonly:   { label: 'Read-Only',         icon: '👁',  color: 'var(--mill-muted)'   },
    privatekey: { label: 'Private Key',       icon: '🔑',  color: 'var(--mill-warning)' },
    nip07:      { label: 'Browser Extension', icon: '🧩',  color: 'var(--mill-accent)'  },
    nip46:      { label: 'Remote Signer',     icon: '📡',  color: 'var(--mill-teal)'    },
    nip55:      { label: 'Android Signer',    icon: '📱',  color: 'var(--mill-teal)'    },
    newkey:     { label: 'New Identity',      icon: '✨',  color: 'var(--mill-success)' },
    google:     { label: 'Google',            icon: googleLogo, color: 'var(--mill-accent)' },
    pomegranate:{ label: 'Google',            icon: googleLogo, color: 'var(--mill-accent)' },
  };

  const METHODS_LIST = [
    { id: 'google',     label: 'Google',             sub: 'Cloud login',   icon: googleLogo, secLabel: 'Easiest', secColor: 'var(--mill-success)' },
    { id: 'pomegranate',label: 'Google',             sub: 'Secure login',  icon: googleLogo, secLabel: 'Easiest', secColor: 'var(--mill-success)' },
    { id: 'nip07',      label: 'Browser Extension', sub: 'NIP-07',        icon: '🧩', secLabel: 'Recommended',  secColor: 'var(--mill-success)' },
    { id: 'nip46',      label: 'Remote Signer',     sub: 'NIP-46 Bunker', icon: '📡', secLabel: 'High security', secColor: 'var(--mill-teal)'    },
    { id: 'nip55',      label: 'Android Signer',    sub: 'NIP-55 · Amber',icon: '📱', secLabel: 'Android only',  secColor: 'var(--mill-warning)'    },
    { id: 'privatekey', label: 'Private Key',        sub: 'nsec / hex',    icon: '🔑', secLabel: 'Use with care', secColor: 'var(--mill-warning)' },
    { id: 'readonly',   label: 'Read Only',          sub: 'Public key',    icon: '👁', secLabel: 'View only',     secColor: 'var(--mill-muted)'   },
    { id: 'newkey',     label: 'New Identity',       sub: 'Generate keys', icon: '✨', secLabel: 'Brand new',     secColor: 'var(--mill-accent)'  },
  ];

  // Methods hidden from the default modal — code is intact, but hosts must opt in
  // via methods config. NIP-55 stays hidden not because it fails to connect (the
  // clipboard return path works with no host wire-up) but because Amber 6.2.2+
  // refuses to remember approvals for browser callers, so every signature costs a
  // full app switch. NIP-46 with Amber as a bunker is the better default.
  const DEFAULT_HIDDEN_METHODS = new Set(['nip55']);

  // ── Base CSS injected into Shadow DOM ─────────────────────────────────────────
  const BASE_CSS = `
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :host {
    --mill-bg:             #09080f;
    --mill-surface:        #100e1b;
    --mill-card:           #181528;
    --mill-card-hover:     #1f1c35;
    --mill-inset:          var(--mill-inset);
    --mill-inset-strong:   var(--mill-inset-strong);
    --mill-overlay:        rgba(4,3,10,0.78);
    --mill-border:         #2a2544;
    --mill-border-light:   #3e3860;
    --mill-accent:         oklch(0.67 0.28 282);
    --mill-accent-hover:   oklch(0.73 0.28 282);
    --mill-accent-dim:     oklch(0.67 0.28 282 / 0.13);
    --mill-teal:           oklch(0.67 0.18 195);
    --mill-teal-dim:       oklch(0.67 0.18 195 / 0.13);
    --mill-text:           #ede8fc;
    --mill-text-secondary: #9d94c0;
    --mill-muted:          #5e5880;
    --mill-danger:         oklch(0.65 0.24 15);
    --mill-danger-dim:     oklch(0.65 0.24 15 / 0.13);
    --mill-warning:        oklch(0.78 0.18 65);
    --mill-warning-dim:    oklch(0.78 0.18 65 / 0.13);
    --mill-success:        oklch(0.7 0.2 155);
    --mill-success-dim:    oklch(0.7 0.2 155 / 0.13);
    --mill-radius:         14px;
    --mill-border-width:   1px;
    --mill-border-style:   solid;
    --mill-shadow:         0 0 0 1px rgba(130,80,255,0.08), 0 24px 64px rgba(0,0,0,0.7), 0 0 80px oklch(0.67 0.28 282 / 0.06);
    --mill-font:           'Space Grotesk', system-ui, sans-serif;
    --mill-font-mono:      'JetBrains Mono', monospace;
    font-family: var(--mill-font);
    color: var(--mill-text);
  }

  @keyframes millFadeUp  { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
  @keyframes millSpin    { to { transform: rotate(360deg); } }

  .mill-overlay {
    position: fixed; inset: 0;
    background: var(--mill-overlay);
    backdrop-filter: blur(5px);
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
    z-index: 9999;
  }

  .mill-modal {
    width: 100%; max-width: 480px;
    background: var(--mill-surface);
    border: var(--mill-border-width) var(--mill-border-style) var(--mill-border-light);
    border-radius: calc(var(--mill-radius) + 4px);
    box-shadow: var(--mill-shadow);
    overflow: hidden;
    max-height: 92vh;
    display: flex; flex-direction: column;
    animation: millFadeUp 0.2s ease;
  }

  .mill-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 15px 20px;
    border-bottom: 1px solid var(--mill-border);
    flex-shrink: 0;
  }
  .mill-header-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--mill-accent);
    box-shadow: 0 0 8px var(--mill-accent);
    margin-right: 8px; display: inline-block;
  }
  .mill-header-label {
    font-size: 11px; font-weight: 600; letter-spacing: 0.07em;
    text-transform: uppercase; color: var(--mill-text-secondary);
  }
  .mill-close {
    background: none; border: none; cursor: pointer; font-size: 18px;
    color: var(--mill-muted); padding: 2px 6px; border-radius: 6px;
    font-family: var(--mill-font); line-height: 1;
    transition: color 0.15s;
  }
  .mill-close:hover { color: var(--mill-text); }

  .mill-body {
    padding: 22px 24px;
    overflow-y: auto; flex: 1;
    scrollbar-width: thin;
    scrollbar-color: var(--mill-border-light) transparent;
  }

  /* ─ Progress bar ─ */
  .mill-progress { display: flex; gap: 5px; margin-bottom: 20px; }
  .mill-progress-seg {
    height: 3px; border-radius: 2px;
    background: var(--mill-border);
    transition: all 0.3s ease; flex: 1;
  }
  .mill-progress-seg.active { background: var(--mill-accent); flex: 2.5; }
  .mill-progress-seg.done   { background: var(--mill-accent); }

  /* ─ Typography ─ */
  .mill-back {
    background: none; border: none; color: var(--mill-muted); cursor: pointer;
    font-size: 13px; padding: 0; margin-bottom: 10px; display: flex;
    align-items: center; gap: 4px; font-family: var(--mill-font);
    transition: color 0.15s;
  }
  .mill-back:hover { color: var(--mill-text); }
  .mill-title   { font-size: 19px; font-weight: 700; margin-bottom: 5px; }
  .mill-subtitle{ font-size: 13px; color: var(--mill-text-secondary); line-height: 1.6; margin-bottom: 18px; }

  /* ─ Badge ─ */
  .mill-badge {
    border-radius: 10px; padding: 10px 14px;
    font-size: 13px; line-height: 1.55;
    display: flex; gap: 10px; align-items: flex-start;
  }
  .mill-badge-icon { flex-shrink: 0; margin-top: 1px; }
  .mill-badge-title { font-weight: 600; margin-bottom: 3px; }
  .mill-badge-body  { color: var(--mill-text-secondary); }
  .mill-badge.info    { background: var(--mill-accent-dim);  border: 1px solid var(--mill-border-light); }
  .mill-badge.info    .mill-badge-title { color: var(--mill-accent);  }
  .mill-badge.warning { background: var(--mill-warning-dim); border: 1px solid var(--mill-warning); }
  .mill-badge.warning .mill-badge-title { color: var(--mill-warning); }
  .mill-badge.danger  { background: var(--mill-danger-dim);  border: 1px solid var(--mill-danger);  }
  .mill-badge.danger  .mill-badge-title { color: var(--mill-danger);  }
  .mill-badge.success { background: var(--mill-success-dim); border: 1px solid var(--mill-success); }
  .mill-badge.success .mill-badge-title { color: var(--mill-success); }
  .mill-badge.muted   { background: rgba(255,255,255,0.04); border: 1px solid var(--mill-border); }
  .mill-badge.muted   .mill-badge-title { color: var(--mill-muted);   }

  /* ─ Input ─ */
  .mill-field { display: flex; flex-direction: column; gap: 6px; }
  .mill-label { font-size: 13px; color: var(--mill-text-secondary); font-weight: 500; }
  .mill-input, .mill-textarea {
    background: var(--mill-inset);
    border: 1px solid var(--mill-border);
    border-radius: 10px; padding: 11px 14px;
    color: var(--mill-text); font-size: 13px;
    font-family: var(--mill-font); outline: none; width: 100%; resize: vertical;
    transition: border-color 0.15s;
  }
  .mill-input:focus, .mill-textarea:focus { border-color: var(--mill-border-light); }
  .mill-input.mono, .mill-textarea.mono { font-family: var(--mill-font-mono); }
  .mill-input.error, .mill-textarea.error { border-color: var(--mill-danger); }
  .mill-input::placeholder, .mill-textarea::placeholder { color: var(--mill-muted); }
  .mill-hint  { font-size: 12px; color: var(--mill-muted); line-height: 1.4; }
  .mill-error { font-size: 12px; color: var(--mill-danger); }

  /* ─ Buttons ─ */
  .mill-btn {
    border-radius: 10px; padding: 11px 20px; font-size: 14px; font-weight: 600;
    font-family: var(--mill-font); cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    transition: opacity 0.15s, filter 0.15s; border: 1px solid transparent;
  }
  .mill-btn:disabled { opacity: 0.42; cursor: not-allowed; }
  .mill-btn:not(:disabled):hover { filter: brightness(1.12); }
  .mill-btn.primary  { background: var(--mill-accent);     color: #fff; border-color: var(--mill-accent); }
  .mill-btn.secondary{ background: var(--mill-accent-dim); color: var(--mill-accent); border-color: var(--mill-border-light); }
  .mill-btn.ghost    { background: transparent; color: var(--mill-text-secondary); border-color: var(--mill-border); }
  .mill-btn.danger   { background: var(--mill-danger-dim); color: var(--mill-danger); border-color: var(--mill-danger); }
  .mill-btn.teal     { background: var(--mill-teal-dim);   color: var(--mill-teal);   border-color: var(--mill-teal); }
  .mill-btn.success  { background: var(--mill-success-dim);color: var(--mill-success);border-color: var(--mill-success); }
  .mill-btn.full     { width: 100%; }
  .mill-btn.small    { padding: 6px 14px; font-size: 12px; }

  /* ─ Footer row ─ */
  .mill-footer { display: flex; gap: 8px; justify-content: flex-end; margin-top: 22px; }

  /* ─ Key display ─ */
  .mill-key-box {
    background: var(--mill-inset-strong); border: 1px solid var(--mill-border);
    border-radius: 10px; padding: 10px 14px;
  }
  .mill-key-label {
    font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em;
    color: var(--mill-muted); margin-bottom: 7px;
  }
  .mill-key-row { display: flex; align-items: flex-start; gap: 10px; }
  .mill-key-value {
    font-family: var(--mill-font-mono); font-size: 12px;
    word-break: break-all; flex: 1; line-height: 1.65;
    color: var(--mill-accent);
    transition: color 0.2s, text-shadow 0.2s;
  }
  .mill-key-value.redacted {
    color: transparent;
    text-shadow: 0 0 10px var(--mill-accent);
    user-select: none;
  }
  .mill-key-actions { display: flex; gap: 5px; flex-shrink: 0; margin-top: 2px; }

  /* ─ Spinner ─ */
  .mill-spinner {
    border-radius: 50%;
    border-top-color: var(--mill-accent);
    animation: millSpin 0.9s linear infinite;
  }

  /* ─ Tab bar ─ */
  .mill-tabs {
    display: flex; background: var(--mill-inset);
    border-radius: 10px; padding: 4px; gap: 4px;
  }
  .mill-tab {
    flex: 1; padding: 8px 0; border-radius: 8px; font-size: 13px; font-weight: 600;
    cursor: pointer; font-family: var(--mill-font);
    background: transparent; border: 1px solid transparent;
    color: var(--mill-muted); transition: all 0.15s;
  }
  .mill-tab.active {
    background: var(--mill-card);
    border-color: var(--mill-border-light);
    color: var(--mill-text);
  }

  /* ─ Perm pill ─ */
  .mill-perm-pill { display: flex; gap: 3px; }
  .mill-perm-opt {
    padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 600;
    cursor: pointer; font-family: var(--mill-font); border: 1px solid var(--mill-border);
    color: var(--mill-muted); background: transparent; transition: all 0.15s;
  }

  /* ─ Check item ─ */
  .mill-check-item {
    display: flex; gap: 12px; align-items: flex-start;
    padding: 12px 14px; border-radius: 10px; cursor: pointer;
    background: var(--mill-inset); border: 1px solid var(--mill-border);
    transition: all 0.15s;
  }
  .mill-check-item.checked {
    background: var(--mill-success-dim);
    border-color: var(--mill-success);
  }
  .mill-check-box {
    width: 18px; height: 18px; border-radius: 4px; flex-shrink: 0;
    margin-top: 1px; display: flex; align-items: center; justify-content: center;
    font-size: 11px; color: #fff; transition: all 0.15s;
    background: transparent; border: 2px solid var(--mill-border-light);
  }
  .mill-check-item.checked .mill-check-box {
    background: var(--mill-success); border-color: var(--mill-success);
  }

  /* ─ Method card ─ */
  .mill-method-card {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px; background: var(--mill-card);
    border: 1px solid var(--mill-border); border-radius: 12px;
    cursor: pointer; text-align: left; width: 100%;
    transition: all 0.15s; font-family: var(--mill-font);
  }
  .mill-method-card:hover {
    background: var(--mill-card-hover);
    border-color: var(--mill-border-light);
  }
  .mill-method-icon {
    width: 42px; height: 42px; border-radius: 10px;
    background: var(--mill-inset); border: 1px solid var(--mill-border);
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
  }
  .mill-method-name  { font-size: 14.5px; font-weight: 600; color: var(--mill-text); }
  .mill-method-sub   { font-size: 11px; color: var(--mill-muted); font-family: var(--mill-font-mono); }
  .mill-method-desc  { font-size: 12px; color: var(--mill-text-secondary); line-height: 1.5; margin-top: 2px; }
  .mill-method-badge {
    font-size: 11px; font-weight: 600; border-radius: 20px;
    padding: 2px 8px; white-space: nowrap; border: 1px solid transparent;
  }
  .mill-arrow { font-size: 16px; color: var(--mill-muted); }

  /* ─ Divider ─ */
  .mill-divider { height: 1px; background: var(--mill-border); margin: 4px 0; }

  /* ─ Connected screen ─ */
  .mill-connected {
    display: flex; flex-direction: column; align-items: center; gap: 18px; padding: 8px 0 4px;
  }
  .mill-connected-avatar {
    width: 76px; height: 76px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 34px;
    border: 2px solid;
  }

  /* ─ Signing permissions editor ─ */
  .mill-perm { display: flex; flex-direction: column; gap: 8px; }

  /* Collapsed summary — the default view. Full editor is opt-in. */
  .mill-perm-summary {
    display: flex; align-items: center; gap: 11px;
    padding: 12px 14px;
    background: var(--mill-inset);
    border: 1px solid var(--mill-border);
    border-radius: 10px;
  }
  .mill-perm-summary-text { flex: 1; min-width: 0; }
  .mill-perm-summary-title {
    font-size: 13px; font-weight: 600; margin-bottom: 2px;
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
  }
  .mill-perm-summary-sub {
    font-size: 11.5px; color: var(--mill-text-secondary); line-height: 1.5;
  }
  .mill-perm-toggle {
    background: none; border: 1px solid var(--mill-border-light);
    color: var(--mill-text-secondary);
    font-family: var(--mill-font); font-size: 11.5px; font-weight: 600;
    padding: 6px 12px; border-radius: 8px; cursor: pointer;
    flex-shrink: 0; transition: all 0.15s; white-space: nowrap;
  }
  .mill-perm-toggle:hover { color: var(--mill-text); border-color: var(--mill-accent); }

  .mill-perm-legend {
    display: flex; flex-direction: column; gap: 4px;
    margin-bottom: 2px; font-size: 11.5px;
  }
  .mill-perm-legend-row {
    display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap;
  }

  .mill-perm-row {
    display: flex; align-items: center; justify-content: space-between;
    gap: 10px; padding: 9px 12px;
    background: var(--mill-inset);
    border: 1px solid var(--mill-border);
    border-radius: 10px;
  }
  .mill-perm-row-left {
    display: flex; gap: 9px; align-items: center; min-width: 0;
  }
  .mill-perm-row-label { font-size: 13px; font-weight: 500; }
  .mill-perm-row-kinds {
    font-size: 10.5px; color: var(--mill-muted); font-family: var(--mill-font-mono);
  }
  .mill-perm-pills {
    display: flex; gap: 3px; flex-shrink: 0;
    background: var(--mill-inset); border-radius: 20px; padding: 3px;
  }
  .mill-perm-pill {
    display: flex; align-items: center; gap: 4px;
    padding: 4px 11px; border-radius: 16px;
    font-family: var(--mill-font); font-size: 11.5px; font-weight: 600;
    border: 1px solid transparent; cursor: pointer;
    transition: all 0.15s; white-space: nowrap;
  }
  .mill-perm-pill-sub { font-size: 10px; opacity: 0.7; }

  /* ─ Signing consent card ─ */
  .mill-consent-head {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px; border-radius: 12px;
    background: var(--mill-inset); border: 1px solid var(--mill-border);
  }
  .mill-consent-icon { font-size: 26px; line-height: 1; flex-shrink: 0; }
  .mill-consent-ask { font-size: 15px; line-height: 1.45; min-width: 0; }
  .mill-consent-kind { font-weight: 700; color: var(--mill-accent); }
  .mill-consent-as {
    font-size: 11.5px; color: var(--mill-muted); margin-top: 4px;
    overflow-wrap: anywhere;
  }

  .mill-consent-toggle {
    background: none; border: none; cursor: pointer;
    color: var(--mill-text-secondary); font-family: var(--mill-font);
    font-size: 12px; font-weight: 600; padding: 6px 0;
    display: flex; align-items: center; gap: 5px; align-self: flex-start;
  }
  .mill-consent-toggle:hover { color: var(--mill-text); }

  .mill-consent-details {
    background: var(--mill-inset); border: 1px solid var(--mill-border);
    border-radius: 10px; overflow: hidden;
  }
  .mill-consent-field {
    display: flex; gap: 10px; padding: 8px 12px;
    border-bottom: 1px solid var(--mill-border); font-size: 12px;
  }
  .mill-consent-field:last-child { border-bottom: none; }
  .mill-consent-field-k {
    color: var(--mill-muted); text-transform: uppercase; letter-spacing: 0.08em;
    font-size: 10px; font-weight: 600; width: 62px; flex-shrink: 0; padding-top: 2px;
  }
  .mill-consent-field-v {
    min-width: 0; flex: 1; overflow-wrap: anywhere; white-space: pre-wrap;
    font-family: var(--mill-font-mono); line-height: 1.5;
    max-height: 140px; overflow-y: auto;
  }

  .mill-consent-remember { display: flex; flex-direction: column; gap: 7px; }
  .mill-consent-remember-label {
    font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em;
    color: var(--mill-muted); font-weight: 600;
  }
  .mill-consent-durations { display: flex; flex-wrap: wrap; gap: 5px; }
  .mill-consent-dur {
    padding: 5px 11px; border-radius: 16px;
    font-family: var(--mill-font); font-size: 11.5px; font-weight: 600;
    border: 1px solid var(--mill-border); background: transparent;
    color: var(--mill-muted); cursor: pointer; transition: all 0.15s;
    white-space: nowrap;
  }
  .mill-consent-dur.active {
    border-color: var(--mill-accent); color: var(--mill-accent);
    background: color-mix(in srgb, var(--mill-accent) 13%, transparent);
  }
  .mill-consent-manage {
    background: none; border: none; cursor: pointer; padding: 0;
    color: var(--mill-muted); font-family: var(--mill-font);
    font-size: 11.5px; text-decoration: underline; align-self: flex-start;
  }
  .mill-consent-manage:hover { color: var(--mill-text-secondary); }

  /* ─ Permissions management ─ */
  .mill-grant-row {
    display: flex; align-items: center; justify-content: space-between;
    gap: 10px; padding: 9px 12px;
    background: var(--mill-inset); border: 1px solid var(--mill-border);
    border-radius: 10px;
  }
  .mill-grant-left { min-width: 0; }
  .mill-grant-kind { font-size: 13px; font-weight: 500; }
  .mill-grant-meta {
    font-size: 10.5px; color: var(--mill-muted); font-family: var(--mill-font-mono);
  }
  .mill-grant-actions { display: flex; gap: 4px; flex-shrink: 0; }
  .mill-grant-btn {
    padding: 4px 10px; border-radius: 14px;
    font-family: var(--mill-font); font-size: 11px; font-weight: 600;
    border: 1px solid transparent; background: transparent;
    color: var(--mill-muted); cursor: pointer; transition: all 0.15s;
  }

  /* Narrow viewports: stack the pills under the label so nothing overflows.
     Rules must live here (not inline) so this media query can win. */
  @media (max-width: 460px) {
    .mill-grant-row { flex-direction: column; align-items: stretch; gap: 8px; }
    .mill-grant-actions { width: 100%; }
    .mill-grant-btn { flex: 1; }
    .mill-consent-dur { flex: 1 1 auto; text-align: center; }
    .mill-perm-row { flex-direction: column; align-items: stretch; gap: 8px; }
    .mill-perm-pills { width: 100%; }
    .mill-perm-pill { flex: 1; justify-content: center; padding: 6px 8px; }
    .mill-perm-pill-sub { display: none; }
    .mill-perm-summary { flex-direction: column; align-items: stretch; gap: 10px; }
    .mill-perm-toggle { width: 100%; padding: 8px 12px; }
  }

  /* ─ Configurable modal footer ─ */
  .mill-modal-footer {
    margin-top: 18px; padding-top: 12px;
    border-top: 1px solid var(--mill-border);
    display: flex; flex-direction: column; gap: 8px;
  }
  .mill-foot-row {
    display: flex; align-items: center; justify-content: space-between;
    gap: 10px; flex-wrap: wrap;
  }
  .mill-foot-text {
    font-size: 11.5px; color: var(--mill-muted); line-height: 1.5;
  }
  .mill-foot-links { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
  .mill-foot-sep { color: var(--mill-border-light); font-size: 11px; }
  .mill-foot-link {
    font-size: 11.5px; font-weight: 600; color: var(--mill-accent);
    text-decoration: none;
  }
  .mill-foot-link:hover { text-decoration: underline; }
  .mill-foot-attr {
    display: inline-flex; align-items: center; gap: 6px; align-self: center;
    font-size: 10.5px; color: var(--mill-muted); text-decoration: none;
    transition: color 0.15s;
  }
  .mill-foot-attr:hover { color: var(--mill-text-secondary); }
  .mill-foot-attr-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--mill-accent); box-shadow: 0 0 6px var(--mill-accent);
    display: inline-block; flex-shrink: 0;
  }
`;

  // ── HTML builder helpers ──────────────────────────────────────────────────────
  function h(tag, attrs = {}, ...children) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (k === 'class') el.className = v;
      else if (k.startsWith('on')) el.addEventListener(k.slice(2).toLowerCase(), v);
      else if (k === 'style' && typeof v === 'object') Object.assign(el.style, v);
      else el.setAttribute(k, v);
    }
    for (const c of children.flat()) {
      if (c === null || c === undefined) continue;
      el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    }
    return el;
  }

  // A method icon may be an emoji string or a function that builds a node (used
  // for real brand logos). Normalise to something h() can append.
  function iconNode(icon, size) {
    return typeof icon === 'function' ? icon(size) : icon;
  }

  // Official multi-colour Google "G". Its brand colours are fixed by design and
  // intentionally NOT themed — recolouring it would be both wrong and off-brand.
  // Everything around it (tile, text, borders) still follows the palette.
  // The G on a white rounded tile — Google's prescribed presentation on coloured
  // or dark buttons, where the bare multi-colour mark would clash. Used on the
  // primary "Continue with Google" button.
  function googleLogoOnWhite(size = 18) {
    const pad = Math.round(size * 0.28);
    const tile = h('span', { style: {
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      width: `${size + pad * 2}px`, height: `${size + pad * 2}px`,
      background: '#fff', borderRadius: '5px', flexShrink: '0',
    } });
    tile.appendChild(googleLogo(size));
    return tile;
  }

  function googleLogo(size = 22) {
    const span = h('span', { style: { display: 'inline-flex', width: `${size}px`, height: `${size}px`, lineHeight: '0' } });
    span.innerHTML =
      `<svg viewBox="0 0 48 48" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg" aria-label="Google" role="img">` +
      `<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>` +
      `<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>` +
      `<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.28-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>` +
      `<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>` +
      `</svg>`;
    return span;
  }

  function badge(type, icon, title, body) {
    return h('div', { class: `mill-badge ${type}` },
      icon && h('span', { class: 'mill-badge-icon' }, icon),
      h('div', {},
        title && h('div', { class: 'mill-badge-title' }, title),
        h('div', { class: 'mill-badge-body' }, body)
      )
    );
  }

  function btn(label, variant, onClick, disabled = false) {
    // label may be a string, or an array of children (e.g. [logo, 'text']) so a
    // button can carry a brand logo alongside its text.
    const b = h('button', { class: `mill-btn ${variant}`, onClick }, ...[].concat(label));
    if (disabled) b.disabled = true;
    return b;
  }

  function progress(total, current) {
    const wrap = h('div', { class: 'mill-progress' });
    for (let i = 0; i < total; i++) {
      const seg = h('div', { class: 'mill-progress-seg' });
      if (i < current)  seg.classList.add('done');
      if (i === current) seg.classList.add('active');
      wrap.appendChild(seg);
    }
    return wrap;
  }

  function keyDisplay(label, value, redact = false) {
    let revealed = !redact;
    const code = h('code', { class: `mill-key-value${redact ? ' redacted' : ''}` }, value);
    const showBtn = redact ? btn(revealed ? 'Hide' : 'Show', 'ghost small', () => {
      revealed = !revealed;
      if (revealed) code.classList.remove('redacted'); else code.classList.add('redacted');
      showBtn.textContent = revealed ? 'Hide' : 'Show';
    }) : null;

    let copied = false;
    const copyBtn = btn('Copy', 'ghost small', () => {
      try { navigator.clipboard.writeText(value); } catch(e) {}
      if (!copied) {
        copied = true; copyBtn.textContent = '✓';
        copyBtn.style.color = 'var(--mill-success)';
        setTimeout(() => { copied = false; copyBtn.textContent = 'Copy'; copyBtn.style.color = ''; }, 2000);
      }
    });

    return h('div', { class: 'mill-key-box' },
      h('div', { class: 'mill-key-label' }, label),
      h('div', { class: 'mill-key-row' },
        code,
        h('div', { class: 'mill-key-actions' },
          ...[showBtn, copyBtn].filter(Boolean)
        )
      )
    );
  }

  function field(label, placeholder, value, onChange, { mono = false, type = 'text', hint, error, rows, inputmode, maxlength } = {}) {
    const wrap = h('div', { class: 'mill-field' });
    if (label) wrap.appendChild(h('label', { class: 'mill-label' }, label));
    const input = rows
      ? h('textarea', { class: `mill-textarea${mono ? ' mono' : ''}${error ? ' error' : ''}`, placeholder, rows: String(rows) })
      : h('input', { class: `mill-input${mono ? ' mono' : ''}${error ? ' error' : ''}`, placeholder, type });
    if (inputmode) input.setAttribute('inputmode', inputmode);
    if (maxlength) input.setAttribute('maxlength', String(maxlength));
    input.value = value;
    input.addEventListener('input', e => onChange(e.target.value));
    wrap.appendChild(input);
    if (hint && !error) wrap.appendChild(h('div', { class: 'mill-hint' }, hint));
    if (error) wrap.appendChild(h('div', { class: 'mill-error' }, error));
    return { wrap, input };
  }

  // Render a QR code for the given text into a 200x200 SVG element.
  // Uses qrcode-generator (typeNumber 0 = auto, errorCorrectLevel L = densest packing).
  function qr(text, { size = 200 } = {}) {
    const qr = qrcode(0, 'L');
    qr.addData(text);
    qr.make();
    // qrcode-generator's createSvgTag returns a string; we wrap it for sizing/color theming
    const wrap = h('div', {
      style: {
        width: `${size}px`, height: `${size}px`,
        background: '#fff', padding: '12px', borderRadius: '10px',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
      },
    });
    wrap.innerHTML = qr.createSvgTag({ scalable: true, margin: 0 });
    const svg = wrap.querySelector('svg');
    if (svg) { svg.setAttribute('width', '100%'); svg.setAttribute('height', '100%'); svg.style.display = 'block'; }
    return wrap;
  }

  function spinner(color = 'var(--mill-accent)', size = 36) {
    const el = h('div', { class: 'mill-spinner' });
    Object.assign(el.style, { width: `${size}px`, height: `${size}px`, border: `3px solid var(--mill-border)`, borderTopColor: color });
    return el;
  }

  function flowWrap({ step, total, title, subtitle, onBack }) {
    const wrap = h('div', {});
    if (total > 1) wrap.appendChild(progress(total, step));
    if (onBack) {
      const b = h('button', { class: 'mill-back', onClick: onBack }, '← Back');
      wrap.appendChild(b);
    }
    wrap.appendChild(h('div', { class: 'mill-title' }, title));
    if (subtitle) wrap.appendChild(h('div', { class: 'mill-subtitle' }, subtitle));
    const body = h('div', { style: { display: 'flex', flexDirection: 'column', gap: '12px' } });
    wrap.appendChild(body);
    const footer = h('div', { class: 'mill-footer' });
    wrap.appendChild(footer);
    return { wrap, body, footer };
  }

  // ── Signing behavior editor ───────────────────────────────────────────────────
  // Plain-language description of the current policy, for the collapsed summary.
  // Most users never open the editor, so this line has to carry the meaning on
  // its own — no jargon, no kind numbers.
  function permsSummary(perms) {
    const ids      = SIGN_CATS.map(c => c.id);
    const isCustom = ids.some(id => perms[id] !== SIGN_CATS.find(c => c.id === id).def);
    if (!isCustom) return 'Posts and reactions are signed automatically. Profile, follows, messages, and zaps are shown to you first.';

    const session = SIGN_CATS.filter(c => perms[c.id] === 'session');
    if (!session.length)             return 'Every request is shown to you before anything is signed.';
    if (session.length === ids.length) return 'Everything is signed automatically until you close this tab.';
    const names = session.map(c => c.label.toLowerCase());
    const list  = names.length === 1 ? names[0] : `${names.slice(0, -1).join(', ')} and ${names.at(-1)}`;
    return `Automatic for ${list}. Everything else is shown to you first.`;
  }

  function signingBehaviorEditor(perms) {
    const wrap = h('div', { class: 'mill-perm' });

    // Collapsed by default: the defaults are sensible, and the full six-category
    // grid is a lot of screen for a decision most users don't want to make.
    let open = false;
    const summary = h('div', { class: 'mill-perm-summary' });
    const details = h('div', { class: 'mill-perm', style: { display: 'none' } });

    const summaryText = h('div', { class: 'mill-perm-summary-sub' });
    const toggle = h('button', { class: 'mill-perm-toggle', type: 'button' });

    const refreshSummary = () => { summaryText.textContent = permsSummary(perms); };
    const applyOpen = () => {
      details.style.display = open ? 'flex' : 'none';
      toggle.textContent    = open ? 'Done' : 'Customize';
      toggle.setAttribute('aria-expanded', String(open));
      refreshSummary();
    };
    toggle.onclick = () => { open = !open; applyOpen(); };

    summary.appendChild(h('div', { class: 'mill-perm-summary-text' },
      h('div', { class: 'mill-perm-summary-title' },
        h('span', {}, '🔐'),
        h('span', {}, 'Signing permissions'),
        h('span', { style: { fontSize: '10.5px', fontWeight: '600', color: 'var(--mill-success)', textTransform: 'uppercase', letterSpacing: '0.08em' } }, 'Recommended')
      ),
      summaryText
    ));
    summary.appendChild(toggle);
    wrap.appendChild(summary);

    // Legend with descriptions
    const legend = h('div', { class: 'mill-perm-legend' });
    PERM_OPTS.forEach(o => {
      legend.appendChild(h('div', { class: 'mill-perm-legend-row' },
        h('span', {}, o.icon),
        h('span', { style: { fontWeight: '600', color: o.color } }, `${o.label} ${o.sublabel}`),
        h('span', { style: { color: 'var(--mill-muted)' } }, '—'),
        h('span', { style: { color: 'var(--mill-text-secondary)' } }, o.desc)
      ));
    });
    details.appendChild(legend);

    SIGN_CATS.forEach(cat => {
      const row = h('div', { class: 'mill-perm-row' });
      const left = h('div', { class: 'mill-perm-row-left' },
        h('span', { style: { fontSize: '17px' } }, cat.icon),
        h('div', { style: { minWidth: '0' } },
          h('div', { class: 'mill-perm-row-label' }, cat.label),
          h('div', { class: 'mill-perm-row-kinds' }, cat.desc)
        )
      );
      const pillBox = h('div', { class: 'mill-perm-pills' });
      PERM_OPTS.forEach(o => {
        const apply = (el, active) => {
          el.style.background  = active ? o.color + '22' : 'transparent';
          el.style.borderColor = active ? o.color : 'transparent';
          el.style.color       = active ? o.color : 'var(--mill-muted)';
        };
        const p = h('button', {
          class: 'mill-perm-pill',
          type: 'button',
          onClick: () => {
            perms[cat.id] = o.id;
            pillBox.querySelectorAll('button').forEach((pp, i) => apply(pp, PERM_OPTS[i].id === o.id));
            refreshSummary();
          },
        },
          h('span', { style: { fontSize: '11px' } }, o.icon),
          h('span', {}, o.label),
          h('span', { class: 'mill-perm-pill-sub' }, o.sublabel)
        );
        apply(p, perms[cat.id] === o.id);
        pillBox.appendChild(p);
      });
      row.appendChild(left); row.appendChild(pillBox);
      details.appendChild(row);
    });

    details.appendChild(badge('muted', 'ℹ️', null,
      'Anything set to Review shows you the event before it is signed, and you can remember that answer per kind at that point. Applies to private-key signing only — NIP-07, NIP-46, and NIP-55 approve requests in their own extension or app.'
    ));
    wrap.appendChild(details);
    applyOpen();
    return wrap;
  }

  // ── Flow: Method Selection ────────────────────────────────────────────────────
  function renderMethodSelection(host, onSelect, opts = {}) {
    const methodFilter = opts.methodFilter;
    const density      = opts.density || 'comfortable';      // 'compact' hides descs, smaller padding
    const layout       = opts.layout  || 'list';             // 'list' or 'grid'
    // callout: undefined → default 'newkey', null/false → disabled, string → that method id
    const calloutId    = opts.callout === undefined ? 'newkey' : opts.callout;
    const wrap = h('div', {});

    wrap.appendChild(renderBrandHeader(opts.header));

    // methodFilter accepts:
    //   undefined / [] → show all defaults (newkey appears as a separated callout above sign-in methods)
    //   ['nip07', 'nip46']           → only these, in this order; newkey is treated as just another card
    //   [{ id: 'nip07', label: 'My Ext', icon: '⚡' }, ...]  → override built-in fields
    // Google login only makes sense once the host has deployed an OAuth shim, so
    // it appears in the default picker only when configured. An explicit methods:
    // list still shows it if asked (clicking without a shim shows a clear
    // "not configured" screen rather than failing silently).
    // Two Google paths, each opt-in via config: pomegranate (FROST, cross-client)
    // when `pomegranate` is set — `true`/`{}` uses the njump ecosystem defaults, or
    // pass { central, operators, threshold } to self-host — and Drive+PIN (per-app)
    // when an oauth-shim is set. Pomegranate takes precedence so there's never a
    // double "Continue with Google". A "Google" affordance is available if either.
    const pomegranateAvailable = !!(host?._state?.pomegranate);
    const drivePinAvailable    = !!host?.getAttribute?.('oauth-shim');
    const googleAvailable      = pomegranateAvailable || drivePinAvailable;
    const explicit = Array.isArray(methodFilter) && methodFilter.length;
    const resolved = explicit
      ? methodFilter.map(entry => {
          const id = typeof entry === 'string' ? entry : entry?.id;
          const base = METHODS_LIST.find(m => m.id === id);
          if (!base) return null;
          return typeof entry === 'object' ? { ...base, ...entry } : base;
        }).filter(Boolean)
      : METHODS_LIST.filter(m => {
          if (DEFAULT_HIDDEN_METHODS.has(m.id)) return false;
          if (m.id === 'pomegranate') return pomegranateAvailable;
          if (m.id === 'google')      return drivePinAvailable && !pomegranateAvailable;
          return true;
        });

    // Callout: when not explicit AND callout id is enabled and present, separate it out.
    // When the consumer explicitly orders methods, respect their order (no separation) unless callout was explicitly set.
    const calloutEnabled = calloutId && (!explicit || opts.callout !== undefined);
    const calloutEntry   = calloutEnabled ? resolved.find(m => m.id === calloutId) : null;
    const signInList     = calloutEntry ? resolved.filter(m => m.id !== calloutId) : resolved;

    if (calloutEntry) {
      // When Google login is configured, "I'm new here" opens a chooser
      // (Continue with Google / Generate my own keys) instead of jumping
      // straight to key generation. With no Google shim set, behaviour is
      // unchanged — existing hosts see exactly the same screen as before.
      const calloutTarget = (calloutId === 'newkey' && googleAvailable) ? '_newhere' : calloutId;
      // Per-method callout copy. Default New-Identity copy if it's newkey.
      const calloutCopy = calloutId === 'newkey'
        ? { headline: "I'm new here!", subline: googleAvailable
            ? 'Get started in seconds. No email, no keys to manage.'
            : 'Create a new Nostr identity in seconds — no email, no signup.' }
        : { headline: calloutEntry.label, subline: calloutEntry.sub || '' };
      const callout = h('button', {
        class: 'mill-method-card',
        onClick: () => onSelect(calloutTarget),
        style: { padding: '10px 14px', background: 'var(--mill-accent-dim)', borderColor: 'var(--mill-accent)', borderStyle: 'dashed', marginBottom: '14px' },
      });
      callout.appendChild(h('div', { class: 'mill-method-icon', style: { width: '32px', height: '32px', fontSize: '17px' } }, iconNode(calloutEntry.icon, 18)));
      const txt = h('div', { style: { flex: '1', minWidth: '0' } });
      txt.appendChild(h('div', { style: { fontSize: '13.5px', fontWeight: '600', color: 'var(--mill-accent)' } }, calloutCopy.headline));
      txt.appendChild(h('div', { style: { fontSize: '12px', color: 'var(--mill-text-secondary)', marginTop: '2px', lineHeight: '1.4' } }, calloutCopy.subline));
      callout.appendChild(txt);
      callout.appendChild(h('span', { class: 'mill-arrow', style: { color: 'var(--mill-accent)' } }, '→'));
      wrap.appendChild(callout);

      wrap.appendChild(h('div', { style: { display: 'flex', alignItems: 'center', gap: '10px', margin: '4px 0 12px' } },
        h('div', { style: { flex: '1', height: '1px', background: 'var(--mill-border)' } }),
        h('span', { style: { fontSize: '10.5px', textTransform: 'uppercase', letterSpacing: '0.12em', color: 'var(--mill-muted)', fontWeight: '600' } }, 'or sign in'),
        h('div', { style: { flex: '1', height: '1px', background: 'var(--mill-border)' } })
      ));
    }

    const isCompact = density === 'compact';
    const isGrid    = layout === 'grid';

    const list = h('div', {
      style: isGrid
        ? { display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: isCompact ? '8px' : '10px' }
        : { display: 'flex', flexDirection: 'column', gap: isCompact ? '6px' : '10px' },
    });

    signInList.forEach(m => {
      const card = h('button', {
        class: 'mill-method-card',
        style: isCompact ? { padding: '10px 12px', gap: '10px' } : {},
        onClick: () => onSelect(m.id),
      });

      // Icon
      const iconEl = h('div', {
        class: 'mill-method-icon',
        style: isCompact ? { width: '32px', height: '32px', fontSize: '16px', flexShrink: '0' } : {},
      }, iconNode(m.icon, isCompact ? 18 : 24));
      card.appendChild(iconEl);

      // Middle: name (+ sub label inline if comfortable, or hidden if compact)
      const mid = h('div', { style: { flex: '1', minWidth: '0', display: 'flex', flexDirection: 'column', gap: '2px' } });
      const nameRow = h('div', { style: { display: 'flex', gap: '6px', alignItems: 'baseline', flexWrap: 'wrap' } });
      nameRow.appendChild(h('span', { class: 'mill-method-name', style: isCompact ? { fontSize: '13.5px' } : {} }, m.label));
      if (!isCompact) nameRow.appendChild(h('span', { class: 'mill-method-sub' }, m.sub));
      mid.appendChild(nameRow);
      if (!isCompact && !isGrid && m.desc) mid.appendChild(h('div', { class: 'mill-method-desc' }, m.desc));
      card.appendChild(mid);

      // Right: security badge (only in comfortable list mode); arrow always
      const right = h('div', { style: { display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '6px', flexShrink: '0' } });
      if (!isCompact && !isGrid) {
        const secBadge = h('span', { class: 'mill-method-badge' }, m.secLabel);
        secBadge.style.color = m.secColor;
        secBadge.style.background = m.secColor.replace(')', ' / 0.12)').replace('var(', 'color-mix(in srgb, var(');
        secBadge.style.borderColor = m.secColor + '44';
        right.appendChild(secBadge);
      }
      right.appendChild(h('span', { class: 'mill-arrow' }, '→'));
      card.appendChild(right);

      list.appendChild(card);
    });
    wrap.appendChild(list);

    // Picker tip. `opts.tip === false` hides it; a string overrides it; undefined
    // shows the default recommendation.
    if (opts.tip !== false) {
      const tip = h('p', { style: { marginTop: '16px', fontSize: '11.5px', color: 'var(--mill-muted)', textAlign: 'center', lineHeight: '1.6' } });
      if (typeof opts.tip === 'string') tip.textContent = opts.tip;
      else tip.innerHTML = 'Not sure? <span style="color:var(--mill-accent);cursor:pointer">NIP-07 browser extension</span> is recommended.';
      wrap.appendChild(tip);
    }

    const foot = renderFooter(opts.footer);
    if (foot) wrap.appendChild(foot);

    return wrap;
  }

  const isImageUrl = s => typeof s === 'string' && /^(https?:\/\/|\/|data:image\/)/.test(s.trim());

  // The picker header / brand block.
  //
  // header = { logo?, logoHeight?, title?, message?, align?, label? }
  //   logo       — image URL (PNG/SVG/…) rendered at its natural size, or a short
  //                emoji/text. A broken image URL is dropped silently.
  //   logoHeight — px height for image logos (default 44).
  //   title      — main title.
  //   message    — a short line under the title.
  //   align      — 'left' (default) | 'center'.
  //
  // With no branding fields set, mill shows its own default header. As soon as any
  // of logo/title/message is provided, the block is fully the host's — no mill
  // wording leaks in. (`label` styles the modal's top strip, handled elsewhere.)
  function renderBrandHeader(header) {
    const hd = header || {};
    const custom = hd.logo || hd.title || hd.message;
    const align = hd.align === 'center' ? 'center' : 'left';
    const hdr = h('div', { style: {
      marginBottom: '22px', display: 'flex', flexDirection: 'column', gap: '10px',
      alignItems: align === 'center' ? 'center' : 'flex-start', textAlign: align,
    } });

    if (!custom) {
      // Default mill header: small mark tile + eyebrow, heading, description.
      const tile = h('div', { style: { width: '32px', height: '32px', borderRadius: '8px', background: 'var(--mill-accent-dim)', border: '1px solid var(--mill-border-light)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '16px' } }, '⚡');
      hdr.style.gap = '6px';
      hdr.appendChild(h('div', { style: { display: 'flex', alignItems: 'center', gap: '10px' } },
        tile,
        h('span', { style: { fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.12em', color: 'var(--mill-muted)', fontWeight: '600' } }, 'Nostr Signer'),
      ));
      hdr.appendChild(h('div', { style: { fontSize: '22px', fontWeight: '700' } }, 'Connect Your Account'));
      hdr.appendChild(h('div', { style: { fontSize: '13px', color: 'var(--mill-text-secondary)', lineHeight: '1.55' } },
        'Choose how to access this Nostr client. Each method has different security tradeoffs.'));
      return hdr;
    }

    // Custom brand block.
    if (hd.logo) {
      if (isImageUrl(hd.logo)) {
        const img = h('img', { src: hd.logo.trim(), alt: hd.title || '', style: { height: `${hd.logoHeight || 44}px`, maxWidth: '100%', objectFit: 'contain', display: 'block' } });
        img.addEventListener('error', () => img.remove());   // no broken-image icon
        hdr.appendChild(img);
      } else {
        hdr.appendChild(h('div', { style: { fontSize: '36px', lineHeight: '1' } }, String(hd.logo).trim()));
      }
    }
    if (hd.title)   hdr.appendChild(h('div', { style: { fontSize: '22px', fontWeight: '700' } }, hd.title));
    if (hd.message) hdr.appendChild(h('div', { style: { fontSize: '13px', color: 'var(--mill-text-secondary)', lineHeight: '1.55', maxWidth: '340px' } }, hd.message));
    return hdr;
  }

  // Where mill's "Signer by MILL" attribution points by default. A host can
  // override it (footer.attributionHref) or turn it off (footer.attribution:false).
  const DEFAULT_MILL_URL = 'https://github.com/0ceanslim/nostr-mill';

  // Configurable modal footer shown under the method picker. Two independent
  // parts, both optional:
  //   - the host's own line: a tagline + links (Terms / Privacy / …)
  //   - a small "Signer by MILL" attribution, ON by default, toggleable
  // footer = { text?, links?: [{label, href}], attribution?: boolean, attributionHref? }
  function renderFooter(footer = {}) {
    const links = Array.isArray(footer.links) ? footer.links.filter(l => l && l.label && l.href) : [];
    const hasHostRow = !!footer.text || links.length > 0;
    const showAttr = footer.attribution !== false;   // default on
    if (!hasHostRow && !showAttr) return null;

    const bar = h('div', { class: 'mill-modal-footer' });

    if (hasHostRow) {
      const row = h('div', { class: 'mill-foot-row' });
      if (footer.text) row.appendChild(h('span', { class: 'mill-foot-text' }, footer.text));
      if (links.length) {
        const lw = h('div', { class: 'mill-foot-links' });
        links.forEach((l, i) => {
          if (i) lw.appendChild(h('span', { class: 'mill-foot-sep' }, '·'));
          lw.appendChild(h('a', { class: 'mill-foot-link', href: l.href, target: '_blank', rel: 'noopener noreferrer' }, l.label));
        });
        row.appendChild(lw);
      }
      bar.appendChild(row);
    }

    if (showAttr) {
      const a = h('a', {
        class: 'mill-foot-attr',
        href: footer.attributionHref || DEFAULT_MILL_URL,
        target: '_blank', rel: 'noopener noreferrer',
        title: 'Add Nostr login to your own app with MILL',
      }, h('span', { class: 'mill-foot-attr-dot' }), h('span', {}, 'Signer by MILL'));
      bar.appendChild(a);
    }

    return bar;
  }

  // ── Flow: Read Only ───────────────────────────────────────────────────────────
  function renderReadOnlyFlow(host, onDone, onBack) {
    let step = 0, keyVal = '';
    const container = h('div', {});

    function render() {
      container.innerHTML = '';
      if (step === 0) {
        const { wrap, body, footer } = flowWrap({ step: 0, total: 2, title: 'Read-Only Access', subtitle: 'Browse content using your public key. Cannot sign, post, react, or send zaps.', onBack });
        body.appendChild(badge('muted', '👁', 'View-only mode', 'You can read your feed, explore profiles, and view notes — but cannot post, react, follow, or send zaps.'));
        let errMsg = '';
        const { wrap: fWrap} = field('Public Key', 'npub1… or 64-char hex pubkey', keyVal, v => { keyVal = v; errMsg = ''; }, { mono: true });
        body.appendChild(fWrap);
        body.appendChild(h('div', { style: { fontSize: '12px', color: 'var(--mill-muted)', lineHeight: '1.4' } }, 'Your npub1 starts with "npub1" and is ~63 characters long.'));
        const continueBtn = btn('Continue', 'primary', () => {
          if (!isValidNpub(keyVal.trim())) { errMsg = 'Enter a valid npub1… or 64-char hex public key'; render(); return; }
          step = 1; render();
        });
        footer.appendChild(btn('Cancel', 'ghost', onBack));
        footer.appendChild(continueBtn);
        wrap.querySelector('.mill-field')?.after(errMsg ? h('div', { class: 'mill-error' }, errMsg) : null);
        container.appendChild(wrap);
      } else {
        const { wrap, body, footer } = flowWrap({ step: 1, total: 2, title: 'Confirm Public Key', subtitle: 'Connecting in read-only mode with the following identity.', onBack: () => { step = 0; render(); } });
        body.appendChild(keyDisplay('Your Public Key', keyVal.trim()));
        body.appendChild(badge('info', 'ℹ️', 'What read-only mode can do', 'View your home feed, explore profiles, read threads and replies, check notifications. Reconnect with a signing method to post.'));
        footer.appendChild(btn('Back', 'ghost', () => { step = 0; render(); }));
        footer.appendChild(btn('Connect Read-Only', 'primary', () => {
          const pk = npubToHex(keyVal.trim());
          onDone({ method: 'readonly', pubkey: pk, signer: createReadOnlySigner(pk) });
        }));
        container.appendChild(wrap);
      }
    }
    render();
    return container;
  }

  // ── Flow: NIP-07 ──────────────────────────────────────────────────────────────
  function renderNIP07Flow(host, onDone, onBack) {
    let step = 0, pubkey = '', errMsg = '', loading = false;
    const container = h('div', {});

    const exts = [
      { name: 'nos2x',    desc: 'Lightweight Nostr signer — Chrome / Firefox',                 url: 'https://github.com/fiatjaf/nos2x' },
      { name: 'Nostore',  desc: 'Safari & iOS NIP-07 signer',                                  url: 'https://apps.apple.com/us/app/nostore/id1666553677' },
      { name: 'Flamingo', desc: 'Social Nostr extension — Chrome',                             url: 'https://www.getflamingo.org/' },
      { name: 'Alby',     desc: 'Bitcoin & Nostr wallet — Chrome / Firefox / Safari',          url: 'https://getalby.com/' },
    ];

    async function connect(render) {
      loading = true; errMsg = ''; render();
      try {
        if (!window.nostr) throw new Error('No NIP-07 extension installed');
        pubkey = await window.nostr.getPublicKey();
        step = 1;
      } catch(e) { errMsg = e.message || 'Permission denied. Click the extension icon and try again.'; }
      loading = false; render();
    }

    function render() {
      container.innerHTML = '';
      if (step === 0) {
        const hasExt = !!window.nostr;
        const { wrap, body, footer } = flowWrap({ step: 0, total: 2, title: 'Browser Extension (NIP-07)', subtitle: 'Delegate all signing to a NIP-07 extension. Your private key never leaves it.', onBack });
        body.appendChild(hasExt
          ? badge('success', '✅', 'Extension detected', 'A NIP-07 compatible extension is installed. Click Connect to request your public key.')
          : badge('warning', '⚠️', 'No extension found', 'Install a NIP-07 extension below, then refresh and try again.')
        );
        body.appendChild(badge('info', '🔐', 'Why extensions are the safest option', 'The extension signs events in its own isolated sandbox. This app only sees your public key and completed signed events — never your private key.'));
        if (!hasExt) {
          const extList = h('div', { style: { display: 'flex', flexDirection: 'column', gap: '7px' } });
          extList.appendChild(h('div', { style: { fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.09em', color: 'var(--mill-muted)' } }, 'Compatible Extensions'));
          exts.forEach(ext => {
            extList.appendChild(h('a', { href: ext.url, target: '_blank', rel: 'noopener noreferrer', style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 12px', background: 'var(--mill-inset)', border: '1px solid var(--mill-border)', borderRadius: '10px', textDecoration: 'none', color: 'inherit', transition: 'border-color 0.15s' } },
              h('div', {},
                h('div', { style: { fontSize: '13.5px', fontWeight: '600', color: 'var(--mill-text)' } }, ext.name),
                h('div', { style: { fontSize: '12px', color: 'var(--mill-muted)', marginTop: '2px' } }, ext.desc)
              ),
              h('span', { style: { fontSize: '12px', color: 'var(--mill-accent)' } }, 'Install ↗')
            ));
          });
          body.appendChild(extList);
        }
        if (errMsg) body.appendChild(badge('danger', '✗', null, errMsg));
        if (loading) body.appendChild(h('div', { style: { display: 'flex', justifyContent: 'center', padding: '8px' } }, spinner()));
        const connectBtn = btn(loading ? 'Connecting…' : 'Connect Extension', 'primary', () => connect(render), loading);
        footer.appendChild(btn('Cancel', 'ghost', onBack));
        footer.appendChild(connectBtn);
        container.appendChild(wrap);
      } else {
        const { wrap, body, footer } = flowWrap({ step: 1, total: 2, title: 'Extension Connected', subtitle: 'Your public key was retrieved from the extension.', onBack: () => { step = 0; render(); } });
        body.appendChild(keyDisplay('Public Key (from extension)', pubkey));
        body.appendChild(badge('success', '✅', 'Signing delegated to extension', 'All signing requests will pop up in your extension. You can approve or reject each event individually.'));
        body.appendChild(badge('muted', '🔒', null, 'Disconnecting does not affect your extension or private key.'));
        footer.appendChild(btn('Back', 'ghost', () => { step = 0; render(); }));
        footer.appendChild(btn('Confirm Connection', 'primary', () => {
          const signer = createNIP07Signer(pubkey);
          onDone({ method: 'nip07', pubkey, signer });
        }));
        container.appendChild(wrap);
      }
    }
    render();
    return container;
  }

  // ── Flow: NIP-46 ──────────────────────────────────────────────────────────────
  function renderNIP46Flow(host, onDone, onBack, opts = {}) {
    let step = 0, tab = 'url', urlVal = '', errMsg = '', statusMsg = '', userPk = '', nostrconnectURI = '', authUrl = '';
    let relays = (Array.isArray(opts.relays) && opts.relays.length) ? [...opts.relays] : [...DEFAULT_RELAYS];
    let showRelayEditor = false;
    let client = null;
    let logs = [];                    // live diagnostic log shown in the connecting screen
    let logsRender = null;            // function to refresh just the log area
    const container = h('div', {});

    const onLog = (entry) => {
      logs.push(entry);
      if (logs.length > 60) logs.shift();
      logsRender?.();
    };

    function makeClient() {
      // The bunker shows this name when authorizing the connection. Hosts set
      // it via MILL.open({ appName }) (or the app-name attribute); fall back to
      // the page title, then a generic label — never the literal "MILL".
      const appName = host.getAttribute?.('app-name') || document.title || 'Nostr App';
      return new NIP46Client({
        relays,
        metadata: { name: appName, url: location.origin },
        debug: false,                // quiet by default; onLog still feeds the in-modal diagnostic panel
        onLog,
        // The signer asked the user to approve at a URL. Surface it (and open it
        // for web bunkers); the connect/get_public_key promise keeps waiting and
        // resolves once the user approves, advancing the flow automatically.
        onAuthChallenge: (url) => {
          authUrl = url;
          statusMsg = 'Approve the connection in your signer…';
          if (url) { try { window.open(url, '_blank', 'noopener'); } catch (_) {} }
          render();
        },
      });
    }

    async function connectViaURL(render) {
      if (!isValidBunker(urlVal.trim())) { errMsg = 'Enter a valid bunker:// or nostrconnect:// URI'; render(); return; }
      errMsg = ''; authUrl = ''; logs = []; step = 1; statusMsg = 'Connecting to relay…'; render();
      try {
        // Bunker URI carries its own relays; they take precedence inside the client.
        client = makeClient();
        statusMsg = 'Awaiting approval on bunker…'; render();
        userPk = await client.connectViaBunker(urlVal.trim(), { timeoutMs: 90_000 });
        step = 2; render();
      } catch (e) {
        const raw = (e && e.message) || 'NIP-46 connection failed';
        // A bunker:// secret is single-use (NIP-46): once a connection is
        // established the signer rejects the old secret. Re-pasting a used or
        // expired string is the usual cause of "bad secret" — guide the user to
        // grab a fresh connection string rather than showing the raw error.
        errMsg = /secret/i.test(raw)
          ? 'That bunker connection string was already used or has expired. Open your signer and copy a fresh bunker:// string, then try again.'
          : raw;
        try { client?.disconnect(); } catch {}
        client = null;
        step = 0; render();
      }
    }

    async function startNostrConnectListener(render) {
      logs = []; step = 1; statusMsg = 'Generating connection…'; errMsg = ''; authUrl = ''; render();
      try {
        client = makeClient();
        statusMsg = 'Scan the URI with your bunker…';
        userPk = await client.connectAsListener({
          timeoutMs: 180_000,
          onURI: u => { nostrconnectURI = u; render(); },
        });
        step = 2; render();
      } catch (e) {
        errMsg = e.message || 'NIP-46 connection failed';
        try { client?.disconnect(); } catch {}
        client = null;
        step = 0; render();
      }
    }

    function renderRelayEditor(render) {
      const wrap = h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', padding: '12px', background: 'var(--mill-inset)', border: '1px solid var(--mill-border)', borderRadius: '10px' } });
      wrap.appendChild(h('div', { style: { fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.1em', color: 'var(--mill-muted)', fontWeight: '600' } }, 'Active relays for this connection'));

      relays.forEach((r, i) => {
        const row = h('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } });
        row.appendChild(h('code', { style: { flex: '1', fontFamily: 'var(--mill-font-mono)', fontSize: '11.5px', color: 'var(--mill-text)', wordBreak: 'break-all' } }, r));
        row.appendChild(btn('×', 'ghost small', () => { relays.splice(i, 1); render(); }));
        wrap.appendChild(row);
      });

      const inputState = { v: '' };
      const { wrap: addWrap} = field(null, 'wss://your.relay/', '', v => inputState.v = v, { mono: true });
      const addRow = h('div', { style: { display: 'flex', gap: '6px', alignItems: 'flex-start' } });
      addRow.appendChild(addWrap);
      addRow.appendChild(btn('Add', 'ghost small', () => {
        const v = (inputState.v || '').trim();
        if (/^wss?:\/\//.test(v) && !relays.includes(v)) { relays.push(v); inputState.v = ''; render(); }
      }));
      addWrap.style.flex = '1';
      wrap.appendChild(addRow);

      const suggested = SUGGESTED_RELAYS.filter(r => !relays.includes(r));
      if (suggested.length) {
        wrap.appendChild(h('div', { style: { fontSize: '10.5px', color: 'var(--mill-muted)', marginTop: '4px' } }, 'Quick add:'));
        const chips = h('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '5px' } });
        suggested.forEach(r => {
          const c = h('button', { class: 'mill-btn ghost small', style: { fontSize: '11px', padding: '3px 9px' }, onClick: () => { relays.push(r); render(); } }, r.replace(/^wss?:\/\//, ''));
          chips.appendChild(c);
        });
        wrap.appendChild(chips);
      }
      return wrap;
    }

    function render() {
      container.innerHTML = '';
      if (step === 0) {
        const { wrap, body, footer } = flowWrap({ step: 0, total: 3, title: 'Remote Signer (NIP-46)', subtitle: 'Sign events on a separate device or server. Your private key never leaves your bunker.', onBack });
        body.appendChild(badge('info', '📡', 'What is a NIP-46 remote signer?', 'A bunker keeps your private key on a device you control. This client sends signing requests to the bunker over a Nostr relay; the bunker approves them remotely.'));

        const tabs = h('div', { class: 'mill-tabs' });
        ['url', 'qr'].forEach(t => {
          const tb = h('button', { class: `mill-tab${tab === t ? ' active' : ''}`, onClick: () => { tab = t; render(); } }, t === 'url' ? 'Bunker URL' : 'QR Code');
          tabs.appendChild(tb);
        });
        body.appendChild(tabs);

        if (tab === 'url') {
          const { wrap: fw } = field('Bunker Connection String', 'bunker://pubkey?relay=wss://…&secret=…', urlVal, v => { urlVal = v; errMsg = ''; }, { mono: true, rows: 3, error: errMsg });
          body.appendChild(fw);
          body.appendChild(h('div', { class: 'mill-hint' }, 'Get this from your bunker app: nsec.app, nsecBunker, or a self-hosted bunker.'));
          if (errMsg) body.appendChild(badge('danger', '✗', null, errMsg));
          footer.appendChild(btn('Cancel', 'ghost', onBack));
          footer.appendChild(btn('Connect to Bunker', 'primary', () => connectViaURL(render)));
        } else {
          body.appendChild(badge('info', '📲', 'Nostr Connect', 'Generate a connection string for your bunker to scan or paste. Mill will wait for the bunker to contact us on the selected relays.'));
          footer.appendChild(btn('Cancel', 'ghost', onBack));
          footer.appendChild(btn('Generate Connection String', 'primary', () => startNostrConnectListener(render)));
        }

        // Relay configuration — collapsed by default
        const relaySummary = h('button', {
          class: 'mill-back',
          style: { marginTop: '4px', textAlign: 'left' },
          onClick: () => { showRelayEditor = !showRelayEditor; render(); },
        }, `${showRelayEditor ? '▾' : '▸'} Relays (${relays.length})`);
        body.appendChild(relaySummary);
        if (showRelayEditor) body.appendChild(renderRelayEditor(render));

        container.appendChild(wrap);
      } else if (step === 1) {
        const { wrap, body } = flowWrap({ step: 1, total: 3, title: 'Connecting…', subtitle: statusMsg });
        const center = h('div', { style: { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '18px', padding: '16px 0' } });
        center.appendChild(spinner('var(--mill-accent)', 48));
        center.appendChild(h('div', { style: { fontSize: '13px', color: 'var(--mill-text-secondary)', textAlign: 'center' } }, statusMsg));
        body.appendChild(center);

        // Auth challenge: the signer wants the user to approve. We auto-opened
        // the URL; show it as a fallback (popup blockers) and keep waiting — the
        // flow advances on its own once approved.
        if (authUrl) {
          body.appendChild(badge('warning', '🔐', 'Approval required',
            'Your signer needs you to approve this connection. A tab should have opened — if not, use the button below. This screen continues automatically once you approve.'));
          const openBtn = h('a', {
            href: authUrl, target: '_blank', rel: 'noopener',
            class: 'mill-btn primary',
            style: { display: 'inline-flex', justifyContent: 'center', textDecoration: 'none', marginTop: '4px' },
          }, 'Open approval page');
          body.appendChild(openBtn);
        }

        if (nostrconnectURI) {
          const qrWrap = h('div', { style: { display: 'flex', justifyContent: 'center', padding: '4px 0' } });
          try { qrWrap.appendChild(qr(nostrconnectURI, { size: 220 })); } catch (e) { /* QR fail — keep URI fallback */ }
          body.appendChild(qrWrap);
          body.appendChild(keyDisplay('Nostr Connect URI', nostrconnectURI));
          body.appendChild(badge('info', '📲', null, 'Scan the QR with your bunker (Amber, nsec.app, etc.) — or copy the URI and paste it into the app.'));
        } else {
          body.appendChild(badge('warning', '📲', null, 'A connection request has been sent. Approve it on your signer device.'));
        }

        // Live diagnostic log — helps debug connection issues
        const logBox = h('div', {
          style: {
            marginTop: '8px',
            background: 'var(--mill-inset)',
            border: '1px solid var(--mill-border)',
            borderRadius: '8px',
            padding: '8px 10px',
            maxHeight: '160px',
            overflowY: 'auto',
            fontSize: '11px',
            fontFamily: 'var(--mill-font-mono)',
            color: 'var(--mill-text-secondary)',
            lineHeight: '1.55',
          },
        });
        const logTitle = h('div', { style: { fontSize: '10px', textTransform: 'uppercase', letterSpacing: '0.1em', color: 'var(--mill-muted)', marginBottom: '4px', fontFamily: 'var(--mill-font)' } }, 'Diagnostic log');
        const logList  = h('div', {});
        logBox.appendChild(logTitle);
        logBox.appendChild(logList);
        logsRender = () => {
          logList.innerHTML = '';
          logs.slice(-20).forEach(l => {
            logList.appendChild(h('div', { style: { color: l.level === 'err' ? 'var(--mill-danger)' : 'var(--mill-text-secondary)', whiteSpace: 'pre-wrap', wordBreak: 'break-all' } }, l.msg));
          });
          logBox.scrollTop = logBox.scrollHeight;
        };
        logsRender();
        body.appendChild(logBox);

        container.appendChild(wrap);
      } else {
        const { wrap, body, footer } = flowWrap({ step: 2, total: 3, title: 'Bunker Connected', subtitle: 'Your remote signer approved the connection.', onBack: () => { try{client?.disconnect();}catch{} client=null; step = 0; render(); } });
        body.appendChild(keyDisplay('User Public Key', userPk));
        body.appendChild(badge('success', '✅', 'Remote signing active', 'Signing requests will be forwarded to your bunker over the relay. Your bunker must be online to approve events.'));
        footer.appendChild(btn('Back', 'ghost', () => { try{client?.disconnect();}catch{} client=null; step = 0; render(); }));
        footer.appendChild(btn('Confirm Connection', 'primary', () => {
          // Persist the client identity + remote so MILL.restore() can re-present
          // the same already-authorized client to the bunker after a reload.
          storeBunkerState({
            clientSecretKey: bytesToHex$4(client.clientSecretKey),
            remotePubkey: client.remotePubkey,
            relays: client.relays,
            userPubkey: userPk,
          });
          const signer = createNIP46Signer(client, userPk);
          onDone({ method: 'nip46', pubkey: userPk, bunkerUrl: urlVal, signer });
        }));
        container.appendChild(wrap);
      }
    }
    render();
    return container;
  }

  // ── Flow: NIP-55 ──────────────────────────────────────────────────────────────
  function renderNIP55Flow(host, onDone, onBack) {
    let step = 0, pubkey = '', errMsg = '';
    // Only use a callback round-trip if the host explicitly opted in. Defaulting
    // to the current page never worked: Amber concatenates the result onto the
    // URL verbatim, so a URL with no `#event=` suffix loses it entirely. With no
    // callbackUrl, Amber falls back to the clipboard — which needs no host code.
    const callbackUrl = host.getAttribute?.('amber-callback') || null;
    const appName     = host.getAttribute?.('app-name') || document.title || 'Nostr App';
    const container = h('div', {});

    async function startAmber(render) {
      if (callbackUrl && isLocalhost()) {
        errMsg = 'Amber callbacks cannot reach localhost. Use NIP-07 or NIP-46 for local dev.';
        render(); return;
      }
      step = 1; errMsg = ''; render();
      try {
        const { buildAmberURL, openAmberIntent, awaitAmberResult, awaitAmberClipboard, snapshotClipboard } = await Promise.resolve().then(function () { return nip55; });
        // Snapshot before firing so stale clipboard content can't be misread.
        const before = callbackUrl ? '' : await snapshotClipboard();
        const url = buildAmberURL({ type: 'get_public_key', callbackUrl, appName });
        openAmberIntent(url);
        const raw = callbackUrl
          ? await awaitAmberResult({ timeoutMs: 60_000 })
          : await awaitAmberClipboard({ timeoutMs: 60_000, before });
        // For get_public_key, Amber returns the pubkey hex in `event` param
        pubkey = raw.toLowerCase().replace(/^npub1.*$/i, '');  // accept either
        if (!/^[0-9a-f]{64}$/.test(pubkey)) {
          try { pubkey = npubToHex(raw); } catch { pubkey = raw; }
        }
        step = 2; render();
      } catch (e) {
        errMsg = e.message || 'Amber connection failed';
        step = 0; render();
      }
    }

    function render() {
      container.innerHTML = '';
      if (step === 0) {
        const { wrap, body, footer } = flowWrap({ step: 0, total: 3, title: 'Android Signer (NIP-55)', subtitle: 'Use Amber or another Android signer app. Communication via Android intents — no network between apps.', onBack });
        body.appendChild(badge('info', '📱', 'How NIP-55 works', 'NIP-55 uses Android\'s intent system to send signing requests to a local app. No relay or internet needed between this app and your signer.'));
        body.appendChild(badge('warning', '⚠️', 'Android only', 'NIP-55 requires Android with a compatible signer app. On iOS or desktop, use NIP-07 (browser extension) or NIP-46 (remote signer) instead.'));
        body.appendChild(badge('warning', '🔁', 'Approves one request at a time', 'Amber 6.2.2+ deliberately never remembers approvals for web pages, so every single signature needs a fresh app switch. For anything beyond signing in, use Remote Signer (NIP-46) — Amber works as a bunker over relays, and you approve just once.'));
        body.appendChild(h('div', { style: { padding: '12px 14px', background: 'var(--mill-inset)', border: '1px solid var(--mill-border)', borderRadius: '10px' } },
          h('div', { style: { fontSize: '14px', fontWeight: '600', marginBottom: '3px' } }, 'Amber'),
          h('div', { style: { fontSize: '12px', color: 'var(--mill-muted)', lineHeight: '1.5' } }, 'Open-source Android NIP-55 signer by greenart7c3. Install from F-Droid, GitHub Releases, or Google Play.')
        ));
        if (errMsg) body.appendChild(badge('danger', '✗', null, errMsg));
        footer.appendChild(btn('Cancel', 'ghost', onBack));
        footer.appendChild(btn('Open Amber →', 'teal', () => startAmber(render)));
        container.appendChild(wrap);
      } else if (step === 1) {
        const { wrap, body } = flowWrap({ step: 1, total: 3, title: 'Waiting for Amber…', subtitle: 'Approve the connection request in the Amber app on your Android device.' });
        const center = h('div', { style: { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '18px', padding: '20px 0' } });
        center.appendChild(spinner('var(--mill-teal)', 48));
        center.appendChild(h('div', { style: { fontSize: '14px', color: 'var(--mill-text-secondary)', textAlign: 'center', lineHeight: '1.65' } }, 'Switch to Amber on your Android device and tap Approve on the connection request.'));
        body.appendChild(center);
        body.appendChild(badge('muted', '💡', null, "If Amber didn't open automatically, launch it manually and check for a pending auth request."));
        container.appendChild(wrap);
      } else {
        const { wrap, body, footer } = flowWrap({ step: 2, total: 3, title: 'Amber Connected', subtitle: 'Successfully linked to your Android signer.', onBack: () => { step = 0; render(); } });
        body.appendChild(keyDisplay('Public Key (from Amber)', pubkey));
        body.appendChild(badge('success', '✅', 'Android signing active', 'All signing requests will be sent to Amber as Android intents. Each event will show a prompt in Amber where you can approve or reject.'));
        footer.appendChild(btn('Back', 'ghost', () => { step = 0; render(); }));
        footer.appendChild(btn('Confirm Connection', 'primary', () => {
          const signer = createNIP55Signer({ pubkey, callbackUrl, appName });
          onDone({ method: 'nip55', pubkey, signer });
        }));
        container.appendChild(wrap);
      }
    }
    render();
    return container;
  }

  // ── Flow: Private Key ─────────────────────────────────────────────────────────
  function renderPrivateKeyFlow(host, onDone, onBack) {
    let step = 0, nsecVal = '', pw = '', pw2 = '', errMsg = '';
    const perms = Object.fromEntries(SIGN_CATS.map(c => [c.id, c.def]));
    const container = h('div', {});

    function render() {
      container.innerHTML = '';
      if (step === 0) {
        const { wrap, body, footer } = flowWrap({ step: 0, total: 4, title: 'Private Key Login', subtitle: 'Paste your nsec. It will be AES-256 encrypted with your password and stored only for this browser session.', onBack });
        body.appendChild(badge('danger', '⚠️', 'Keep your nsec secret', 'Your private key is the master credential for your Nostr identity. Anyone who obtains it can post as you, access your DMs, and permanently take over your account.'));
        const { wrap: fw } = field('Private Key (nsec or hex)', 'nsec1… or 64-char hex', nsecVal, v => { nsecVal = v; errMsg = ''; }, { mono: true, error: errMsg });
        body.appendChild(fw);
        if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
        body.appendChild(h('div', { class: 'mill-hint' }, 'This key never leaves your browser. It is encrypted locally before being stored in sessionStorage.'));
        footer.appendChild(btn('Cancel', 'ghost', onBack));
        footer.appendChild(btn('Continue', 'primary', () => {
          if (!isValidNsec(nsecVal.trim())) { errMsg = 'Enter a valid nsec1… or 64-char hex private key'; render(); return; }
          step = 1; render();
        }));
        container.appendChild(wrap);
      } else if (step === 1) {
        const { wrap, body, footer } = flowWrap({ step: 1, total: 4, title: 'Set Session Password', subtitle: 'This password encrypts your key while it sits in this browser. You enter it once per session to unlock signing — not for each event.', onBack: () => { step = 0; render(); } });
        body.appendChild(badge('info', '🔒', 'How encryption works', 'Your nsec is encrypted with AES-256-GCM using a PBKDF2-derived key (100k iterations, SHA-256). Stored in sessionStorage — wiped on tab close.'));
        const isOk = () => pw.length >= 4 && pw === pw2;
        const setBtn = btn('Set Password', 'primary', () => { if (isOk()) { step = 2; render(); } }, !isOk());
        const err1 = h('div', { class: 'mill-error' });
        const err2 = h('div', { class: 'mill-error' });
        const updateUi = () => {
          setBtn.disabled = !isOk();
          err1.textContent = pw && pw.length < 4 ? 'Minimum 4 characters' : '';
          err2.textContent = pw2 && pw !== pw2 ? 'Passwords do not match' : '';
        };
        const { wrap: pw1 } = field('Session Password', 'Minimum 4 characters', pw, v => { pw = v; updateUi(); }, { type: 'password' });
        const { wrap: pw2w } = field('Confirm Password', 'Repeat password', pw2, v => { pw2 = v; updateUi(); }, { type: 'password' });
        pw1.appendChild(err1); pw2w.appendChild(err2);
        body.appendChild(pw1); body.appendChild(pw2w);
        footer.appendChild(btn('Back', 'ghost', () => { step = 0; render(); }));
        footer.appendChild(setBtn);
        container.appendChild(wrap);
      } else if (step === 2) {
        const { wrap, body, footer } = flowWrap({ step: 2, total: 4, title: 'Signing Permissions', subtitle: 'Choose what gets signed automatically and what you want to see first. You can change any of this later. Only applies to private-key signing — NIP-07/46/55 approve things in their own apps.', onBack: () => { step = 1; render(); } });
        body.appendChild(signingBehaviorEditor(perms));
        footer.appendChild(btn('Back', 'ghost', () => { step = 1; render(); }));
        footer.appendChild(btn('Continue', 'primary', () => { step = 3; render(); }));
        container.appendChild(wrap);
      } else {
        const masked = nsecVal.slice(0, 12) + '•'.repeat(14) + nsecVal.slice(-6);
        const { wrap, body, footer } = flowWrap({ step: 3, total: 4, title: 'Review & Connect', subtitle: 'Confirm before connecting.', onBack: () => { step = 2; render(); } });
        body.appendChild(keyDisplay('Private Key (masked)', masked));
        const table = h('div', { style: { background: 'var(--mill-inset)', border: '1px solid var(--mill-border)', borderRadius: '10px', overflow: 'hidden' } });
        table.appendChild(h('div', { style: { padding: '8px 14px', borderBottom: '1px solid var(--mill-border)', fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.1em', color: 'var(--mill-muted)' } }, 'Signing Permissions'));
        SIGN_CATS.forEach((cat, i) => {
          const p = PERM_OPTS.find(o => o.id === perms[cat.id]);
          table.appendChild(h('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 14px', borderBottom: i < SIGN_CATS.length - 1 ? '1px solid var(--mill-border)' : 'none' } },
            h('span', { style: { fontSize: '13px', color: 'var(--mill-text-secondary)' } }, `${cat.icon} ${cat.label}`),
            h('span', { style: { fontSize: '12px', color: p?.color, fontWeight: '600' } }, p?.label)
          ));
        });
        body.appendChild(table);
        footer.appendChild(btn('Back', 'ghost', () => { step = 2; render(); }));
        footer.appendChild(btn('Connect with Private Key', 'primary', async () => {
          const hexKey   = nsecToHex(nsecVal.trim());
          const pubHex   = getPublicKey$1(hexToBytes$2(hexKey));
          const encrypted = await encryptNsec(hexKey, pw);
          storeEncryptedNsec(encrypted);
          storeSignPerms(perms);   // so MILL.restore() can rebuild with the same policy after reload
          const signer = createPrivateKeySigner({
            pubkey: pubHex, perms,
            promptPassword: sessionPrompt(host, pw),
            requestConsent: req => host.requestConsent({ ...req, npub: hexToNpub(pubHex) }),
          });
          onDone({ method: 'privatekey', pubkey: pubHex, perms, signer });
        }));
        container.appendChild(wrap);
      }
    }
    render();
    return container;
  }

  // Cloud-backup secret: 4–8 characters, letters and/or digits. A superset of
  // wisp's numeric-only PIN — a user can still type 4 digits, but may also use
  // letters for a bit more entropy. Kept short and low-friction on purpose; real
  // at-rest security is the cloud account, and the exported ncryptsec uses a full
  // passphrase. Deliberately not longer: this is a PIN, not a passphrase.
  const CLOUD_PIN_RE = /^[a-zA-Z0-9]{4,8}$/;
  const isValidPin = s => CLOUD_PIN_RE.test(s || '');
  const sanitizePin = s => (s || '').replace(/[^a-zA-Z0-9]/g, '').slice(0, 8);

  // ── Flow: "I'm new here" chooser ──────────────────────────────────────────────
  // Only reached when Google login is configured. Two ways to start: the normie
  // path (Google, key hidden) and the self-custody path (generate, save your own
  // key). Framed so the easy choice is obvious but the sovereign one is right
  // there — matching the user's goal of easy-onboarding-now, take-control-later.
  function renderNewHereChooser(host, onSelect, onBack) {
    const { wrap, body, footer } = flowWrap({
      step: 0, total: 1,
      title: 'Get Started',
      subtitle: 'Create your Nostr account. You can move to full self-custody whenever you want.',
      onBack,
    });

    const option = (icon, title, sub, primary, onClick) => {
      const card = h('button', {
        class: 'mill-method-card',
        onClick,
        style: { padding: '13px 15px', marginBottom: '10px',
          ...(primary ? { background: 'var(--mill-accent-dim)', borderColor: 'var(--mill-accent)' } : {}) },
      });
      card.appendChild(h('div', { class: 'mill-method-icon', style: { width: '34px', height: '34px', fontSize: '18px' } }, iconNode(icon, 20)));
      const txt = h('div', { style: { flex: '1', minWidth: '0' } });
      txt.appendChild(h('div', { style: { fontSize: '14px', fontWeight: '600', color: primary ? 'var(--mill-accent)' : 'var(--mill-text)' } }, title));
      txt.appendChild(h('div', { style: { fontSize: '12px', color: 'var(--mill-text-secondary)', marginTop: '2px', lineHeight: '1.45' } }, sub));
      card.appendChild(txt);
      card.appendChild(h('span', { class: 'mill-arrow', style: primary ? { color: 'var(--mill-accent)' } : {} }, '→'));
      return card;
    };

    // Route to whichever Google path the host configured (pomegranate wins).
    const googleMethod = host?._state?.pomegranate ? 'pomegranate' : 'google';
    body.appendChild(option(googleLogo, 'Continue with Google',
      'Easiest. Your key is created and safely stored for you — nothing to write down.',
      true, () => onSelect(googleMethod)));
    body.appendChild(option('🔑', 'Generate my own keys',
      'Advanced. You get your private key immediately and are responsible for backing it up.',
      false, () => onSelect('newkey')));

    footer.appendChild(btn('Back', 'ghost', onBack));
    return wrap;
  }

  // ── Flow: Continue with Google (cloud-backed key) ─────────────────────────────
  // The normie path. Mill generates and holds the key; the user sees a PIN, never
  // a key. Their nsec is encrypted and stored in their own Google Drive's hidden
  // app-data folder, so it survives across devices and browsers without the user
  // managing anything. "Take control of my keys" (the export screen) is where the
  // key becomes visible — hidden until asked for.
  function renderGoogleFlow(host, onDone, onBack) {
    const shimUrl = host.getAttribute?.('oauth-shim') || '';
    let step = shimUrl ? 'idle' : 'unconfigured';
    let errMsg = '', pin = '', pin2 = '';
    let mode = 'generate';         // 'generate' | 'import' — bring-your-own-key
    let nsecVal = '';              // pasted key when mode === 'import'
    let token = null;               // { accessToken, sub, ... }
    let backups = [];              // Drive file list
    let confirmRemove = null;      // file id pending a remove confirmation (manage screen)
    let unlockMatches = [];        // accounts that decrypted with the entered PIN (chooser)
    const container = h('div', {});

    // Drive ops need a token getter; a forced refresh re-opens the popup, since a
    // GIS access token can't be refreshed silently from here.
    const getToken = async (force) => {
      if (token && !force) return token.accessToken;
      token = await requestCloudToken(shimUrl);
      return token.accessToken;
    };

    async function connect(render) {
      step = 'connecting'; errMsg = ''; render();
      try {
        await getToken(false);
        backups = await withAuth(getToken, t => listBackups(t));
        step = backups.length ? 'unlock' : 'setup';
        render();
      } catch (e) {
        errMsg = e.message || 'Could not connect to Google.';
        step = 'idle'; render();
      }
    }

    async function refreshBackups() {
      backups = await withAuth(getToken, t => listBackups(t));
    }

    // Delete one stored key's Drive blob. Does NOT touch any cross-app recovery
    // event on relays — those are addressed by the phrase and can't be reached
    // from here, and are irrevocable regardless (see the NIP).
    async function removeBackup(fileId, render) {
      step = 'working'; errMsg = ''; confirmRemove = null; render();
      try {
        await withAuth(getToken, t => deleteBackup(t, fileId));
        await refreshBackups();
        step = backups.length ? 'manage' : 'setup';
        render();
      } catch (e) {
        errMsg = e.message || 'Could not remove that backup.';
        step = 'manage'; render();
      }
    }

    // Finish: encrypt the recovered/created key under the PIN for this session's
    // sessionStorage (same mechanism the private-key flow uses), build the signer.
    async function finish(privHex, npub, pubHex) {
      const perms = defaultPerms();
      const encrypted = await encryptNsec(privHex, pin);
      storeEncryptedNsec(encrypted);
      storeSignPerms(perms);
      const signer = createPrivateKeySigner({
        pubkey: pubHex, perms,
        promptPassword: sessionPrompt(host, pin),
        requestConsent: req => host.requestConsent({ ...req, npub }),
      });
      onDone({ method: 'google', pubkey: pubHex, perms, signer });
    }

    async function unlock(render) {
      step = 'working'; errMsg = ''; render();
      try {
        // Collect every backup the entered PIN decrypts. Usually one; if the user
        // gave several accounts the same PIN, more than one decrypts and we let
        // them choose rather than silently picking the newest.
        const matches = [];
        for (const f of backups) {
          try {
            const blob = await withAuth(getToken, t => downloadBackup(t, f.id));
            const privHex = await decryptCloudBlob(blob, pin);
            const pubHex = getPublicKey$1(hexToBytes$2(privHex));
            matches.push({ privHex, pubHex, npub: hexToNpub(pubHex) });
          } catch { /* wrong PIN or unrelated file — skip */ }
        }
        if (!matches.length) {
          errMsg = 'That PIN did not unlock any account. Try again.';
          step = 'unlock'; render(); return;
        }
        if (matches.length === 1) {
          const m = matches[0];
          await finish(m.privHex, m.npub, m.pubHex);
          return;
        }
        unlockMatches = matches; step = 'choose-account'; render();
      } catch (e) {
        errMsg = e.message || 'Something went wrong.';
        step = 'unlock'; render();
      }
    }

    // Save a NEW cloud account — either a freshly generated key or one the user
    // brought themselves (mode === 'import'). Upload BEFORE trusting it locally
    // (wisp's ordering) so a failed upload never leaves a key only on this device.
    async function saveNewKey(render) {
      step = 'working'; errMsg = ''; render();
      try {
        let privHex, npub, pubHex;
        if (mode === 'import') {
          privHex = nsecToHex(nsecVal.trim());
          pubHex  = getPublicKey$1(hexToBytes$2(privHex));
          npub    = hexToNpub(pubHex);
        } else {
          const keys = await generateKeypair();
          privHex = keys.privHex; npub = keys.npub; pubHex = keys.pubHex;
        }
        const blob = await encryptCloudBlob(privHex, pin);
        await withAuth(getToken, t => uploadBackup(t, blob));
        await finish(privHex, npub, pubHex);
      } catch (e) {
        errMsg = e.message || 'Could not save your account to Google.';
        step = 'setup'; render();
      }
    }

    function pinField(label, val, onInput) {
      // Allows letters as well as digits, so no forced numeric inputmode — a
      // number-only pad would hide the letters the 4–8 alphanumeric PIN permits.
      // Reflect the sanitised value back into the field so what's shown always
      // equals what's stored (otherwise a typed symbol appears but is dropped).
      const f = field(label, '4–8 letters or numbers', val, (v) => {
        const clean = sanitizePin(v);
        if (f.input && f.input.value !== clean) f.input.value = clean;
        onInput(clean);
      }, { type: 'password', maxlength: '8' });
      return f.wrap;
    }

    function render() {
      container.innerHTML = '';

      if (step === 'unconfigured') {
        const { wrap, body, footer } = flowWrap({ step: 0, total: 1, title: 'Google Sign-In Unavailable', subtitle: 'This app has not set up Google sign-in.', onBack });
        body.appendChild(badge('warning', '🔧', 'Not configured', 'The developer of this app needs to set an oauth-shim URL to enable “Continue with Google”. Use another sign-in method for now.'));
        footer.appendChild(btn('Back', 'primary', onBack));
        container.appendChild(wrap);

      } else if (step === 'idle') {
        const { wrap, body, footer } = flowWrap({ step: 0, total: 3, title: 'Continue with Google', subtitle: 'Create or restore your account. Your key is encrypted and stored in your own Google Drive — the app never sees it.', onBack });
        body.appendChild(badge('info', '🔒', 'How this works', 'A new Nostr key is created for you — or your existing one is restored, or you can import your own. It is encrypted with a PIN and saved to a private folder in your Google Drive that only this sign-in can read.'));
        if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
        footer.appendChild(btn('Back', 'ghost', onBack));
        footer.appendChild(btn([googleLogoOnWhite(18), 'Continue with Google'], 'primary', () => connect(render)));
        container.appendChild(wrap);

      } else if (step === 'connecting') {
        const { wrap, body } = flowWrap({ step: 1, total: 3, title: 'Connecting…', subtitle: 'Approve access in the Google window.' });
        const center = h('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '14px', padding: '30px 0' } });
        center.appendChild(spinner()); center.appendChild(h('span', { style: { color: 'var(--mill-text-secondary)' } }, 'Waiting for Google…'));
        body.appendChild(center);
        container.appendChild(wrap);

      } else if (step === 'working') {
        const { wrap, body } = flowWrap({ step: 2, total: 3, title: 'Almost there…', subtitle: 'Securing your account.' });
        const center = h('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '14px', padding: '30px 0' } });
        center.appendChild(spinner()); center.appendChild(h('span', { style: { color: 'var(--mill-text-secondary)' } }, 'One moment…'));
        body.appendChild(center);
        container.appendChild(wrap);

      } else if (step === 'unlock') {
        const okBtn = btn('Unlock', 'primary', () => { if (isValidPin(pin)) unlock(render); }, !isValidPin(pin));
        const { wrap, body, footer } = flowWrap({ step: 2, total: 3, title: 'Enter your PIN', subtitle: 'Welcome back. Enter the PIN you set to unlock your account.', onBack: () => { step = 'idle'; pin = ''; render(); } });
        const f = pinField('PIN', pin, v => { pin = sanitizePin(v); okBtn.disabled = !isValidPin(pin); });
        body.appendChild(f);
        if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
        const inp = f.querySelector('input'); if (inp) inp.addEventListener('keydown', e => { if (e.key === 'Enter' && isValidPin(pin)) unlock(render); });
        // Escape hatches so a stored backup is never a dead end: add/import a
        // different key, or manage (remove) what's stored.
        body.appendChild(h('div', { style: { display: 'flex', flexDirection: 'column', gap: '6px', marginTop: '10px' } },
          h('button', { class: 'mill-consent-manage', type: 'button', onClick: () => { mode = 'generate'; pin = ''; pin2 = ''; nsecVal = ''; errMsg = ''; step = 'setup'; render(); } }, 'Add or import a different account'),
          h('button', { class: 'mill-consent-manage', type: 'button', onClick: () => { confirmRemove = null; errMsg = ''; step = 'manage'; render(); } }, `Manage stored keys${backups.length > 1 ? ` (${backups.length})` : ''}`),
        ));
        footer.appendChild(btn('Back', 'ghost', () => { step = 'idle'; pin = ''; render(); }));
        footer.appendChild(okBtn);
        container.appendChild(wrap);

      } else if (step === 'manage') {
        const { wrap, body, footer } = flowWrap({ step: 2, total: 3, title: 'Manage Cloud Keys', subtitle: 'Keys stored in your Google Drive for this account. Removing one deletes only the cloud copy and cannot be undone.', onBack: () => { confirmRemove = null; step = backups.length ? 'unlock' : 'idle'; render(); } });
        if (!backups.length) {
          body.appendChild(badge('muted', '🗂', 'Nothing stored', 'There are no cloud keys for this account. Create or import one to get started.'));
        } else {
          backups.forEach((fb, i) => {
            const row = h('div', { class: 'mill-grant-row' });
            let when = '';
            try { if (fb.modifiedTime) when = new Date(fb.modifiedTime).toLocaleDateString(); } catch {}
            row.appendChild(h('div', { class: 'mill-grant-left' },
              h('div', { class: 'mill-grant-kind' }, `🔑 Stored key ${i + 1}`),
              h('div', { class: 'mill-grant-meta' }, `${String(fb.id).slice(0, 8)}…${when ? ` · ${when}` : ''}`),
            ));
            const pending = confirmRemove === fb.id;
            const rm = h('button', { class: 'mill-grant-btn', type: 'button',
              onClick: () => { if (pending) removeBackup(fb.id, render); else { confirmRemove = fb.id; render(); } } },
              pending ? 'Confirm remove' : 'Remove');
            if (pending) { rm.style.borderColor = 'var(--mill-danger)'; rm.style.color = 'var(--mill-danger)'; rm.style.background = 'color-mix(in srgb, var(--mill-danger) 12%, transparent)'; }
            row.appendChild(h('div', { class: 'mill-grant-actions' }, rm));
            body.appendChild(row);
          });
          body.appendChild(h('div', { class: 'mill-hint' }, 'These are opaque on purpose — the key inside is only revealed by unlocking with its PIN. Keep an independent copy of any key you still want before removing it.'));
        }
        if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
        footer.appendChild(btn('Back', 'ghost', () => { confirmRemove = null; step = backups.length ? 'unlock' : 'idle'; render(); }));
        footer.appendChild(btn('Add / import a key', 'primary', () => { mode = 'generate'; pin = ''; pin2 = ''; nsecVal = ''; errMsg = ''; step = 'setup'; render(); }));
        container.appendChild(wrap);

      } else if (step === 'choose-account') {
        const { wrap, body, footer } = flowWrap({ step: 2, total: 3, title: 'Choose an Account', subtitle: 'More than one account uses that PIN. Pick which one to sign in as.', onBack: () => { step = 'unlock'; pin = ''; unlockMatches = []; render(); } });
        unlockMatches.forEach((m, i) => {
          const card = h('button', { class: 'mill-method-card', onClick: () => finish(m.privHex, m.npub, m.pubHex) });
          card.appendChild(h('div', { class: 'mill-method-icon', style: { width: '34px', height: '34px', fontSize: '16px' } }, '🔑'));
          card.appendChild(h('div', { style: { flex: '1', minWidth: '0' } },
            h('div', { style: { fontSize: '13px', fontWeight: '600' } }, `Account ${i + 1}`),
            h('code', { style: { fontSize: '10.5px', fontFamily: 'var(--mill-font-mono)', color: 'var(--mill-accent)', wordBreak: 'break-all', display: 'block', marginTop: '2px', lineHeight: '1.4' } }, m.npub),
          ));
          card.appendChild(h('span', { class: 'mill-arrow' }, '→'));
          body.appendChild(card);
        });
        footer.appendChild(btn('Back', 'ghost', () => { step = 'unlock'; pin = ''; unlockMatches = []; render(); }));
        container.appendChild(wrap);

      } else if (step === 'setup') {
        const importing = mode === 'import';
        const keyOk = () => !importing || isValidNsec(nsecVal.trim());
        const ok    = () => isValidPin(pin) && pin === pin2 && keyOk();
        const okBtn = btn(importing ? 'Import & Save' : 'Create Account', 'primary', () => { if (ok()) saveNewKey(render); }, !ok());
        const { wrap, body, footer } = flowWrap({ step: 2, total: 3, title: importing ? 'Import Your Key' : 'Choose a PIN', subtitle: importing ? 'Bring an existing Nostr key and protect it with a PIN.' : 'Pick a PIN (4–8 letters or numbers). You will use it to unlock your account on other devices.', onBack: () => { step = backups.length ? 'unlock' : 'idle'; pin = ''; pin2 = ''; nsecVal = ''; render(); } });

        // Toggle: generate a fresh key, or bring your own.
        const seg = h('div', { style: { display: 'flex', gap: '4px', background: 'var(--mill-inset)', border: '1px solid var(--mill-border)', borderRadius: '10px', padding: '4px', marginBottom: '4px' } });
        const segBtn = (id, label) => {
          const active = mode === id;
          const b = h('button', { class: 'mill-btn', style: { flex: '1', padding: '8px', fontSize: '12.5px', background: active ? 'var(--mill-accent-dim)' : 'transparent', color: active ? 'var(--mill-accent)' : 'var(--mill-muted)', border: active ? '1px solid var(--mill-accent)' : '1px solid transparent' }, onClick: () => { mode = id; errMsg = ''; render(); } }, label);
          return b;
        };
        seg.appendChild(segBtn('generate', 'Create new key'));
        seg.appendChild(segBtn('import', 'Import my key'));
        body.appendChild(seg);

        const err = h('div', { class: 'mill-error' });
        const sync = () => { okBtn.disabled = !ok(); err.textContent = (pin2 && pin !== pin2) ? 'PINs do not match' : ''; };

        if (importing) {
          const { wrap: fw } = field('Private Key (nsec or hex)', 'nsec1… or 64-char hex', nsecVal, v => { nsecVal = v; errMsg = ''; sync(); }, { mono: true });
          body.appendChild(fw);
        }

        body.appendChild(pinField('PIN', pin, v => { pin = sanitizePin(v); sync(); }));
        body.appendChild(pinField('Confirm PIN', pin2, v => { pin2 = sanitizePin(v); sync(); }));
        body.appendChild(err);
        // Honest about what the PIN does and does not do — no security theatre.
        body.appendChild(badge('muted', 'ℹ️', 'About your PIN', 'The PIN stops someone casually opening your account. Your real protection is your Google account and its security — keep that locked down. If you forget the PIN, you can still recover using an exported key, if you saved one.'));
        if (importing) body.appendChild(badge('info', '🔑', 'Bringing your own key', 'Your key is encrypted with your PIN and uploaded to your Google Drive. Keep your original nsec backed up too — the PIN only protects this cloud copy.'));
        if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
        footer.appendChild(btn('Back', 'ghost', () => { step = backups.length ? 'unlock' : 'idle'; pin = ''; pin2 = ''; nsecVal = ''; render(); }));
        footer.appendChild(okBtn);
        container.appendChild(wrap);
      }
    }
    render();
    return container;
  }

  // ── Flow: Continue with Google (Pomegranate / FROST) ──────────────────────────
  // The cross-client Google path (fiatjaf's pomegranate). The key is FROST-sharded
  // across operators and never stored whole; Google authenticates the user to
  // those operators; signing runs over NIP-46 through a `central` coordinator.
  // Config: MILL.open({ pomegranate: true }) uses the njump ecosystem defaults
  // below; MILL.open({ pomegranate: { central, operators, threshold, relays,
  // pinCentral } }) self-hosts. Lives on host._state.pomegranate. EXPERIMENTAL.
  function renderPomegranateFlow(host, onDone, onBack) {
    const raw = host._state?.pomegranate;
    const cfg = (raw && typeof raw === 'object') ? raw : {};
    const uniq = a => a.filter((v, i) => a.indexOf(v) === i);
    const defaultCentral = massageURL(cfg.central || POM_DEFAULT_CENTRAL);
    const defaultOperators = (cfg.operators?.length ? cfg.operators : POM_DEFAULT_OPERATORS).map(massageURL);
    const centralChoices = uniq([defaultCentral, ...((cfg.centralChoices || []).map(massageURL))]);
    const operatorChoices = uniq([...defaultOperators, ...((cfg.operatorChoices || []).map(massageURL))]);
    const explicitThreshold = cfg.threshold;
    const relays = cfg.relays;
    const allowCustomCentral = cfg.allowCustomCentral !== false;
    const allowCustomOperators = cfg.allowCustomOperators !== false;
    const minOperators = cfg.minOperators || 3;
    const pinCentral = cfg.pinCentral !== false;   // DEFAULT true — no discovery redirect unless the host opts out

    // Threshold from operator count: honour an explicit host value while it leaves
    // fault tolerance (≤ n−1), else ~7/12 of n (min 2, capped at n): 4 → 3-of-4.
    const thresholdFor = n => (explicitThreshold && explicitThreshold <= n - 1)
      ? explicitThreshold : Math.min(n, Math.max(2, Math.ceil((n * 7) / 12)));
    const defaultThreshold = thresholdFor(defaultOperators.length);   // used by the recover flow

    // Selection state — drives auth / getAccount / signup / replace. Everything
    // "advanced" (central, operator checklist, threshold) is defaulted and hidden
    // behind the Advanced disclosure; most users never touch it.
    let selectedCentral = defaultCentral;
    let selectedOperators = operatorChoices.map(url => ({ url, checked: defaultOperators.includes(url) }));
    try {
      const saved = JSON.parse(localStorage.getItem('mill:pomegranate:servers') || 'null');
      if (saved) {
        let dirty = false;
        // Only restore servers the host still offers. A stored central/operator that
        // is no longer in the config (e.g. the host renamed/moved its central) would
        // otherwise pin returning visitors to a dead host — so drop it and fall back
        // to the default rather than persisting an arbitrary URL that can rot.
        if (saved.central) {
          const c = massageURL(saved.central);
          if (centralChoices.includes(c)) selectedCentral = c; else dirty = true;
        }
        if (Array.isArray(saved.operators)) {
          const offered = saved.operators.map(massageURL).filter(u => operatorChoices.includes(u));
          if (offered.length) selectedOperators.forEach(o => { o.checked = offered.includes(o.url); });
          if (offered.length !== saved.operators.length) dirty = true;
        }
        if (dirty) { try { localStorage.setItem('mill:pomegranate:servers', JSON.stringify({ central: selectedCentral, operators: selectedOperators.filter(o => o.checked).map(o => o.url) })); } catch {} }
      }
    } catch {}
    const chosenOperators = () => selectedOperators.filter(o => o.checked).map(o => o.url);
    const effThreshold = () => thresholdFor(chosenOperators().length);
    const isDefaultSelection = () => selectedCentral === defaultCentral &&
      chosenOperators().length === defaultOperators.length && chosenOperators().every(u => defaultOperators.includes(u));
    const persistServers = () => { try { localStorage.setItem('mill:pomegranate:servers', JSON.stringify({ central: selectedCentral, operators: chosenOperators() })); } catch {} };
    const resetServers = () => {
      selectedCentral = defaultCentral;
      selectedOperators = operatorChoices.map(url => ({ url, checked: defaultOperators.includes(url) }));
      try { localStorage.removeItem('mill:pomegranate:servers'); } catch {}
    };

    let step = 'idle';
    let advancedOpen = false;             // Advanced disclosure on the idle screen
    let customCentralMode = false;        // "Custom…" chosen in the central select
    const probeStatus = {};               // operatorURL -> 'up' | 'down' (idle-screen dots)
    let skipped = [];                     // [{ url, reason }] left out by a resilient signup
    let signupMeta = null;                // { central, operators, threshold, skipped } for onConnected
    const flowLog = [];                   // technical server responses, shown under a "Details" disclosure
    let logOpen = false;
    let connectToken = 0;                  // bumped to invalidate a cancelled/superseded connect attempt
    const hostOf = u => String(u || '').replace(/^https?:\/\//, '');
    const logLine = m => { flowLog.push(m); };
    let errMsg = '', statusMsg = '', createdNsec = '', nsecSaved = false;
    let recovered = null;                 // { privHex, nsec, npub } from recovery
    let auth = null;                      // { centralURL, token, email } after Google login, before account creation
    let mode = 'generate';               // 'generate' | 'import' — bring-your-own-key at signup
    let nsecVal = '';                    // pasted key when mode === 'import'
    let intent = 'signin';               // 'signin' | 'replace' — replace swaps the key behind this Google account
    let account = null;                   // { pubkey, operators, threshold } of the account being replaced
    let returnTo = '';                    // where the recover step's Done returns (e.g. 'replace-confirm')
    let backedUp = false;                 // set once the user backs up the old key during a replace
    let ackReplace = false;               // "I understand my key will be erased" checkbox
    let foundCtx = null;                   // { token, email, foundCentral } when discovery points elsewhere
    const eraseRequested = {};            // operatorURL -> true once its erase popup has opened+closed
    const shards = {};                    // operatorURL -> shard hex (recovery)
    const container = h('div', {});
    const appName = () => host.getAttribute?.('app-name') || document.title || 'Nostr App';

    // Operators that hold the CURRENT account's shards (may differ from configured).
    const eraseTargets = () => (account?.operators || []).map(o => massageURL(typeof o === 'string' ? o : o.url));

    // Shared generate/import chooser (used by new-account and replace-confirm):
    // appends the toggle + (when importing) the nsec field to `body`, wires
    // mode/nsecVal, and calls syncBtn() on input so the caller gates its button.
    function keyChooser(body, syncBtn) {
      const seg = h('div', { style: { display: 'flex', gap: '4px', background: 'var(--mill-inset)', border: '1px solid var(--mill-border)', borderRadius: '10px', padding: '4px' } });
      const segBtn = (id, label) => h('button', { class: 'mill-btn', style: { flex: '1', padding: '8px', fontSize: '12.5px', background: mode === id ? 'var(--mill-accent-dim)' : 'transparent', color: mode === id ? 'var(--mill-accent)' : 'var(--mill-muted)', border: mode === id ? '1px solid var(--mill-accent)' : '1px solid transparent' }, onClick: () => { mode = id; errMsg = ''; render(); } }, label);
      seg.appendChild(segBtn('generate', 'Create new key'));
      seg.appendChild(segBtn('import', 'Import my key'));
      body.appendChild(seg);
      if (mode === 'import') {
        const { wrap: fw } = field('Private Key (nsec or hex)', 'nsec1… or 64-char hex', nsecVal, v => { nsecVal = v; errMsg = ''; syncBtn(); }, { mono: true });
        body.appendChild(fw);
      }
    }

    function checkboxRow(label, checked, onChange) {
      const input = h('input', { type: 'checkbox', style: { marginTop: '3px', flex: '0 0 auto' } });
      input.checked = checked;
      input.addEventListener('change', e => onChange(e.target.checked));
      return h('label', { style: { display: 'flex', gap: '8px', alignItems: 'flex-start', cursor: 'pointer', fontSize: '13px', color: 'var(--mill-text-secondary)', lineHeight: '1.4' } }, input, h('span', {}, label));
    }

    // Collapsed "Details" disclosure holding the raw server responses from the flow.
    function renderLog(body) {
      if (!flowLog.length) return;
      body.appendChild(h('button', { class: 'mill-consent-manage', type: 'button', style: { marginTop: '2px' },
        onClick: () => { logOpen = !logOpen; render(); } }, `${logOpen ? '▾' : '▸'} Details (${flowLog.length})`));
      if (logOpen) {
        const box = h('div', { style: { maxHeight: '150px', overflowY: 'auto', background: 'var(--mill-inset)', border: '1px solid var(--mill-border)', borderRadius: '8px', padding: '8px', fontFamily: 'var(--mill-font-mono)', fontSize: '11px', lineHeight: '1.5', color: 'var(--mill-text-secondary)', whiteSpace: 'pre-wrap', wordBreak: 'break-word' } });
        box.textContent = flowLog.join('\n');
        body.appendChild(box);
      }
    }

    // Background liveness probe for the Advanced operator dots (open + Add only).
    async function probeAdvanced(render, subset) {
      const targets = subset || selectedOperators.map(o => o.url);
      await Promise.all(targets.map(async u => { probeStatus[u] = await probeServer(u); }));
      render();
    }

    // Take a pomegranate bunker URI and connect via mill's existing NIP-46 path.
    // Fails fast (not the 90 s default) with the relay named, so a dead central is
    // diagnosable, and is cancellable from the connecting screen.
    async function connectBunker(bunkerURI, render) {
      const myToken = ++connectToken;
      const relayUrl = decodeURIComponent((String(bunkerURI).match(/[?&]relay=([^&]+)/) || [])[1] || '');
      logLine(`connecting to signer${relayUrl ? ` via ${relayUrl}` : ''}…`);
      step = 'connecting-signer'; statusMsg = 'Connecting to your signer…'; errMsg = ''; render();
      try {
        const client = new NIP46Client({ relays: DEFAULT_RELAYS, metadata: { name: appName(), url: location.origin }, debug: false });
        const userPk = await client.connectViaBunker(bunkerURI, { timeoutMs: 20_000 });
        if (myToken !== connectToken) return;   // a Cancel (or newer attempt) superseded this one
        storeBunkerState({
          clientSecretKey: bytesToHex$4(client.clientSecretKey),
          remotePubkey: client.remotePubkey, relays: client.relays, userPubkey: userPk,
        });
        const signer = createNIP46Signer(client, userPk);
        persistServers();   // remember the selected central/operators after a real success
        logLine('signer connected');
        onDone({ method: 'pomegranate', pubkey: userPk, bunkerUrl: bunkerURI, signer, nsec: createdNsec || undefined, pomegranate: signupMeta || { central: selectedCentral } });
      } catch (e) {
        if (myToken !== connectToken) return;   // cancelled
        logLine(`signer connect failed: ${e.message || e}`);
        errMsg = relayUrl
          ? `Couldn’t reach your signer relay (${relayUrl}). It may be offline — see Details.`
          : 'Couldn’t connect to your signer — see Details.';
        step = 'idle'; render();
      }
    }

    // Stop waiting on a connect (the pending attempt is abandoned via connectToken).
    function cancelConnect(render) {
      connectToken++; logLine('connection cancelled'); errMsg = ''; statusMsg = ''; step = 'idle'; render();
    }

    // Route to the right step once we hold a valid token for `centralURL`.
    async function proceedAt(centralURL, token, render) {
      const email = tokenEmail(token);
      auth = { centralURL, token, email };
      if (intent === 'replace') {
        // Read the account with getAccount (not loginExisting — that would create a
        // default profile) so we can erase it cleanly.
        const acct = await getAccount(centralURL, token);
        backedUp = false; ackReplace = false; nsecVal = '';
        Object.keys(eraseRequested).forEach(k => delete eraseRequested[k]);
        if (!acct) { account = null; mode = 'import'; step = 'new-account'; render(); return; }
        account = acct; mode = 'import';
        step = 'replace-confirm'; render(); return;
      }
      // Sign-in: existing account → connect straight through (one screen); no account
      // → set one up. "Use a different key" lives on the Connected screen afterwards.
      const existing = await loginExisting(centralURL, token);
      if (existing) { await connectBunker(existing.bunkerURI, render); return; }
      mode = 'generate'; nsecVal = ''; step = 'new-account'; render();
    }

    async function start(render) {
      step = 'connecting'; errMsg = ''; render();
      try {
        const token = await authenticate(selectedCentral);   // popup opens inside the click
        const email = tokenEmail(token);
        // Cross-client discovery: is this account set up at a DIFFERENT central?
        // Skipped when pinned (the default). If so, do NOT auto-open a second popup —
        // the click's transient activation is already spent and Chrome blocks it;
        // show an interstitial whose button re-auths inside a fresh user gesture.
        const found = pinCentral ? null : await discover(email, relays);
        if (found && found.centralURL !== selectedCentral) {
          foundCtx = { token, email, foundCentral: found.centralURL };
          step = 'found-elsewhere'; render(); return;
        }
        await proceedAt(selectedCentral, token, render);
      } catch (e) {
        errMsg = e.message || 'Google sign-in failed.';
        step = 'idle'; render();
      }
    }

    // Interstitial: "Continue there" (sign-in) / "Replace the key there" (replace).
    // Re-auth at the discovered central inside this fresh click, then route.
    async function continueThere(render) {
      step = 'connecting'; errMsg = ''; render();
      try {
        const token = await authenticate(foundCtx.foundCentral);
        await proceedAt(foundCtx.foundCentral, token, render);
      } catch (e) {
        errMsg = e.message || 'Google sign-in failed.';
        step = 'found-elsewhere'; render();
      }
    }

    // Interstitial (replace only): ignore the discovered central and import at the
    // CONFIGURED one. signup() then publishes a fresh announcement that outranks the
    // old pointer, so future discovery resolves here. Reuses start()'s token.
    async function importHere(render) {
      step = 'connecting'; errMsg = ''; render();
      try {
        await proceedAt(selectedCentral, foundCtx.token, render);
      } catch (e) {
        errMsg = e.message || 'Sign-in failed.';
        step = 'found-elsewhere'; render();
      }
    }

    async function eraseAt(operatorURL, render) {
      errMsg = ''; render();
      try {
        await erasePopup(operatorURL);
        eraseRequested[operatorURL] = true;   // "requested" — confirm vs cancel is indistinguishable here
        render();
      } catch (e) { errMsg = e.message || 'Could not open the erase window.'; render(); }
    }

    // Format a signup failure as "host: status body" (host + trimmed server text).
    function signupErr(e) {
      if (!e) return 'Could not create your account.';
      if (e.floor) return e.message;
      const host = e.operator ? String(e.operator).replace(/^https?:\/\//, '')
        : (e.status ? selectedCentral.replace(/^https?:\/\//, '') : '');
      const parts = [];
      if (host) parts.push(host + ':');
      if (e.status) parts.push(String(e.status));
      const body = (e.body || '').trim().replace(/\s+/g, ' ').slice(0, 120);
      if (body) parts.push(body);
      return parts.length ? parts.join(' ') : (e.message || 'Could not create your account.');
    }

    // Wrap signup() so a single flaky/broken operator at registration time doesn't
    // fail the whole thing: probe first, drop unreachable ones, and on a 5xx/network
    // failure clear the pending registration and re-deal the SAME key across the
    // rest — down to `minOperators`. 4xx (client/protocol) and central failures are
    // never skipped. Sets `skipped`/`signupMeta`; throws on the floor or an
    // unskippable error. `sk` keeps its pubkey across re-deals, so operators that
    // already stored a shard accept the same key again.
    async function signupResilient({ centralURL, token, email, secretKey, render }) {
      let remaining = chosenOperators();
      let attemptToken = token, reauthed = false;
      const skips = [];
      // Pre-flight: drop operators that don't answer a health probe.
      const probes = await Promise.all(remaining.map(async op => [op, await probeServer(op)]));
      probes.forEach(([op, st]) => { probeStatus[op] = st; logLine(`probe ${hostOf(op)}: ${st === 'up' ? 'responding' : 'not responding'}`); });
      remaining = remaining.filter(op => {
        if (probeStatus[op] === 'down') { skips.push({ url: op, reason: 'not responding' }); logLine(`left out ${hostOf(op)} (not responding)`); return false; }
        return true;
      });
      skipped = skips.slice();
      for (;;) {
        if (remaining.length < minOperators) {
          const e = new Error(`Not enough operators are reachable (need at least ${minOperators}). Try again later or adjust Advanced.`);
          e.floor = true; logLine(`stopped: only ${remaining.length} operator(s) reachable, need ${minOperators}`); throw e;
        }
        const t = thresholdFor(remaining.length);
        statusMsg = skips.length ? `Registering across ${remaining.length} operators…` : 'Registering your new key…'; render();
        logLine(`register at ${hostOf(centralURL)} across ${remaining.length} operators (${t} needed)`);
        try {
          const res = await signup({ centralURL, token: attemptToken, email, operators: remaining, threshold: t, secretKey, relays });
          signupMeta = { central: centralURL, operators: remaining.slice(), threshold: t, skipped: skips.slice() };
          skipped = skips.slice();
          logLine(`account online: ${t}-of-${remaining.length} across ${remaining.map(hostOf).join(', ')}`);
          return res;
        } catch (e) {
          logLine(signupErr(e));
          if (e.status === 401 && !reauthed) { reauthed = true; logLine('token expired — re-authenticating'); attemptToken = await authenticate(centralURL); continue; }
          // Any post-start failure may have left a pending/partial registration —
          // clear it before retrying or bailing (idempotent; no-op with no account).
          try { await deleteAccount(centralURL, attemptToken); logLine('cleared pending registration'); } catch {}
          if (e.operator && !isShardConflict(e) && !(e.status >= 400 && e.status < 500)) {
            // Operator down / 5xx: drop it and re-deal across the rest.
            const reason = e.status ? `server error ${e.status}` : 'unreachable';
            skips.push({ url: e.operator, reason });
            remaining = remaining.filter(op => massageURL(op) !== massageURL(e.operator));
            skipped = skips.slice();
            logLine(`left out ${hostOf(e.operator)} (${reason}) — re-dealing across ${remaining.length}`);
            continue;
          }
          throw e;   // 4xx / shard-conflict / central failure → caller decides
        }
      }
    }

    async function doReplace(render) {
      step = 'replacing'; statusMsg = 'Erasing the old account…'; errMsg = ''; skipped = []; flowLog.length = 0; logOpen = false; render();
      try {
        const secretKey = mode === 'import' ? hexToBytes$2(nsecToHex(nsecVal.trim())) : undefined;
        // Delete first (also clears any pending registration), re-auth once on 401.
        try {
          await deleteAccount(auth.centralURL, auth.token); logLine(`deleted old account at ${hostOf(auth.centralURL)}`);
        } catch (e) {
          if (e.status === 401) { auth.token = await authenticate(auth.centralURL); await deleteAccount(auth.centralURL, auth.token); }
          else throw e;
        }
        const res = await signupResilient({ centralURL: auth.centralURL, token: auth.token, email: auth.email, secretKey, render });
        window.__pomBunker = res.bunkerURI;
        if (mode === 'import') {
          // They brought the key — nothing to reveal. Straight into the signer.
          await connectBunker(res.bunkerURI, render);
        } else {
          createdNsec = res.nsec; nsecSaved = false;
          step = 'created'; render();   // reveal the fresh nsec once, then Continue
        }
      } catch (e) {
        if (isShardConflict(e) && e.operator) {
          // That operator's erase was cancelled — it still holds the old share.
          delete eraseRequested[massageURL(e.operator)];
          errMsg = `${hostOf(e.operator)} still holds your old share — erase it and continue.`;
        } else {
          try { await deleteAccount(auth.centralURL, auth.token); } catch {}   // so Retry never hits the 60s 409
          errMsg = e.floor ? e.message : 'Couldn’t replace your key — see Details.';
        }
        step = 'replace-erase'; render();
      }
    }

    async function doSignup(render) {
      step = 'creating'; statusMsg = 'Creating your account…'; errMsg = ''; skipped = []; flowLog.length = 0; logOpen = false; render();
      try {
        const secretKey = mode === 'import' ? hexToBytes$2(nsecToHex(nsecVal.trim())) : undefined;
        const res = await signupResilient({ centralURL: auth.centralURL, token: auth.token, email: auth.email, secretKey, render });
        createdNsec = res.nsec; nsecSaved = false;
        window.__pomBunker = res.bunkerURI;
        step = 'created'; render();
      } catch (e) {
        try { await deleteAccount(auth.centralURL, auth.token); } catch {}   // so Retry never hits the 60s 409
        errMsg = e.floor ? e.message : 'Couldn’t finish setting up your account — see Details.';
        step = 'new-account'; render();
      }
    }

    async function addShard(operatorURL, render) {
      errMsg = ''; render();
      try {
        shards[operatorURL] = await requestOperatorShard(operatorURL);
        if (Object.keys(shards).length >= defaultThreshold) {
          recovered = reconstructFromShards(Object.values(shards));
          step = 'recovered'; render();
        } else { render(); }
      } catch (e) { errMsg = e.message || 'Could not recover that shard.'; render(); }
    }

    function render() {
      container.innerHTML = '';

      if (step === 'unconfigured') {
        const { wrap, body, footer } = flowWrap({ step: 0, total: 1, title: 'Google Sign-In Unavailable', subtitle: 'This app has not finished setting up Google sign-in.', onBack });
        body.appendChild(badge('warning', '🔧', 'Not configured', 'The developer needs to configure a pomegranate central server and operators to enable “Continue with Google”. Use another method for now.'));
        footer.appendChild(btn('Back', 'primary', onBack));
        container.appendChild(wrap);

      } else if (step === 'idle') {
        const { wrap, body, footer } = flowWrap({ step: 0, total: 3, title: 'Continue with Google', subtitle: 'Sign in with Google — your key is split across independent servers and never held whole.', onBack });
        if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));

        // Advanced disclosure — how-it-works, servers, and recovery. Collapsed by
        // default so most users never see any of the machinery.
        body.appendChild(h('button', { class: 'mill-consent-manage', type: 'button', style: { marginTop: '6px', fontWeight: '600' },
          onClick: () => { advancedOpen = !advancedOpen; render(); if (advancedOpen) probeAdvanced(render); } }, `${advancedOpen ? '▾' : '▸'} Advanced`));
        if (advancedOpen) {
          const panel = h('div', { style: { display: 'flex', flexDirection: 'column', gap: '10px', padding: '10px', border: '1px solid var(--mill-border)', borderRadius: '10px', background: 'var(--mill-inset)' } });
          panel.appendChild(badge('info', '🔒', 'How this works', 'A Nostr key is created for you and split into encrypted shares across several operators (a threshold is needed to sign). Google only proves it’s you; the full key is never reassembled.'));
          if (allowCustomCentral) {
            panel.appendChild(h('div', { class: 'mill-label' }, 'Central server'));
            if (!customCentralMode) {
              const sel = h('select', { class: 'mill-input', style: { width: '100%', background: 'var(--mill-surface)', color: 'var(--mill-text)', border: '1px solid var(--mill-border)', borderRadius: '8px', padding: '8px 10px', fontSize: '13px' }, onChange: e => { if (e.target.value === '__custom__') { customCentralMode = true; } else { selectedCentral = e.target.value; } render(); } });
              centralChoices.forEach(c => { const o = h('option', { value: c, style: { background: 'var(--mill-surface)', color: 'var(--mill-text)' } }, c.replace(/^https?:\/\//, '') + (c === defaultCentral ? ' (default)' : '')); if (c === selectedCentral) o.selected = true; sel.appendChild(o); });
              sel.appendChild(h('option', { value: '__custom__', style: { background: 'var(--mill-surface)', color: 'var(--mill-text)' } }, 'Custom…'));
              panel.appendChild(sel);
            } else {
              const { wrap: cw, input: ci } = field('', 'https://central.example.com', '', () => {}, {}); cw.style.flex = '1';
              const useBtn = btn('Use', 'ghost small', () => { const v = ci.value.trim(); if (isValidServerURL(v)) { selectedCentral = massageURL(v); if (!centralChoices.includes(selectedCentral)) centralChoices.push(selectedCentral); customCentralMode = false; errMsg = ''; render(); } else { errMsg = 'Enter a valid https:// server URL'; render(); } });
              const cancelBtn = btn('Cancel', 'ghost small', () => { customCentralMode = false; render(); });
              panel.appendChild(h('div', { style: { display: 'flex', gap: '6px', alignItems: 'flex-start' } }, cw, useBtn, cancelBtn));
            }
          }
          if (allowCustomOperators) {
            panel.appendChild(h('div', { class: 'mill-label' }, 'Operators'));
            selectedOperators.forEach(o => {
              const st = probeStatus[o.url];
              const title = st === 'up' ? 'Responding' : st === 'down' ? 'Not responding' : 'Checking…';
              const dot = h('span', { title, style: { display: 'inline-block', width: '9px', height: '9px', borderRadius: '50%', flex: '0 0 auto', background: st === 'up' ? 'var(--mill-success)' : st === 'down' ? 'var(--mill-danger)' : 'var(--mill-border)' } });
              const cb = h('input', { type: 'checkbox' }); cb.checked = o.checked; cb.addEventListener('change', e => { o.checked = e.target.checked; render(); });
              panel.appendChild(h('label', { title, style: { display: 'flex', gap: '8px', alignItems: 'center', fontSize: '13px', cursor: 'pointer' } }, cb, dot, h('span', {}, o.url.replace(/^https?:\/\//, '') + (st === 'down' ? ' — not responding' : ''))));
            });
            panel.appendChild(h('div', { class: 'mill-hint', style: { display: 'flex', gap: '12px', alignItems: 'center' } },
              h('span', {}, '● responding'), h('span', { style: { color: 'var(--mill-danger)' } }, '● not responding')));
            const { wrap: aw, input: ai } = field('', 'https://po.example.com', '', () => {}, {}); aw.style.flex = '1';
            const addBtn = btn('Add', 'ghost small', () => { const v = ai.value.trim(); if (!isValidServerURL(v)) { errMsg = 'Enter a valid https:// operator URL'; render(); return; } const u = massageURL(v); if (!selectedOperators.find(x => x.url === u)) selectedOperators.push({ url: u, checked: true }); errMsg = ''; render(); probeAdvanced(render, [u]); });
            panel.appendChild(h('div', { style: { display: 'flex', gap: '6px', alignItems: 'flex-start' } }, aw, addBtn));
          }
          const n = chosenOperators().length;
          panel.appendChild(h('div', { class: 'mill-hint' }, n >= minOperators ? `Any ${effThreshold()} of the ${n} selected operators can sign.` : `Select at least ${minOperators} operators.`));
          panel.appendChild(h('div', { class: 'mill-hint' }, 'Applies to new accounts — existing accounts keep their recorded operators.'));
          // Recovery lives here — a rare, advanced action.
          panel.appendChild(h('button', { class: 'mill-consent-manage', type: 'button', style: { marginTop: '2px' },
            onClick: () => { returnTo = ''; step = 'recover'; errMsg = ''; render(); } }, 'Recover my key from operators'));
          if (!isDefaultSelection()) panel.appendChild(h('button', { class: 'mill-consent-manage', type: 'button', onClick: () => { resetServers(); errMsg = ''; render(); } }, 'Reset to defaults'));
          body.appendChild(panel);
        }
        // Status line — only when the selection is customised, to keep defaults clean.
        if (!isDefaultSelection()) {
          const n = chosenOperators().length;
          body.appendChild(h('div', { class: 'mill-hint', style: { marginTop: '2px' } }, `Signing in at ${selectedCentral.replace(/^https?:\/\//, '')} · ${n} operators, ${effThreshold()} needed`));
        }
        renderLog(body);   // shows only after a failed attempt left entries
        const blockPrimary = customCentralMode || chosenOperators().length < minOperators;
        footer.appendChild(btn('Back', 'ghost', onBack));
        footer.appendChild(btn([googleLogoOnWhite(18), 'Continue with Google'], 'primary', () => { intent = 'signin'; start(render); }, blockPrimary));
        container.appendChild(wrap);

      } else if (step === 'found-elsewhere') {
        const foundHost = foundCtx.foundCentral.replace(/^https?:\/\//, '');
        const cfgHost = selectedCentral.replace(/^https?:\/\//, '');
        const isReplace = intent === 'replace';
        const { wrap, body, footer } = flowWrap({ step: 0, total: isReplace ? 4 : 3, title: 'Account Found Elsewhere', subtitle: `This Google account already has a Nostr identity at ${foundHost}.`, onBack: () => { step = 'idle'; render(); } });
        if (isReplace) {
          body.appendChild(badge('info', '🔀', 'Where should your key live?', `Your account is at ${foundHost}. You can replace the key there, or import it here at ${cfgHost} — which creates the account here and makes this the identity other clients discover from now on.`));
          body.appendChild(btn(`Replace the key at ${foundHost}`, 'ghost', () => continueThere(render)));
          if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
          footer.appendChild(btn('Cancel', 'ghost', () => { step = 'idle'; render(); }));
          footer.appendChild(btn(`Import here (${cfgHost})`, 'primary', () => importHere(render)));
        } else {
          body.appendChild(badge('info', '🔎', 'Use your existing account', `Sign in at ${foundHost} to use the identity you already have there. Signing in creates nothing new.`));
          if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
          footer.appendChild(btn('Cancel', 'ghost', () => { step = 'idle'; render(); }));
          footer.appendChild(btn([googleLogoOnWhite(18), 'Continue there'], 'primary', () => continueThere(render)));
        }
        container.appendChild(wrap);

      } else if (step === 'new-account') {
        const importing = mode === 'import';
        const keyOk = () => !importing || isValidNsec(nsecVal.trim());
        const goBtn = btn(importing ? 'Import & Shard' : 'Create Account', 'primary', () => { if (keyOk()) doSignup(render); }, !keyOk());
        const { wrap, body, footer } = flowWrap({ step: 1, total: 3, title: 'Set Up Your Account', subtitle: 'Create a fresh key, or bring your own to shard across the operators.', onBack: () => { step = 'idle'; render(); } });
        keyChooser(body, () => { goBtn.disabled = !keyOk(); });
        if (importing) {
          body.appendChild(badge('warning', '⚠️', 'Sharding an existing identity', 'Your key will be split into shares and sent to the operators. They become semi-custodians of THIS identity — any threshold of them could rebuild it. Only do this with operators you trust, and keep your own backup of the key.'));
        } else {
          body.appendChild(badge('info', '🎲', 'Fresh key', 'A brand-new key is generated in your browser, sharded, and distributed. You never have to write anything down (though you can back it up on the next screen).'));
        }
        if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
        renderLog(body);
        footer.appendChild(btn('Back', 'ghost', () => { step = 'idle'; render(); }));
        footer.appendChild(goBtn);
        container.appendChild(wrap);

      } else if (step === 'replace-confirm') {
        const importing = mode === 'import';
        const targets = eraseTargets();
        const isSameKey = () => {
          if (!importing || !isValidNsec(nsecVal.trim())) return false;
          try { return getPublicKey$1(hexToBytes$2(nsecToHex(nsecVal.trim()))) === account.pubkey; } catch { return false; }
        };
        const keyValid = () => !importing || (isValidNsec(nsecVal.trim()) && !isSameKey());
        const canReplace = () => ackReplace && keyValid();
        const sameKeyErr = h('div', { class: 'mill-error', hidden: true }, "That's already the key on this account.");
        const replaceBtn = btn('Replace key', 'danger', () => { if (canReplace()) { errMsg = ''; step = 'replace-erase'; render(); } }, !canReplace());
        const sync = () => { sameKeyErr.hidden = !isSameKey(); replaceBtn.disabled = !canReplace(); };

        const { wrap, body, footer } = flowWrap({ step: 0, total: 4, title: 'Replace Your Key', subtitle: 'Put a different Nostr identity behind this Google account.', onBack: () => { step = 'idle'; render(); } });
        body.appendChild(keyDisplay(`Current identity — sharded across ${targets.length} operators, ${account.threshold} needed`, hexToNpub(account.pubkey)));
        body.appendChild(badge('danger', '⚠️', 'This permanently replaces your current identity', `This replaces the identity tied to ${auth.email}. Your posts, follows and messages belong to the current key; after replacing, nothing can sign as it again unless you keep a backup. If this identity matters to you, back it up first.`));
        body.appendChild(btn('Back up current key first', 'ghost', () => { returnTo = 'replace-confirm'; step = 'recover'; errMsg = ''; render(); }));
        if (backedUp) body.appendChild(h('div', { class: 'mill-hint' }, '✅ Backed up'));
        keyChooser(body, sync);
        if (importing) body.appendChild(sameKeyErr);
        body.appendChild(checkboxRow('I understand my current key will be erased from the operators and this cannot be undone.', ackReplace, v => { ackReplace = v; sync(); }));
        if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
        footer.appendChild(btn('Back', 'ghost', () => { step = 'idle'; render(); }));
        footer.appendChild(replaceBtn);
        container.appendChild(wrap);

      } else if (step === 'replace-erase') {
        const targets = eraseTargets();
        const allRequested = targets.length > 0 && targets.every(op => eraseRequested[op]);
        const { wrap, body, footer } = flowWrap({ step: 1, total: 4, title: 'Erase Old Shares', subtitle: 'Sign in with Google at each operator and confirm the erase.', onBack: () => { step = 'replace-confirm'; render(); } });
        body.appendChild(h('div', { class: 'mill-hint' }, 'Do all of them — stopping halfway leaves your old key unrecoverable with no new key in place.'));
        targets.forEach(op => {
          const have = !!eraseRequested[op];
          const row = h('div', { class: 'mill-grant-row' });
          row.appendChild(h('div', { class: 'mill-grant-left' }, h('div', { class: 'mill-grant-kind' }, `${have ? '✅' : '⏳'} ${op.replace(/^https?:\/\//, '')}`)));
          row.appendChild(h('div', { class: 'mill-grant-actions' },
            btn(have ? 'Requested' : 'Erase', 'ghost', () => { if (!have) eraseAt(op, render); })));
          body.appendChild(row);
        });
        if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
        renderLog(body);
        footer.appendChild(btn('Back', 'ghost', () => { step = 'replace-confirm'; render(); }));
        footer.appendChild(btn('Continue', 'primary', () => doReplace(render), !allRequested));
        container.appendChild(wrap);

      } else if (step === 'connecting' || step === 'creating' || step === 'connecting-signer' || step === 'replacing') {
        const isReplace = step === 'replacing';
        const isSigner = step === 'connecting-signer';
        const title = step === 'creating' ? 'Creating your account…' : isReplace ? 'Replacing your key…' : 'Connecting…';
        const sub = step === 'creating' ? 'Splitting and distributing your key…'
          : isReplace ? (statusMsg || 'Working…')
          : isSigner ? 'Connecting to your signer…'
          : 'Approve access in the Google window.';
        const { wrap, body, footer } = flowWrap({ step: isReplace ? 2 : 1, total: isReplace ? 4 : 3, title, subtitle: sub });
        const center = h('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '14px', padding: '30px 0' } });
        center.appendChild(spinner()); center.appendChild(h('span', { style: { color: 'var(--mill-text-secondary)' } }, statusMsg || 'One moment…'));
        body.appendChild(center);
        if (isSigner) { renderLog(body); footer.appendChild(btn('Cancel', 'ghost', () => cancelConnect(render))); }
        container.appendChild(wrap);

      } else if (step === 'created') {
        const { wrap, body, footer } = flowWrap({ step: 2, total: 3, title: 'Account Created', subtitle: 'Your account is ready. Optionally save your key before continuing — it is otherwise held only as shares across the operators.', onBack: () => { step = 'idle'; render(); } });
        body.appendChild(badge('success', '🎉', 'You’re set up', signupMeta ? `Your key is sharded across ${signupMeta.operators.length} operators; any ${signupMeta.threshold} can sign. You can sign in from any compatible app with this Google account.` : 'You can sign in from any compatible app with this Google account.'));
        if (skipped.length) body.appendChild(h('div', { class: 'mill-hint' }, `${skipped.length} operator${skipped.length > 1 ? 's were' : ' was'} left out (see Details).`));
        body.appendChild(keyDisplay('Private Key (nsec) — optional backup, keep secret', createdNsec, true));
        body.appendChild(badge('muted', '💾', null, 'Saving this is optional — you can recover later with Google as long as the operators are online. But keeping your own copy means you never depend on them.'));
        renderLog(body);
        footer.appendChild(btn('Continue', 'primary', () => connectBunker(window.__pomBunker, render)));
        container.appendChild(wrap);

      } else if (step === 'recover') {
        const { wrap, body, footer } = flowWrap({ step: 0, total: 2, title: 'Recover Your Key', subtitle: 'Sign in with Google at each operator to collect your key shares. Once enough are collected, your key is reassembled here, in your browser.', onBack: () => { step = returnTo || 'idle'; render(); } });
        body.appendChild(h('div', { class: 'mill-hint' }, `Collected ${Object.keys(shards).length} of ${defaultThreshold} needed.`));
        operatorChoices.forEach(op => {
          const have = !!shards[op];
          const row = h('div', { class: 'mill-grant-row' });
          row.appendChild(h('div', { class: 'mill-grant-left' }, h('div', { class: 'mill-grant-kind' }, `${have ? '✅' : '⏳'} ${op.replace(/^https?:\/\//, '')}`)));
          row.appendChild(h('div', { class: 'mill-grant-actions' },
            btn(have ? 'Got it' : 'Recover', 'ghost', () => { if (!have) addShard(op, render); })));
          body.appendChild(row);
        });
        if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
        footer.appendChild(btn('Back', 'ghost', () => { step = returnTo || 'idle'; render(); }));
        container.appendChild(wrap);

      } else if (step === 'recovered') {
        const { wrap, body, footer } = flowWrap({ step: 1, total: 2, title: 'Key Recovered', subtitle: 'Your key has been reassembled. Save it somewhere only you control.', onBack: () => { step = 'recover'; render(); } });
        body.appendChild(keyDisplay('Private Key (nsec) — KEEP SECRET', recovered.nsec, true));
        body.appendChild(keyDisplay('Public Key (npub)', recovered.npub));
        body.appendChild(badge('warning', '🔑', 'This is your full key', 'Store it in a password manager or offline. Anyone with it controls your account.'));
        footer.appendChild(btn('Done', 'primary', () => { if (returnTo) { backedUp = true; step = returnTo; returnTo = ''; } else { step = 'idle'; } render(); }));
        container.appendChild(wrap);
      }
    }
    render();
    // Entered from the Connected screen's "Use a different key": go straight into
    // the replace flow. Auth runs synchronously here (still inside the user's click),
    // so the Google popup isn't blocked.
    if (host._state?.pomegranateReplace) { host._state.pomegranateReplace = false; intent = 'replace'; start(render); }
    return container;
  }

  // ── Flow: New Keypair ─────────────────────────────────────────────────────────
  function renderNewKeypairFlow(host, onDone, onBack) {
    let step = 0, keys = null, checks = [false, false, false], pw = '', pw2 = '', generating = false;
    const perms = Object.fromEntries(SIGN_CATS.map(c => [c.id, c.def]));
    const container = h('div', {});

    function render() {
      container.innerHTML = '';
      if (step === 0) {
        const { wrap, body, footer } = flowWrap({ step: 0, total: 5, title: 'Generate New Identity', subtitle: "Create a fresh Nostr keypair using your browser's CSPRNG.", onBack });
        if (generating) {
          const center = h('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '14px', padding: '30px 0' } });
          center.appendChild(spinner()); center.appendChild(h('span', { style: { color: 'var(--mill-text-secondary)' } }, 'Generating secure random keys…'));
          body.appendChild(center);
        } else {
          body.appendChild(badge('info', '🎲', 'Cryptographically secure', 'Keys are generated using crypto.getRandomValues() — your browser\'s CSPRNG. Nothing is transmitted to any server.'));
          body.appendChild(badge('warning', '⚠️', 'Backup your key before using this identity', 'There is no account recovery or password reset. If you lose your nsec, you lose the identity — forever.'));
        }
        footer.appendChild(btn('Cancel', 'ghost', onBack));
        footer.appendChild(btn(generating ? 'Generating…' : 'Generate Keys', 'primary', async () => {
          generating = true; render();
          keys = await generateKeypair();
          generating = false; step = 1; render();
        }, generating));
        container.appendChild(wrap);
      } else if (step === 1 && keys) {
        const { wrap, body, footer } = flowWrap({ step: 1, total: 5, title: 'Save Your Keys', subtitle: 'Copy your private key now — this is the only time it will be shown in full.', onBack: () => { step = 0; render(); } });
        body.appendChild(badge('danger', '🔴', 'Never share your nsec', 'Anyone who sees your nsec can impersonate you, read your DMs, and permanently take over your account.'));
        body.appendChild(keyDisplay('Private Key (nsec) — KEEP SECRET', keys.nsec, true));
        body.appendChild(keyDisplay('Public Key (npub) — safe to share', keys.npub));
        body.appendChild(badge('muted', '💾', null, 'Save to a password manager, encrypted note, or paper stored offline. Never in a plain cloud note or screenshot.'));
        footer.appendChild(btn('Back', 'ghost', () => { step = 0; render(); }));
        footer.appendChild(btn("I've Saved My Keys", 'primary', () => { step = 2; render(); }));
        container.appendChild(wrap);
      } else if (step === 2) {
        const { wrap, body, footer } = flowWrap({ step: 2, total: 5, title: 'Confirm Backup', subtitle: "Check each box to confirm you've secured your key.", onBack: () => { step = 1; render(); } });
        const items = [
          'I have copied my nsec private key to a secure, private location.',
          'I understand that losing my nsec means permanently losing this identity with no recovery.',
          'I will never share my nsec or paste it into a site I do not fully trust.',
        ];
        items.forEach((text, i) => {
          const row = h('div', { class: `mill-check-item${checks[i] ? ' checked' : ''}`, onClick: () => { checks[i] = !checks[i]; render(); } });
          const box = h('div', { class: 'mill-check-box' }, checks[i] ? '✓' : '');
          row.appendChild(box);
          row.appendChild(h('span', { style: { fontSize: '13.5px', lineHeight: '1.55', color: 'var(--mill-text-secondary)' } }, text));
          body.appendChild(row);
        });
        footer.appendChild(btn('Back', 'ghost', () => { step = 1; render(); }));
        footer.appendChild(btn('Continue', 'primary', () => { step = 3; render(); }, !checks.every(Boolean)));
        container.appendChild(wrap);
      } else if (step === 3) {
        const { wrap, body, footer } = flowWrap({ step: 3, total: 5, title: 'Encrypt & Signing Settings', subtitle: 'Set a session password to protect your key in this browser, and choose what gets signed automatically.', onBack: () => { step = 2; render(); } });
        const isOk = () => pw.length >= 4 && pw === pw2;
        const contBtn = btn('Continue', 'primary', () => { if (isOk()) { step = 4; render(); } }, !isOk());
        const err1 = h('div', { class: 'mill-error' });
        const err2 = h('div', { class: 'mill-error' });
        const updateUi = () => {
          contBtn.disabled = !isOk();
          err1.textContent = pw && pw.length < 4 ? 'Minimum 4 characters' : '';
          err2.textContent = pw2 && pw !== pw2 ? 'Passwords do not match' : '';
        };
        const { wrap: pw1 } = field('Session Password', 'Minimum 4 characters', pw, v => { pw = v; updateUi(); }, { type: 'password' });
        const { wrap: pw2w } = field('Confirm Password', 'Repeat password', pw2, v => { pw2 = v; updateUi(); }, { type: 'password' });
        pw1.appendChild(err1); pw2w.appendChild(err2);
        body.appendChild(pw1); body.appendChild(pw2w);
        body.appendChild(h('div', { class: 'mill-divider' }));
        body.appendChild(signingBehaviorEditor(perms));
        footer.appendChild(btn('Back', 'ghost', () => { step = 2; render(); }));
        footer.appendChild(contBtn);
        container.appendChild(wrap);
      } else {
        const { wrap, body, footer } = flowWrap({ step: 4, total: 5, title: 'Welcome to Nostr', subtitle: 'Your new identity is ready.', onBack: () => { step = 3; render(); } });
        body.appendChild(keyDisplay('Your Public Key (npub)', keys?.npub || ''));
        body.appendChild(badge('success', '🎉', 'Identity created!', 'Your Nostr identity is ready. Share your npub so others can find and follow you. Your profile, follows, and notes are yours — no platform can take them away.'));
        footer.appendChild(btn('Back', 'ghost', () => { step = 3; render(); }));
        footer.appendChild(btn('Enter Nostr ✨', 'success', async () => {
          const encrypted = await encryptNsec(keys.privHex, pw);
          storeEncryptedNsec(encrypted);
          storeSignPerms(perms);   // so MILL.restore() can rebuild with the same policy after reload
          const signer = createPrivateKeySigner({
            pubkey: keys.pubHex, perms,
            promptPassword: sessionPrompt(host, pw),
            requestConsent: req => host.requestConsent({ ...req, npub: keys.npub }),
          });
          onDone({ method: 'newkey', pubkey: keys.pubHex, nsec: keys.nsec, perms, signer });
        }));
        container.appendChild(wrap);
      }
    }
    render();
    return container;
  }

  // ── Connected screen ──────────────────────────────────────────────────────────
  function renderConnectedScreen(result, onDisconnect, opts = {}) {
    const m = METHOD_META[result.method] || {};
    const wrap = h('div', { class: 'mill-connected' });
    const avatar = h('div', { class: 'mill-connected-avatar' }, iconNode(m.icon, 40));
    avatar.style.background = `radial-gradient(circle, ${m.color}30, transparent)`;
    avatar.style.borderColor = m.color;
    wrap.appendChild(avatar);
    wrap.appendChild(h('div', { style: { textAlign: 'center' } },
      h('div', { style: { fontSize: '22px', fontWeight: '700', marginBottom: '4px' } }, 'Connected'),
      h('div', { style: { fontSize: '14px', color: 'var(--mill-text-secondary)' } }, `Signed in via ${m.label}`)
    ));
    if (result.pubkey) {
      wrap.appendChild(h('div', { style: { width: '100%', background: 'var(--mill-inset)', border: '1px solid var(--mill-border)', borderRadius: '10px', padding: '10px 14px' } },
        h('div', { style: { fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.1em', color: 'var(--mill-muted)', marginBottom: '6px' } }, 'Public Key'),
        h('code', { style: { fontSize: '12px', fontFamily: 'var(--mill-font-mono)', color: 'var(--mill-accent)', wordBreak: 'break-all', lineHeight: '1.6' } }, result.pubkey)
      ));
    }
    // Primary "I'm done" action — the obvious path for a first-time user. Just
    // dismisses the modal (same as ✕); onConnected has already fired.
    if (opts.onContinue) {
      wrap.appendChild(btn(opts.continueLabel || 'Continue to app', 'primary', opts.onContinue));
    }
    // "Take control" — only when mill actually holds the key (private-key-backed
    // methods). For NIP-07/46/55 the key lives elsewhere and there's nothing to
    // reveal. Hidden behind a quiet link, per the decision that normies should
    // never have to think about keys until they choose to.
    if (opts.onShowKeys && loadEncryptedNsec()) {
      wrap.appendChild(btn('Take control of my keys', 'ghost small', opts.onShowKeys));
    }
    // Pomegranate: swap the key behind this Google account (email is known now, so
    // this goes straight into the replace flow — no pre-login popup just to learn it).
    if (result.method === 'pomegranate' && opts.onReplaceKey) {
      wrap.appendChild(btn('Use a different key', 'ghost small', opts.onReplaceKey));
    }
    wrap.appendChild(btn('Disconnect & Switch Account', 'ghost small', onDisconnect));
    return wrap;
  }

  // ── Flow: key export ("take control of my keys") ──────────────────────────────
  // Reveals the private key mill has been holding, and exports a portable NIP-49
  // ncryptsec any other Nostr client can import. Requires re-entering the session
  // password / PIN first — seeing the key is exactly when re-authentication is
  // warranted, and it means a shoulder-surfer on an unlocked tab still can't.
  function renderKeyExport(host, result, onBack) {
    let step = 'auth', pw = '', privHex = '', errMsg = '';
    let pass = '', ncryptsec = '', exporting = false;
    const container = h('div', {});
    const isCloud = result?.method === 'google';

    function render() {
      container.innerHTML = '';

      if (step === 'auth') {
        const label = isCloud ? 'PIN' : 'Session password';
        const { wrap, body, footer } = flowWrap({ step: 0, total: 2, title: 'Take Control of Your Keys', subtitle: `Enter your ${label.toLowerCase()} to reveal your private key.`, onBack });
        body.appendChild(badge('warning', '🔑', 'Your private key is about to be shown', 'Anyone who sees it gains full control of your account. Make sure no one is watching your screen, and only save it somewhere private.'));
        const f = field(label, isCloud ? '4–8 letters or numbers' : 'Your password', pw, v => { pw = v; errMsg = ''; },
          { type: 'password', error: errMsg, maxlength: isCloud ? 8 : undefined });
        body.appendChild(f.wrap);
        const submit = async () => {
          try {
            const enc = loadEncryptedNsec();
            privHex = await decryptNsec(enc, pw);
            step = 'reveal'; render();
          } catch { errMsg = isCloud ? 'Wrong PIN' : 'Wrong password'; render(); }
        };
        if (f.input) f.input.addEventListener('keydown', e => { if (e.key === 'Enter' && pw) submit(); });
        footer.appendChild(btn('Cancel', 'ghost', onBack));
        footer.appendChild(btn('Reveal', 'primary', submit));
        container.appendChild(wrap);

      } else {
        const nsec = hexToNsec(privHex);
        const { wrap, body, footer } = flowWrap({ step: 1, total: 2, title: 'Your Keys', subtitle: 'This is your account. Save it somewhere only you control.', onBack: () => { step = 'auth'; pw = ''; privHex = ''; render(); } });
        body.appendChild(keyDisplay('Private Key (nsec) — KEEP SECRET', nsec, true));
        if (result?.pubkey) body.appendChild(keyDisplay('Public Key (npub) — safe to share', hexToNpub(result.pubkey)));

        if (isCloud) {
          body.appendChild(badge('info', '☁️', 'Your cloud backup still exists', 'A copy of this key is still encrypted in your Google Drive so you can keep signing in with Google. Saving your nsec here is an additional, portable copy — it does not remove the cloud one.'));
        }

        body.appendChild(h('div', { class: 'mill-divider' }));

        // Portable export: NIP-49 ncryptsec, importable by any Nostr client.
        body.appendChild(h('div', { style: { fontSize: '13px', fontWeight: '600', marginBottom: '2px' } }, 'Export an encrypted backup'));
        body.appendChild(h('div', { class: 'mill-hint' }, 'Protect your key with a passphrase (min 8 characters). The result is a standard ncryptsec you can import into any Nostr app.'));
        const pf = field('Backup passphrase', 'At least 8 characters', pass, v => { pass = v; }, { type: 'password' });
        body.appendChild(pf.wrap);
        if (ncryptsec) body.appendChild(keyDisplay('Encrypted Key (ncryptsec)', ncryptsec, true));
        if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));

        footer.appendChild(btn('Done', 'ghost', onBack));
        footer.appendChild(btn(exporting ? 'Encrypting…' : 'Export ncryptsec', 'primary', async () => {
          errMsg = '';
          if (pass.length < 8) { errMsg = 'Use at least 8 characters'; render(); return; }
          exporting = true; render();
          try { ncryptsec = exportNcryptsec(privHex, pass); }
          catch (e) { errMsg = e.message || 'Export failed'; }
          exporting = false; render();
        }, exporting));
        container.appendChild(wrap);
      }
    }
    render();
    return container;
  }

  // ── Flow: Unlock (standalone password prompt for restore) ─────────────────────
  // Shown by MILL.restore() when a private-key signer needs the session password
  // after a reload — the full picker stays closed; only the password is asked.
  // ── Flow: signing consent ─────────────────────────────────────────────────────
  // Shown per signature when neither a per-kind grant nor the category policy
  // has already authorised it. Deliberately minimal by default — Amber shows one
  // sentence and hides the payload behind "Show Details"; dumping raw JSON at
  // someone every time trains them to click through without reading.
  function renderConsentFlow(host, req, onDecide) {
    const { event, label, category } = req;
    let showDetails = false;
    let duration = 'once';                 // safe default: remember nothing
    const container = h('div', {});

    const appName = host.getAttribute?.('app-name') || document.title || 'This app';
    const npub    = req.npub || '';

    function render() {
      container.innerHTML = '';
      const { wrap, body, footer } = flowWrap({
        step: 0, total: 1,
        title: 'Approve Signing',
        subtitle: 'Review this request before it is signed with your private key.',
      });

      const head = h('div', { class: 'mill-consent-head' });
      head.appendChild(h('div', { class: 'mill-consent-icon' }, '✍️'));
      const ask = h('div', { class: 'mill-consent-ask' });
      ask.appendChild(h('div', {},
        h('span', {}, `${appName} wants you to sign ${kindArticle(label)} `),
        h('span', { class: 'mill-consent-kind' }, label),
      ));
      if (npub) ask.appendChild(h('div', { class: 'mill-consent-as' }, `Signing as ${npub}`));
      head.appendChild(ask);
      body.appendChild(head);

      const toggle = h('button', { class: 'mill-consent-toggle', type: 'button',
        onClick: () => { showDetails = !showDetails; render(); } },
        h('span', {}, showDetails ? '▾' : '▸'),
        h('span', {}, showDetails ? 'Hide details' : 'Show details'),
      );
      body.appendChild(toggle);

      if (showDetails) {
        const d = h('div', { class: 'mill-consent-details' });
        const field = (k, v) => {
          if (v === null || v === undefined || v === '') return;
          d.appendChild(h('div', { class: 'mill-consent-field' },
            h('div', { class: 'mill-consent-field-k' }, k),
            h('div', { class: 'mill-consent-field-v' }, String(v)),
          ));
        };
        const nip = kindNip(event?.kind);
        field('Kind', nip ? `${event?.kind} — ${label} (${nip})` : `${event?.kind} — ${label}`);
        if (event?.created_at) {
          const ts = new Date(event.created_at * 1000);
          field('Date', isNaN(ts) ? String(event.created_at) : ts.toLocaleString());
        }
        // Content is shown decoded, not as escaped JSON — the point is that a
        // person can actually read what they're signing.
        field('Content', event?.content ?? '');
        const tags = Array.isArray(event?.tags) ? event.tags : [];
        if (tags.length) field('Tags', tags.map(t => Array.isArray(t) ? t.join(' · ') : String(t)).join('\n'));
        body.appendChild(d);
      }

      const remember = h('div', { class: 'mill-consent-remember' });
      remember.appendChild(h('div', { class: 'mill-consent-remember-label' }, `Remember for ${label}`));
      const durs = h('div', { class: 'mill-consent-durations' });
      DURATIONS.forEach(o => {
        durs.appendChild(h('button', {
          class: `mill-consent-dur${duration === o.id ? ' active' : ''}`,
          type: 'button',
          onClick: () => { duration = o.id; render(); },
        }, o.label));
      });
      remember.appendChild(durs);
      body.appendChild(remember);

      // Mill is only ever on screen when it's asking for something, so this is
      // the one reliable place to offer a way into its settings — no host-app
      // menu wiring required.
      body.appendChild(h('button', { class: 'mill-consent-manage', type: 'button',
        onClick: () => onDecide({ manage: true }) }, 'Manage permissions'));

      // The chosen duration applies to whichever button is pressed, so
      // "reject this kind for an hour" is expressible — same as Amber.
      footer.appendChild(btn('Reject', 'ghost', () => onDecide({ approved: false, duration })));
      footer.appendChild(btn('Approve & Sign', 'primary', () => onDecide({ approved: true, duration })));
      container.appendChild(wrap);
    }
    render();
    return container;
  }

  // ── Flow: permissions manager ─────────────────────────────────────────────────
  // Lists every live per-kind grant with the same two controls as the consent
  // card — what, and for how long — so the two screens read as one system.
  function renderPermissionsScreen(host, onBack) {
    const container = h('div', {});

    function render() {
      container.innerHTML = '';
      sweepExpiredGrants();
      const grants = listGrants();
      const { wrap, body, footer } = flowWrap({
        step: 0, total: 1,
        title: 'Signing Permissions',
        subtitle: 'Kinds you have already approved or blocked. Removing one means mill will ask again next time.',
        onBack,
      });

      if (!grants.length) {
        body.appendChild(badge('muted', '🗂', 'Nothing remembered yet',
          'When you approve a signing request and choose to remember it, it shows up here. Requests you approve "just this time" are never stored.'));
      } else {
        grants.forEach(g => {
          const row = h('div', { class: 'mill-grant-row' });
          const allowed = g.action !== 'deny';
          const when = g.dur === 'always' ? 'Always'
            : g.dur === 'session' ? 'This session'
            : `Until ${new Date(g.until).toLocaleTimeString()}`;
          row.appendChild(h('div', { class: 'mill-grant-left' },
            h('div', { class: 'mill-grant-kind' },
              `${allowed ? '✅' : '⛔'} ${kindLabel(g.kind)}`),
            h('div', { class: 'mill-grant-meta' }, `kind ${g.kind} · ${allowed ? 'allowed' : 'blocked'} · ${when}`),
          ));
          const actions = h('div', { class: 'mill-grant-actions' });
          const mk = (text, active, color, onClick) => {
            const b = h('button', { class: 'mill-grant-btn', type: 'button', onClick }, text);
            if (active) { b.style.borderColor = color; b.style.color = color; b.style.background = color + '1f'; }
            return b;
          };
          actions.appendChild(mk('Allow', allowed, 'var(--mill-success)',
            () => { saveGrant(g.kind, 'allow', g.dur || 'session'); render(); }));
          actions.appendChild(mk('Block', !allowed, 'var(--mill-danger)',
            () => { saveGrant(g.kind, 'deny', g.dur || 'session'); render(); }));
          actions.appendChild(mk('Ask', false, 'var(--mill-accent)',
            () => { revokeGrant(g.kind); render(); }));
          row.appendChild(actions);
          body.appendChild(row);
        });
        body.appendChild(h('div', { class: 'mill-hint' },
          'These apply only to private-key signing in this browser. NIP-07, NIP-46, and NIP-55 manage approvals in their own app.'));
      }

      if (grants.length) {
        footer.appendChild(btn('Forget all', 'ghost', () => { revokeAllGrants(); render(); }));
      }
      footer.appendChild(btn('Done', 'primary', onBack));
      container.appendChild(wrap);
    }
    render();
    return container;
  }

  // Password provider for a signer created during a fresh login.
  //
  // This is the UNLOCK gate only — consent is a separate gate handled by the
  // consent card. The user typed this password seconds ago to log in, so that
  // already is their once-per-session unlock; asking again here would stack a
  // password prompt on top of every approval, which is the friction the
  // two-gate split exists to remove.
  //
  // Authorisation is NOT weakened by this: an unauthorised kind never reaches
  // unlock(), because authorize() throws first.
  function sessionPrompt(_host, pw) {
    return () => Promise.resolve(pw);
  }

  function renderUnlockFlow(host, onSubmit, onCancel, opts = {}) {
    let pw = '', errMsg = '';
    const container = h('div', {});
    function render() {
      container.innerHTML = '';
      const { wrap, body, footer } = flowWrap({
        step: 0, total: 1,
        title: opts.title || 'Unlock Signing',
        subtitle: opts.subtitle || 'Enter your session password to unlock signing.',
      });
      body.appendChild(badge('info', '🔒', 'Session locked', 'Your key is encrypted and still stored for this tab. Enter the password you set at login to unlock it — once, for the rest of this session.'));
      const submit = () => {
        if (!pw) { errMsg = 'Password required'; render(); return; }
        onSubmit(pw);
      };
      const { wrap: pwWrap, input } = field('Session Password', 'Your login password', pw, v => { pw = v; errMsg = ''; }, { type: 'password', error: errMsg });
      if (input) input.addEventListener('keydown', e => { if (e.key === 'Enter') submit(); });
      body.appendChild(pwWrap);
      if (errMsg) body.appendChild(h('div', { class: 'mill-error' }, errMsg));
      footer.appendChild(btn('Cancel', 'ghost', () => onCancel?.()));
      footer.appendChild(btn('Unlock', 'primary', submit));
      container.appendChild(wrap);
    }
    render();
    return container;
  }

  // ── NostrSignerElement — the Web Component ────────────────────────────────────
  class NostrSignerElement extends HTMLElement {
    static get observedAttributes() { return ['theme', 'open']; }

    constructor() {
      super();
      this._shadow = this.attachShadow({ mode: 'open' });
      this._state = { open: false, method: null, connected: null, consent: null, settings: false };
      this._callbacks = { onConnected: null, onClose: null };
    }

    connectedCallback() {
      this._injectStyles();
      this._render();
    }

    disconnectedCallback() {
      try { this._state.connected?.signer?.disconnect?.(); } catch {}
    }

    attributeChangedCallback(name, _old, val) {
      if (name === 'theme') this._applyTheme(val);
      if (name === 'open')  { this._state.open = val !== null && val !== 'false'; this._render(); }
    }

    _injectStyles() {
      if (this._shadow.querySelector('style')) return;
      const style = document.createElement('style');
      style.textContent = BASE_CSS;
      this._shadow.appendChild(style);
    }

    _applyTheme(themeNameOrObj) {
      const host = this._shadow.host;
      const tokens = typeof themeNameOrObj === 'string'
        ? THEMES[themeNameOrObj] ?? THEMES.dark
        : { ...THEMES.dark, ...themeNameOrObj };
      for (const [prop, val] of Object.entries(tokens)) {
        // remap --mill- prefix to :host scope
        host.style.setProperty(prop, val);
      }
    }

    setTheme(theme) { this._applyTheme(theme); }

    open(opts = {}) {
      if (opts.onConnected) this._callbacks.onConnected = opts.onConnected;
      if (opts.onClose)     this._callbacks.onClose     = opts.onClose;
      // Always reset layout/methods/theme state per-open so callers don't inherit
      // values from previous opens. To clear a previous theme override, the caller
      // can pass theme: 'dark' explicitly.
      if (opts.theme)       this._applyTheme(opts.theme);
      this._state.methodFilter = opts.methods;            // undefined → defaults
      this._state.density      = opts.density;            // undefined → comfortable
      this._state.layout       = opts.layout;             // undefined → list
      this._state.callout      = 'callout' in opts ? opts.callout : undefined;  // undefined → 'newkey'
      this._state.relays       = Array.isArray(opts.relays) && opts.relays.length ? opts.relays : undefined;
      this._state.footer       = opts.footer;             // { text?, links?, attribution?, attributionHref? }
      this._state.header       = opts.header;             // { logo?, logoHeight?, title?, message?, align?, label? }
      this._state.tip          = 'tip' in opts ? opts.tip : undefined;   // string | false | undefined
      this._state.pomegranate  = opts.pomegranate;        // { central, operators[], threshold?, relays? }
      this._state.open      = true;
      this._state.method    = null;
      this._state.connected = null;
      this._state.unlock    = null;
      this._render();
    }

    /**
     * Open a minimal password-only prompt (no method picker) and resolve with the
     * entered password, or null if the user cancels. Used by MILL.restore() to
     * unlock a private-key signer after a reload.
     */
    promptPassword({ title, subtitle } = {}) {
      return new Promise(resolve => {
        this._state.unlock = { resolve, title, subtitle };
        this._state.open = true;
        this._state.method = null;
        this._state.connected = null;
        this._render();
      });
    }

    /**
     * Open the signing consent card and resolve with the user's decision:
     * { approved: boolean, duration: 'once'|'5m'|'1h'|'session'|'always' }.
     * Resolves { approved: false } if the modal is dismissed — a request the
     * user walked away from must never count as approval.
     */
    requestConsent(req) {
      return new Promise(resolve => {
        this._state.consent = { ...req, npub: req.npub || this._state.connected?.signer?.npub || '', resolve };
        this._state.open = true;
        this._state.method = null;
        this._render();
      });
    }

    /** Open the permissions manager. Optional for hosts; the consent card links here. */
    openSettings() {
      this._state.settings = true;
      this._state.open = true;
      this._state.method = null;
      this._render();
    }

    close() {
      // Resolve a pending unlock prompt as cancelled so callers don't hang.
      if (this._state.unlock) { const u = this._state.unlock; this._state.unlock = null; u.resolve(null); }
      // Dismissing a consent card is a refusal, never a silent approval, and it
      // must not be remembered — the user made no choice about future requests.
      if (this._state.consent) {
        const c = this._state.consent; this._state.consent = null;
        c.resolve({ approved: false, duration: 'once' });
      }
      this._state.settings = false;
      this._state.open = false;
      this._render();
      this._callbacks.onClose?.();
    }

    _render() {
      // Clear old modal if exists
      const old = this._shadow.querySelector('.mill-overlay');
      if (old) old.remove();
      if (!this._state.open) return;

      const overlay = h('div', { class: 'mill-overlay', onClick: e => { if (e.target === overlay) this.close(); } });
      const modal = h('div', { class: 'mill-modal' });

      // Header strip: dot + label + close. `header.label` overrides the label;
      // '' or false hides the dot+label (the close button always stays).
      const labelCfg = this._state.header?.label;
      const labelText = labelCfg === undefined ? 'Account Access' : labelCfg;
      const headerLeft = h('div', { style: { display: 'flex', alignItems: 'center' } });
      if (labelText) {
        headerLeft.appendChild(h('span', { class: 'mill-header-dot' }));
        headerLeft.appendChild(h('span', { class: 'mill-header-label' }, labelText));
      }
      const header = h('div', { class: 'mill-header' },
        headerLeft,
        h('button', { class: 'mill-close', onClick: () => this.close() }, '✕')
      );
      modal.appendChild(header);

      const body = h('div', { class: 'mill-body' });

      const onDone = result => {
        this._state.connected = result;
        this._state.open = true;
        this._dispatch('mill:connected', result);
        this._callbacks.onConnected?.(result);
        this._render();
      };

      const onBack = () => {
        this._state.method = null;
        this._render();
      };

      if (this._state.consent) {
        const decide = (decision) => {
          // "Manage permissions" keeps the request pending — the user is still
          // deciding, and settings changes should inform that decision.
          if (decision?.manage) { this._state.settings = true; this._render(); return; }
          const c = this._state.consent;
          this._state.consent = null;
          this._state.open = false;
          this._render();
          c?.resolve(decision);
        };
        if (this._state.settings) {
          body.appendChild(renderPermissionsScreen(this, () => { this._state.settings = false; this._render(); }));
        } else {
          body.appendChild(renderConsentFlow(this, this._state.consent, decide));
        }
      } else if (this._state.settings) {
        body.appendChild(renderPermissionsScreen(this, () => {
          this._state.settings = false;
          this._state.open = false;
          this._render();
        }));
      } else if (this._state.unlock) {
        const finish = (pw) => {
          const u = this._state.unlock;
          this._state.unlock = null;
          this._state.open = false;
          this._render();
          u?.resolve(pw);
        };
        body.appendChild(renderUnlockFlow(this,
          (pw) => finish(pw),
          () => finish(null),
          { title: this._state.unlock.title, subtitle: this._state.unlock.subtitle },
        ));
      } else if (this._state.connected && this._state.keyexport) {
        body.appendChild(renderKeyExport(this, this._state.connected, () => {
          this._state.keyexport = false; this._render();
        }));
      } else if (this._state.connected) {
        body.appendChild(renderConnectedScreen(this._state.connected, () => {
          try { this._state.connected?.signer?.disconnect?.(); } catch {}
          // Switching accounts: drop persisted restore state so a later
          // MILL.restore() can't rebuild the account we just left.
          clearStoredNsec(); clearSignPerms(); clearBunkerState();
          this._state.connected = null; this._state.method = null;
          this._dispatch('mill:disconnected', {});
          this._render();
        }, {
          onContinue: () => this.close(),
          onShowKeys: () => { this._state.keyexport = true; this._render(); },
          // Re-enter the pomegranate flow straight into "replace the key" (auth →
          // replace-confirm). The flow reconnects on completion.
          onReplaceKey: () => { this._state.connected = null; this._state.method = 'pomegranate'; this._state.pomegranateReplace = true; this._render(); },
        }));
      } else if (this._state.method) {
        const flowMap = {
          readonly:   () => renderReadOnlyFlow(this, onDone, onBack),
          privatekey: () => renderPrivateKeyFlow(this, onDone, onBack),
          nip07:      () => renderNIP07Flow(this, onDone, onBack),
          nip46:      () => renderNIP46Flow(this, onDone, onBack, { relays: this._state.relays }),
          nip55:      () => renderNIP55Flow(this, onDone, onBack),
          newkey:     () => renderNewKeypairFlow(this, onDone, onBack),
          google:     () => renderGoogleFlow(this, onDone, onBack),
          pomegranate:() => renderPomegranateFlow(this, onDone, onBack),
          _newhere:   () => renderNewHereChooser(this, id => { this._state.method = id; this._render(); }, onBack),
        };
        const flowFn = flowMap[this._state.method];
        if (flowFn) body.appendChild(flowFn());
      } else {
        body.appendChild(renderMethodSelection(this, id => {
          this._state.method = id; this._render();
        }, {
          methodFilter: this._state.methodFilter,
          density:      this._state.density,
          layout:       this._state.layout,
          callout:      this._state.callout,
          footer:       this._state.footer,
          header:       this._state.header,
          tip:          this._state.tip,
        }));
      }

      modal.appendChild(body);
      overlay.appendChild(modal);
      this._shadow.appendChild(overlay);
    }

    _dispatch(eventName, detail) {
      this.dispatchEvent(new CustomEvent(eventName, { bubbles: true, composed: true, detail }));
    }
  }

  customElements.define('nostr-signer', NostrSignerElement);

  // ── Imperative API (MILL global) ──────────────────────────────────────────────
  let _imperativeEl = null;
  function _getOrCreateElement() {
    if (!_imperativeEl) {
      _imperativeEl = document.createElement('nostr-signer');
      document.body.appendChild(_imperativeEl);
    }
    return _imperativeEl;
  }

  const MILL = {
    /**
     * Open the signer modal.
     * @param {{ theme?: string|object, onConnected?: function, onClose?: function,
     *           appName?: string, amberCallback?: string }} opts
     *   appName       — name shown to the user's remote signer / bunker (NIP-46)
     *                   and Amber (NIP-55) instead of the default page title.
     *   amberCallback — server callback URL for the NIP-55 Amber round-trip.
     */
    open(opts = {}) {
      const el = _getOrCreateElement();
      // Surface host config as element attributes so the per-method flows
      // (which read attributes off the host element) pick them up.
      if (opts.appName) el.setAttribute('app-name', opts.appName);
      if (opts.amberCallback) el.setAttribute('amber-callback', opts.amberCallback);
      // Set/clear per-open so a reconfigured open() doesn't inherit stale config.
      if (opts.oauthShim) el.setAttribute('oauth-shim', opts.oauthShim); else el.removeAttribute('oauth-shim');
      el.open(opts);   // pomegranate config travels on _state (set in el.open)
      return el;
    },

    /**
     * Rebuild a signer after a page reload WITHOUT opening the picker, using the
     * state mill persisted at login (sessionStorage). The host is responsible for
     * remembering which method + pubkey the session used (e.g. from onConnected)
     * and passing them here.
     *
     * Accepts mill method ids (nip07, nip46, nip55, privatekey, newkey, readonly)
     * or the common grain-style aliases (browser_extension, bunker, amber,
     * encrypted_key, none).
     *
     * Returns the same signer shape onConnected gives, or null if restore isn't
     * possible (no persisted state, extension missing, user cancelled the
     * password prompt). On null, the host should fall back to MILL.open().
     *
     * @param {{ method: string, pubkey: string }} opts
     * @returns {Promise<object|null>}
     */
    async restore({ method, pubkey } = {}) {
      const m = RESTORE_METHOD_ALIASES[method] || method;
      switch (m) {
        case 'nip07':
          if (!window.nostr || typeof window.nostr.signEvent !== 'function') return null;
          try {
            const ext = await window.nostr.getPublicKey();
            if (pubkey && ext && ext.toLowerCase() !== pubkey.toLowerCase()) return null;
            return createNIP07Signer(pubkey || ext);
          } catch { return null; }

        case 'readonly':
          return pubkey ? createReadOnlySigner(pubkey) : null;

        case 'privatekey': {
          if (!loadEncryptedNsec() || !pubkey) return null;
          const el = _getOrCreateElement();
          return createPrivateKeySigner({
            pubkey,
            perms: loadSignPerms() || defaultPerms(),
            promptPassword: () => el.promptPassword({ subtitle: 'Enter your session password to unlock signing.' }),
            requestConsent: req => el.requestConsent({ ...req, npub: hexToNpub(pubkey) }),
          });
        }

        case 'nip46': {
          const st = loadBunkerState();
          if (!st || !st.clientSecretKey || !st.remotePubkey) return null;
          try {
            const client = new NIP46Client({
              relays: st.relays,
              clientSecretKey: hexToBytes$2(st.clientSecretKey),
            });
            await client.restore({ remotePubkey: st.remotePubkey, relays: st.relays, userPubkey: st.userPubkey });
            return createNIP46Signer(client, st.userPubkey || pubkey);
          } catch { return null; }
        }

        case 'nip55': {
          if (!pubkey) return null;
          const callbackUrl = _imperativeEl?.getAttribute?.('amber-callback') || null;
          const appName = _imperativeEl?.getAttribute?.('app-name') || document.title || 'Nostr App';
          return createNIP55Signer({ pubkey, callbackUrl, appName });
        }

        default:
          return null;
      }
    },

    /** Wipe all persisted restore state (call on logout). */
    clearRestoreState() {
      clearStoredNsec();
      clearSignPerms();
      clearBunkerState();
    },

    /** Apply a theme globally to the auto-created element. */
    setTheme(theme) {
      _getOrCreateElement().setTheme(theme);
    },

    /** Close the modal programmatically. */
    close() { _imperativeEl?.close(); },

    /**
     * Open the per-kind signing-permissions manager.
     *
     * Entirely optional — the consent card already links here, and mill is only
     * on screen when it's asking for something, so a host that never calls this
     * still gives users a way in. Wire it to a menu item if you want a direct
     * route. Private-key signing only; other methods manage approvals elsewhere.
     */
    openSettings() { _getOrCreateElement().openSettings(); },

    /** Expose theme utilities. */
    themes: THEMES,
    brandTheme,
    applyTheme,

    /** Install a returned signer as window.nostr (so existing nostr code works). */
    installAsWindowNostr,

    /** Low-level builders (advanced use). */
    signers: {
      createNIP07Signer, createNIP46Signer, createNIP55Signer,
      createPrivateKeySigner, createReadOnlySigner,
    },

    /** NIP-46 client class for advanced direct use. */
    NIP46Client,
  };

  // UMD/global export
  if (typeof window !== 'undefined') window.MILL = MILL;

  /**
   * UMD entry point. Single default export so window.MILL = MILL directly,
   * not window.MILL = { default: MILL, ... }.
   * Named exports are still available on the MILL object as MILL.themes etc.
   */

  return MILL;

}));
//# sourceMappingURL=mill.umd.js.map
