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
import { AlertCircle, Loader2, RotateCcw, RotateCw, Upload } from 'lucide-react';
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

function isAcceptedImageFile(file: File): boolean {
    return ACCEPTED_IMAGE_TYPES.includes(file.type);
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
    const inputRef = useRef<HTMLInputElement>(null);
    const imageRef = useRef<HTMLImageElement>(null);
    const [step, setStep] = useState<Step>('idle');
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

    const reset = useCallback(() => {
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

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }, [croppedPreviewUrl, imageSrc, revokeUrl]);

    useEffect(() => {
        return () => {
            revokeUrl(imageSrc);
            revokeUrl(croppedPreviewUrl);
        };
    }, [croppedPreviewUrl, imageSrc, revokeUrl]);

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

    const uploadDisabled = disabled || scanning || step !== 'idle';

    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        processFile(file);
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
                aria-label="領収書画像をドラッグ＆ドロップまたはクリックして選択"
                className={cn(
                    'space-y-3 rounded-lg border border-dashed p-4 transition-colors',
                    isDragActive && 'border-primary bg-primary/5',
                    !uploadDisabled && 'cursor-pointer hover:border-primary/50 hover:bg-muted/50',
                    uploadDisabled && 'cursor-not-allowed opacity-60',
                )}
                onClick={() => {
                    if (!uploadDisabled) {
                        inputRef.current?.click();
                    }
                }}
                onKeyDown={(event) => {
                    if (!uploadDisabled && (event.key === 'Enter' || event.key === ' ')) {
                        event.preventDefault();
                        inputRef.current?.click();
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
                    画像をドラッグ＆ドロップするか、クリックして選択してください。領収書部分を切り取ってから読み取ります。
                </p>

                <div className="pointer-events-none flex flex-col items-center justify-center gap-2 rounded-md border border-dashed bg-muted/30 py-6">
                    {scanning ? (
                        <Loader2 className="text-muted-foreground size-8 animate-spin" />
                    ) : (
                        <Upload className="text-muted-foreground size-8" />
                    )}
                    <p className="text-muted-foreground text-sm">
                        {scanning ? '読み取り中...' : isDragActive ? 'ここにドロップ' : 'JPEG / PNG / WebP'}
                    </p>
                </div>

                <input
                    ref={inputRef}
                    id="receipt-scan-file"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    capture="environment"
                    className="hidden"
                    disabled={uploadDisabled}
                    onChange={handleFileChange}
                />

                <InputError message={fileTypeError ?? error} />
            </div>

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
