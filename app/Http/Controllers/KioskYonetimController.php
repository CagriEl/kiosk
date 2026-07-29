<?php

namespace App\Http\Controllers;

use App\Services\Kiosk\KioskDailyCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class KioskYonetimController extends Controller
{
    private const ALLOWED_FILES = [
        'kilitle.cmd',
        'bekleyen.html',
        'bekleyen-kur.cmd',
        'vpn-koru.cmd',
        'kurulum.cmd',
        'kurulum.reg',
        'baylan.aspx',
        'kapanma-izle.cmd',
    ];

    public function loginForm(): View|RedirectResponse
    {
        if (session('kiosk_yonetim_ok')) {
            return redirect()->route('yonetim.index');
        }

        return view('kiosk.yonetim-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string|max:200',
        ]);

        $expected = (string) config('kiosk.yonetim_password');
        $given = (string) $request->input('password');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return back()->withErrors(['password' => 'Şifre hatalı.'])->onlyInput();
        }

        $request->session()->put('kiosk_yonetim_ok', true);
        $request->session()->regenerate();

        return redirect()->route('yonetim.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('kiosk_yonetim_ok');
        $request->session()->regenerate();

        return redirect()->route('yonetim.login');
    }

    public function index(): View
    {
        $dir = $this->storageDir();
        $files = [];
        foreach (self::ALLOWED_FILES as $name) {
            $path = $dir.DIRECTORY_SEPARATOR.$name;
            if (is_file($path)) {
                $files[] = [
                    'name' => $name,
                    'size' => filesize($path),
                    'label' => $this->fileLabel($name),
                ];
            }
        }

        return view('kiosk.yonetim-index', [
            'files' => $files,
            'supportPhone' => config('kiosk.support_phone'),
        ]);
    }

    public function download(string $file): BinaryFileResponse
    {
        if (! in_array($file, self::ALLOWED_FILES, true)) {
            throw new NotFoundHttpException();
        }

        $path = $this->storageDir().DIRECTORY_SEPARATOR.$file;
        if (! is_file($path)) {
            throw new NotFoundHttpException();
        }

        return response()->download($path, $file);
    }

    public function report(Request $request, KioskDailyCounter $counter): View
    {
        $days = (int) $request->query('days', 30);
        $kioskId = $request->query('kiosk_id');
        $kioskId = is_string($kioskId) && $kioskId !== '' ? $kioskId : null;

        return view('kiosk.report', [
            'rows' => $counter->report($kioskId, $days),
            'today' => $counter->today($kioskId),
            'days' => max(1, min(90, $days)),
            'kioskId' => $kioskId ?: 'tümü',
            'supportPhone' => config('kiosk.support_phone'),
            'yonetim' => true,
        ]);
    }

    private function storageDir(): string
    {
        $dir = resource_path('kiosk-yonetim');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    private function fileLabel(string $name): string
    {
        return match ($name) {
            'kilitle.cmd' => 'Windows kilit / aç (kilitle.cmd ac)',
            'bekleyen.html' => 'VPN bekleyen sayfa',
            'bekleyen-kur.cmd' => 'Bekleyen kurulum',
            'vpn-koru.cmd' => 'GlobalProtect VPN koruyucu (kur + çalıştır)',
            'kurulum.cmd' => 'Baylan IE protokol kurulum',
            'kurulum.reg' => 'Baylan IE kayıt (.reg)',
            'baylan.aspx' => 'Baylan ASPX kopyası',
            default => $name,
        };
    }
}
