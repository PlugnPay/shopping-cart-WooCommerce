#!/usr/bin/env sh

# Purpose: Easily rebuild zip file for given payment module.

# Usage: ./build.sh [folderName] [zipName]
# i.e.   ./build.sh woocommerce_ss2_module
# i.e.   ./build.sh woocommerce_ss2_module woocommerce_ss2_module.zip

# Define arguments/settings
moduleFolder=$1             # [Required] module's folder name 
moduleZip=$2                # [Optional] module's zip file name
rootOffset="$moduleFolder"; # folder adjustment to being zipping from

# ensure folder exists
if [ ! -d "$moduleFolder" ] 
then
  echo "==> Directory $moduleFolder DOES NOT exist." 
  exit 9999 # die with error code 9999
fi

# define zip name if needed
if [ ! -n "$moduleZip" ]
then
  moduleZip="$moduleFolder.zip"
  echo "==> Zip name undefined.  Assuming $moduleZip"
fi  

# unlink old zip file, if exists
if [ -f "$moduleZip" ] 
then
  echo "==> File $moduleZip exists. Unlinking..." 
  unlink $moduleZip
fi

# zip up the module, beginning at the rootOffset adjustment
echo "==> Generating $moduleZip - Please Wait..." 
zip -r $moduleZip $rootOffset -x '.git/*' -x .gitignore -x .editorconfig -x build.sh
echo "==> Completed $moduleZip creation..."

## finally do a little clean-up
unset moduleFolder
unset moduleZip
unset rootOffset

