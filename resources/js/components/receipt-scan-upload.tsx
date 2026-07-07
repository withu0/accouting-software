import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { getCroppedImageFile, rotateImageSrc, type PixelCropArea } from '@/lib/crop-image';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { useIsMobile } from '@/hooks/use-mobile';
import { AlertCircle, Camera, Loader2, RotateCcw, RotateCw, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import ReactCrop, { centerCrop, convertToPixelCrop, type Crop, type PixelCrop } from 'react-image-crop';
import 'react-image-crop/dist/ReactCrop.css';

interface ReceiptScanUploadProps {
    disabled?: boolean;
    available: boolean;
    error?: string;
}

type Step = 'idle' | 'crop' | 'confirm';

const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const WEBCAM_CAPTURE_QUALITY = 0.92;

function isAcceptedImageFile(file: File): boolean {
    return ACCEPTED_IMAGE_TYPES.includes(file.type);
}

function canUseWebCamera(): boolean {
    return typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices?.getUserMedia);
}

function toNaturalPixelCrop(pixelCrop: PixelCrop, image: HTMLImageElement): PixelCropArea {
    const scaleX = image.naturalWidth / image.width;
    const scaleY = image.naturalHeight / image.height;

    return {
        x: Math.round(pixelCrop.x * scaleX),
        y: Math.round(pixelCrop.y * scaleY),
        width: Math.round(pixelCrop.width * scaleX),
        height: Math.round(pixelCrop.height * scaleY),
    };
}

export default function ReceiptScanUpload({ disabled = false, available, error }: ReceiptScanUploadProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const cameraInputRef = useRef<HTMLInputElement>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const isMobile = useIsMobile();
    const imageRef = useRef<HTMLImageElement>(null);
    const [step, setStep] = useState<Step>('idle');
    const [cameraOpen, setCameraOpen] = useState(false);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [cameraStarting, setCameraStarting] = useState(false);
    const [capturingPhoto, setCapturingPhoto] = useState(false);
    const [scanning, setScanning] = useState(false);
    const [preparingCrop, setPreparingCrop] = useState(false);
    const [rotating, setRotating] = useState(false);

    const [imageSrc, setImageSrc] = useState<string | null>(null);
    const [crop, setCrop] = useState<Crop>();
    const [completedCrop, setCompletedCrop] = useState<PixelCrop>();

    const [croppedPreviewUrl, setCroppedPreviewUrl] = useState<string | null>(null);
    const [croppedFile, setCroppedFile] = useState<File | null>(null);
    const [isDragActive, setIsDragActive] = useState(false);
    const [fileTypeError, setFileTypeError] = useState<string | null>(null);

    const revokeUrl = useCallback((url: string | null) => {
        if (url) {
            URL.revokeObjectURL(url);
        }
    }, []);

    const stopCamera = useCallback(() => {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;

        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }
    }, []);

    const reset = useCallback(() => {
        stopCamera();
        setCameraOpen(false);
        setCameraError(null);
        setCameraStarting(false);
        setCapturingPhoto(false);
        revokeUrl(imageSrc);
        revokeUrl(croppedPreviewUrl);
        setImageSrc(null);
        setCroppedPreviewUrl(null);
        setCroppedFile(null);
        setCrop(undefined);
        setCompletedCrop(undefined);
        setStep('idle');
        setPreparingCrop(false);
        setRotating(false);
        setIsDragActive(false);
        setFileTypeError(null);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
        if (cameraInputRef.current) {
            cameraInputRef.current.value = '';
        }
    }, [croppedPreviewUrl, imageSrc, revokeUrl, stopCamera]);

    useEffect(() => {
        return () => {
            stopCamera();
            revokeUrl(imageSrc);
            revokeUrl(croppedPreviewUrl);
        };
    }, [croppedPreviewUrl, imageSrc, revokeUrl, stopCamera]);

    useEffect(() => {
        if (!cameraOpen) {
            stopCamera();
            setCameraStarting(false);
            return;
        }

        let cancelled = false;

        const startCamera = async () => {
            setCameraError(null);
            setCameraStarting(true);

            try {
                if (!canUseWebCamera()) {
                    throw new Error('unsupported');
                }

                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: isMobile ? 'environment' : 'user' },
                });

                if (cancelled) {
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }

                streamRef.current = stream;

                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    await videoRef.current.play();
                }
            } catch {
                if (!cancelled) {
                    setCameraError('カメラにアクセスできませんでした。ブラウザの権限を確認してください。');
                }
            } finally {
                if (!cancelled) {
                    setCameraStarting(false);
                }
            }
        };

        void startCamera();

        return () => {
            cancelled = true;
            stopCamera();
        };
    }, [cameraOpen, isMobile, stopCamera]);

    const processFile = useCallback(
        (file: File) => {
            if (!isAcceptedImageFile(file)) {
                setFileTypeError('JPEG、PNG、WebP形式の画像をアップロードしてください。');
                return;
            }

            setFileTypeError(null);
            revokeUrl(imageSrc);
            revokeUrl(croppedPreviewUrl);
            setCroppedPreviewUrl(null);
            setCroppedFile(null);
            setCrop(undefined);
            setCompletedCrop(undefined);

            const url = URL.createObjectURL(file);
            setImageSrc(url);
            setStep('crop');
        },
        [croppedPreviewUrl, imageSrc, revokeUrl],
    );

    const uploadDisabled = disabled || scanning || step !== 'idle' || cameraOpen;

    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        processFile(file);
        event.target.value = '';
    };

    const handleCameraClick = () => {
        if (isMobile || !canUseWebCamera()) {
            cameraInputRef.current?.click();
            return;
        }

        setCameraError(null);
        setCameraOpen(true);
    };

    const handleCameraDialogOpenChange = (open: boolean) => {
        setCameraOpen(open);
        if (!open) {
            setCameraError(null);
            setCameraStarting(false);
            setCapturingPhoto(false);
        }
    };

    const handleCapturePhoto = async () => {
        const video = videoRef.current;
        if (!video || cameraStarting || capturingPhoto || cameraError) {
            return;
        }

        if (video.videoWidth <= 0 || video.videoHeight <= 0) {
            setCameraError('カメラの準備ができていません。しばらく待ってから再度お試しください。');
            return;
        }

        setCapturingPhoto(true);

        try {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const context = canvas.getContext('2d');
            if (!context) {
                throw new Error('canvas');
            }

            context.drawImage(video, 0, 0);

            const blob = await new Promise<Blob | null>((resolve) => {
                canvas.toBlob(resolve, 'image/jpeg', WEBCAM_CAPTURE_QUALITY);
            });

            if (!blob) {
                throw new Error('blob');
            }

            const file = new File([blob], `receipt-${Date.now()}.jpg`, { type: 'image/jpeg' });
            setCameraOpen(false);
            processFile(file);
        } catch {
            setCameraError('写真の撮影に失敗しました。もう一度お試しください。');
        } finally {
            setCapturingPhoto(false);
        }
    };

    const handleDragEnter = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.stopPropagation();
        if (!uploadDisabled) {
            setIsDragActive(true);
        }
    };

    const handleDragLeave = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.stopPropagation();
        if (event.currentTarget.contains(event.relatedTarget as Node)) {
            return;
        }
        setIsDragActive(false);
    };

    const handleDragOver = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.stopPropagation();
    };

    const handleDrop = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.stopPropagation();
        setIsDragActive(false);

        if (uploadDisabled) {
            return;
        }

        const file = event.dataTransfer.files?.[0];
        if (!file) {
            return;
        }

        processFile(file);
    };

    const handleImageLoad = (event: React.SyntheticEvent<HTMLImageElement>) => {
        const { width, height } = event.currentTarget;
        setCrop(centerCrop({ unit: '%', width: 80, height: 60 }, width, height));
        setCompletedCrop(undefined);
    };

    const handleRotate = async (direction: 'cw' | 'ccw') => {
        if (!imageSrc || preparingCrop || rotating) {
            return;
        }

        setRotating(true);

        try {
            const rotatedSrc = await rotateImageSrc(imageSrc, direction);
            revokeUrl(imageSrc);
            setImageSrc(rotatedSrc);
            setCrop(undefined);
            setCompletedCrop(undefined);
        } catch {
            // Keep current image if rotation fails.
        } finally {
            setRotating(false);
        }
    };

    const resolveNaturalCrop = (): PixelCropArea | null => {
        const image = imageRef.current;
        if (!image || !crop?.width || !crop?.height) {
            return null;
        }

        const pixelCrop = completedCrop ?? convertToPixelCrop(crop, image.width, image.height);

        if (pixelCrop.width <= 0 || pixelCrop.height <= 0) {
            return null;
        }

        return toNaturalPixelCrop(pixelCrop, image);
    };

    const handleProceedToConfirm = async () => {
        if (!imageSrc) {
            return;
        }

        const naturalCrop = resolveNaturalCrop();
        if (!naturalCrop) {
            return;
        }

        setPreparingCrop(true);

        try {
            const file = await getCroppedImageFile(imageSrc, naturalCrop);
            revokeUrl(croppedPreviewUrl);
            const previewUrl = URL.createObjectURL(file);
            setCroppedFile(file);
            setCroppedPreviewUrl(previewUrl);
            setStep('confirm');
        } catch {
            reset();
        } finally {
            setPreparingCrop(false);
        }
    };

    const handleConfirmUpload = () => {
        if (!croppedFile) {
            return;
        }

        setScanning(true);

        router.post(
            route('receipt-scans.store'),
            { file: croppedFile },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setScanning(false);
                    reset();
                },
            },
        );
    };

    const handleBackToCrop = () => {
        revokeUrl(croppedPreviewUrl);
        setCroppedPreviewUrl(null);
        setCroppedFile(null);
        setStep('crop');
    };

    const handleCropDialogOpenChange = (open: boolean) => {
        if (!open && step === 'crop' && !preparingCrop) {
            reset();
        }
    };

    const handleConfirmDialogOpenChange = (open: boolean) => {
        if (!open && step === 'confirm' && !scanning) {
            reset();
        }
    };

    const canProceed = Boolean(crop?.width && crop?.height);

    if (!available) {
        return (
            <Alert>
                <AlertCircle className="size-4" />
                <AlertDescription>
                    領収書の自動読み取りは未設定です。管理者が OpenAI API キーを設定すると利用できます。
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <>
            <div
                role="button"
                tabIndex={uploadDisabled ? -1 : 0}
                aria-disabled={uploadDisabled}
                aria-label="領収書画像をアップロード"
                className={cn(
                    'space-y-3 rounded-lg border border-dashed p-4 transition-colors',
                    isDragActive && 'border-primary bg-primary/5',
                    !uploadDisabled && !isMobile && 'cursor-pointer hover:border-primary/50 hover:bg-muted/50',
                    uploadDisabled && 'cursor-not-allowed opacity-60',
                )}
                onClick={() => {
                    if (!uploadDisabled && !isMobile) {
                        fileInputRef.current?.click();
                    }
                }}
                onKeyDown={(event) => {
                    if (!uploadDisabled && !isMobile && (event.key === 'Enter' || event.key === ' ')) {
                        event.preventDefault();
                        fileInputRef.current?.click();
                    }
                }}
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
            >
                <Label htmlFor="receipt-scan-file" className="pointer-events-none">
                    領収書から読み取る
                </Label>
                <p className="text-muted-foreground pointer-events-none text-sm">
                    カメラで撮影するか、保存済みの画像を選択してください。
                    {!isMobile && ' 画像のドラッグ＆ドロップにも対応しています。'}
                    領収書部分を切り取ってから読み取ります。
                </p>

                <div className="pointer-events-none flex flex-col items-center justify-center gap-2 rounded-md border border-dashed bg-muted/30 py-6">
                    {scanning ? (
                        <Loader2 className="text-muted-foreground size-8 animate-spin" />
                    ) : (
                        <Camera className="text-muted-foreground size-8" />
                    )}
                    <p className="text-muted-foreground text-sm">
                        {scanning ? '読み取り中...' : isDragActive ? 'ここにドロップ' : 'JPEG / PNG / WebP'}
                    </p>
                </div>

                <div
                    className="flex flex-col gap-2 sm:flex-row"
                    onClick={(event) => event.stopPropagation()}
                    onKeyDown={(event) => event.stopPropagation()}
                >
                    <Button
                        type="button"
                        className="flex-1"
                        disabled={uploadDisabled}
                        onClick={handleCameraClick}
                    >
                        <Camera className="size-4" />
                        カメラで撮影
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        className="flex-1"
                        disabled={uploadDisabled}
                        onClick={() => fileInputRef.current?.click()}
                    >
                        <Upload className="size-4" />
                        画像を選択
                    </Button>
                </div>

                <input
                    ref={cameraInputRef}
                    id="receipt-scan-camera"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    className="hidden"
                    disabled={uploadDisabled}
                    onChange={handleFileChange}
                />

                <input
                    ref={fileInputRef}
                    id="receipt-scan-file"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    disabled={uploadDisabled}
                    onChange={handleFileChange}
                />

                <InputError message={fileTypeError ?? error} />
            </div>

            <Dialog open={cameraOpen} onOpenChange={handleCameraDialogOpenChange}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>カメラで撮影</DialogTitle>
                        <DialogDescription>
                            領収書がはっきり写るように調整してから、撮影ボタンを押してください。
                        </DialogDescription>
                    </DialogHeader>

                    <div className="overflow-hidden rounded-md border bg-muted">
                        {cameraError ? (
                            <div className="flex min-h-64 items-center justify-center p-6">
                                <Alert variant="destructive">
                                    <AlertCircle className="size-4" />
                                    <AlertDescription>{cameraError}</AlertDescription>
                                </Alert>
                            </div>
                        ) : (
                            <div className="relative flex min-h-64 items-center justify-center bg-black">
                                {cameraStarting && (
                                    <Loader2 className="absolute z-10 size-8 animate-spin text-white" />
                                )}
                                <video
                                    ref={videoRef}
                                    autoPlay
                                    playsInline
                                    muted
                                    className={cn(
                                        'max-h-[60vh] w-full object-contain',
                                        cameraStarting && 'opacity-0',
                                    )}
                                />
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={capturingPhoto}
                            onClick={() => handleCameraDialogOpenChange(false)}
                        >
                            キャンセル
                        </Button>
                        <Button
                            type="button"
                            disabled={cameraStarting || capturingPhoto || Boolean(cameraError)}
                            onClick={handleCapturePhoto}
                        >
                            {capturingPhoto ? <Loader2 className="size-4 animate-spin" /> : <Camera className="size-4" />}
                            撮影する
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={step === 'crop'} onOpenChange={handleCropDialogOpenChange}>
                <DialogContent className="flex h-[90vh] max-h-[90vh] max-w-3xl flex-col gap-3 overflow-hidden sm:gap-4">
                    <DialogHeader className="shrink-0">
                        <DialogTitle>領収書部分を切り取る</DialogTitle>
                        <DialogDescription asChild>
                            <div className="space-y-2 text-sm text-muted-foreground">
                                <p>
                                    枠の角や辺をドラッグして、領収書の範囲を自由な大きさ・形に調整できます。不要な背景は除くと読み取りに使うデータ量を減らせます。
                                </p>
                                <p>
                                    文字が正しい向き（上から下に読める状態）になるよう回転ボタンで調整してください。向きが正しいほど、日付・金額などの読み取り精度が上がります。
                                </p>
                            </div>
                        </DialogDescription>
                    </DialogHeader>

                    {imageSrc && (
                        <div className="flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-md bg-muted p-2">
                            <ReactCrop
                                crop={crop}
                                className="max-h-full max-w-full"
                                onChange={(_pixelCrop, percentCrop) => setCrop(percentCrop)}
                                onComplete={(pixelCrop) => setCompletedCrop(pixelCrop)}
                            >
                                <img
                                    ref={imageRef}
                                    src={imageSrc}
                                    alt="領収書"
                                    className="block max-w-full object-contain"
                                    style={{ maxHeight: 'calc(90vh - 19rem)' }}
                                    onLoad={handleImageLoad}
                                />
                            </ReactCrop>
                        </div>
                    )}

                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={preparingCrop || rotating}
                            onClick={() => handleRotate('ccw')}
                        >
                            {rotating ? <Loader2 className="size-4 animate-spin" /> : <RotateCcw className="size-4" />}
                            左に90°
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={preparingCrop || rotating}
                            onClick={() => handleRotate('cw')}
                        >
                            {rotating ? <Loader2 className="size-4 animate-spin" /> : <RotateCw className="size-4" />}
                            右に90°
                        </Button>
                    </div>

                    <DialogFooter className="shrink-0">
                        <Button type="button" variant="outline" disabled={preparingCrop || rotating} onClick={reset}>
                            キャンセル
                        </Button>
                        <Button type="button" disabled={preparingCrop || rotating || !canProceed} onClick={handleProceedToConfirm}>
                            {preparingCrop ? <Loader2 className="size-4 animate-spin" /> : null}
                            送信
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={step === 'confirm'} onOpenChange={handleConfirmDialogOpenChange}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>読み取り内容の確認</DialogTitle>
                        <DialogDescription asChild>
                            <div className="space-y-2 text-sm text-muted-foreground">
                                <p>
                                    切り取った画像が AI に送信されます。領収書以外の部分を除くことで、読み取りに使うデータ量（コスト）を抑えられます。
                                </p>
                                <p>
                                    日付・金額・店名など、領収書の必要な部分がすべて枠内に入っているかご確認ください。枠が小さすぎると読み取り精度が下がることがあります。
                                </p>
                            </div>
                        </DialogDescription>
                    </DialogHeader>

                    {croppedPreviewUrl && (
                        <div className="overflow-hidden rounded-md border bg-muted">
                            <img src={croppedPreviewUrl} alt="切り取った領収書のプレビュー" className="mx-auto max-h-64 w-auto object-contain" />
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" disabled={scanning} onClick={handleBackToCrop}>
                            戻って調整
                        </Button>
                        <Button type="button" disabled={scanning || !croppedFile} onClick={handleConfirmUpload}>
                            {scanning ? <Loader2 className="size-4 animate-spin" /> : null}
                            この内容で読み取る
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
