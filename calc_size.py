import os

def get_size(start_path = '.'):
    sizes = []
    for dirpath, dirnames, filenames in os.walk(start_path):
        # Only top level directories
        if dirpath == start_path:
            for d in dirnames:
                d_path = os.path.join(dirpath, d)
                total_size = 0
                for dp, dn, fn in os.walk(d_path):
                    for f in fn:
                        fp = os.path.join(dp, f)
                        if not os.path.islink(fp):
                            total_size += os.path.getsize(fp)
                sizes.append((d, total_size / (1024*1024)))
    
    sizes.sort(key=lambda x: x[1], reverse=True)
    for name, size in sizes:
        print(f"{name}: {size:.2f} MB")

get_size()
