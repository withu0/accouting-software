import { cn } from '@/lib/utils';
import { Upload } from 'lucide-react';
import { type ChangeEvent, type DragEvent, useRef, useState } from 'react';

interface FileDropZoneProps {
    accept?: string;
    disabled?: boolean;
    file: File | null;
    onFileChange: (file: File | null) => void;
    id?: string;
}

export function FileDropZone({ accept = '.csv,.txt', disabled = false, file, onFileChange, id = 'file' }: FileDropZoneProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);

    const handleFile = (selected: File | null) => {
        onFileChange(selected);
    };

    const handleDrop = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        setIsDragging(false);
        if (disabled) {
            return;
        }
        const dropped = e.dataTransfer.files?.[0] ?? null;
        if (dropped) {
            handleFile(dropped);
        }
    };

    const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
        handleFile(e.target.files?.[0] ?? null);
    };

    return (
        <div
            role="button"
            tabIndex={disabled ? -1 : 0}
            onKeyDown={(e) => {
                if (!disabled && (e.key === 'Enter' || e.key === ' ')) {
                    inputRef.current?.click();
                }
            }}
            onClick={() => !disabled && inputRef.current?.click()}
            onDragOver={(e) => {
                e.preventDefault();
                if (!disabled) {
                    setIsDragging(true);
                }
            }}
            onDragLeave={() => setIsDragging(false)}
            onDrop={handleDrop}
            className={cn(
                'flex cursor-pointer flex-col items-center justify-center gap-4 rounded-xl border-2 border-dashed px-8 py-12 text-center transition-all duration-200',
                isDragging
                    ? 'border-primary bg-primary/5 shadow-inner shadow-primary/10'
                    : 'border-border/70 bg-muted/20 hover:border-primary/40 hover:bg-muted/40 hover:shadow-sm',
                disabled && 'cursor-not-allowed opacity-50',
            )}
        >
            <input
                ref={inputRef}
                id={id}
                type="file"
                accept={accept}
                disabled={disabled}
                className="sr-only"
                onChange={handleChange}
            />
            <div className="from-primary/20 to-primary/5 text-primary flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br ring-1 ring-primary/10">
                <Upload className="size-6" />
            </div>
            {file ? (
                <div>
                    <p className="text-sm font-medium">{file.name}</p>
                    <p className="text-muted-foreground mt-1 text-xs">クリックまたはドラッグでファイルを変更</p>
                </div>
            ) : (
                <div>
                    <p className="text-sm font-medium">CSVファイルをドラッグ＆ドロップ</p>
                    <p className="text-muted-foreground mt-1 text-xs">またはクリックしてファイルを選択</p>
                </div>
            )}
        </div>
    );
}
