// 與後端 App\Support\Mask::string() 同演算法（奇數索引換 *）；兩端須同步修改。
// 註：此處以 UTF-16 code unit 計（split('')），PHP 端以 code point 計（mb_*）；ASCII/BMP（含中文）
// 一致，僅星芒字（emoji 等 surrogate pair）會分歧。本函式僅遮統一編號（ASCII），不受影響。
export function maskString(value) {
    return value.split('').map((c, i) => (i % 2 === 1 ? '*' : c)).join('');
}
