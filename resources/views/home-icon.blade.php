<svg viewBox="0 0 110 90" fill="none" xmlns="http://www.w3.org/2000/svg">
@switch($kind)
@case('numbers')<rect x="8" y="23" width="44" height="55" rx="12" fill="#fffaf3" transform="rotate(-9 8 23)"/><rect x="57" y="11" width="42" height="57" rx="12" fill="#c2dace" transform="rotate(8 57 11)"/><text x="29" y="63" font-size="42" fill="#9c4b3b" font-family="Trebuchet MS,sans-serif" font-weight="bold">1</text><text x="66" y="53" font-size="40" fill="#31584d" font-family="Trebuchet MS,sans-serif" font-weight="bold">2</text>@break
@case('plus')<circle cx="34" cy="46" r="28" fill="#fffaf3"/><path d="M34 31v30M19 46h30" stroke="#31584d" stroke-width="8" stroke-linecap="round"/><circle cx="83" cy="24" r="12" fill="#e6b099"/><circle cx="88" cy="64" r="15" fill="#f3d780"/>@break
@case('minus')<rect x="10" y="18" width="78" height="56" rx="18" fill="#fffaf3" transform="rotate(-7 10 18)"/><path d="M31 44h34" stroke="#736294" stroke-width="9" stroke-linecap="round"/><circle cx="91" cy="66" r="12" fill="#d2b5de"/>@break
@case('shapes')<path d="M30 9 55 54H5Z" fill="#d4b2db"/><rect x="60" y="40" width="39" height="39" rx="8" fill="#c0d8cd" transform="rotate(10 60 40)"/><circle cx="28" cy="69" r="17" fill="#eca992"/>@break
@case('tens')<rect x="6" y="25" width="66" height="44" rx="8" fill="#fffaf3" stroke="#6d91a7" stroke-width="2"/><path d="M19 25v44m13-44v44m13-44v44m13-44v44M6 47h66" stroke="#6d91a7" stroke-width="2"/><circle cx="88" cy="39" r="10" fill="#e9b4a3"/><circle cx="90" cy="65" r="10" fill="#e9b4a3"/>@break
@case('operations')<rect x="4" y="19" width="47" height="48" rx="14" fill="#fffaf3" transform="rotate(-10 4 19)"/><rect x="54" y="28" width="47" height="48" rx="14" fill="#c3dbd0" transform="rotate(9 54 28)"/><path d="M28 28v26M15 41h26M68 53h23" stroke="#31584d" stroke-width="6" stroke-linecap="round"/>@break
@case('measure')<path d="m12 62 72-46 20 30-72 46Z" fill="#fffaf3" transform="translate(-4 -8)"/><path d="m24 50 7 11m8-21 7 11m8-21 7 11m8-21 7 11m8-21 7 11" stroke="#9c7933" stroke-width="3" stroke-linecap="round"/>@break
@case('chart')<path d="M12 16v59h87" stroke="#736294" stroke-width="3" stroke-linecap="round"/><rect x="25" y="47" width="16" height="27" rx="5" fill="#e9b4a3"/><rect x="49" y="28" width="16" height="46" rx="5" fill="#a9c8b8"/><rect x="73" y="11" width="16" height="63" rx="5" fill="#fffaf3"/>@break
@endswitch
</svg>
